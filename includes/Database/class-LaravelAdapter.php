<?php
/**
 * Laravel Database Adapter
 *
 * Implements the DatabaseAdapterInterface for Laravel environments
 * using Laravel's DB facade and query builder.
 *
 * @package SmartLicenseServer\Database
 */

namespace SmartLicenseServer\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Exception;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Adapter for Laravel database access.
 *
 * This adapter provides a unified interface to Laravel's database layer,
 * allowing Smart License Server to operate in Laravel environments.
 */
class LaravelAdapter implements DatabaseAdapterInterface {

    /**
     * Last inserted ID.
     *
     * @var int|null
     */
    protected $insert_id = null;

    /**
     * Last executed error message.
     *
     * @var string|null
     */
    protected $last_error = null;

    /**
     * Database connection name.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * Constructor.
     *
     * @param string|null $connection Optional. Custom database connection name.
     */
    public function __construct( $connection = null ) {
        $this->connection = $connection;
    }

    /**
     * Get the database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function get_connection() {
        return $this->connection ? DB::connection( $this->connection ) : DB::connection();
    }

    /**
     * Establish a database connection.
     *
     * For Laravel, the connection is already managed by the framework.
     *
     * @return bool Always true.
     */
    public function connect() {
        try {
            // Test connection
            $this->get_connection()->getPdo();
            return true;
        } catch ( Exception $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Close the active database connection.
     *
     * Laravel manages connections via its connection pool; explicit closing is discouraged.
     * This method is a no-op for Laravel.
     *
     * @return void
     */
    public function close() {
        // No-op for Laravel
        // Laravel manages connections automatically
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function begin_transaction() {
        $this->get_connection()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() {
        $this->get_connection()->commit();
    }

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback() {
        $this->get_connection()->rollBack();
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  SQL query with placeholders (?).
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return mixed True on success for non-SELECT queries, or result for SELECT queries.
     */
    public function query( $query, array $params = [] ) {
        try {
            // Determine query type
            $query_type = strtoupper( substr( trim( $query ), 0, 6 ) );

            if ( $query_type === 'SELECT' ) {
                $result = $this->get_connection()->select( $query, $params );
                return $result;
            } elseif ( $query_type === 'INSERT' ) {
                $result = $this->get_connection()->insert( $query, $params );
                $this->insert_id = $this->get_connection()->getPdo()->lastInsertId();
                return $result;
            } elseif ( $query_type === 'UPDATE' ) {
                return $this->get_connection()->update( $query, $params );
            } elseif ( $query_type === 'DELETE' ) {
                return $this->get_connection()->delete( $query, $params );
            } else {
                return $this->get_connection()->statement( $query, $params );
            }
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Retrieve a single row as an associative array.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return array|null Associative array of the row, or null if not found.
     */
    public function get_row( $query, array $params = [] ) {
        try {
            $result = $this->get_connection()->selectOne( $query, $params );
            
            if ( $result === null ) {
                return null;
            }

            // Convert stdClass to associative array
            return (array) $result;
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    /**
     * Retrieve multiple rows as an array of associative arrays.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return array List of associative arrays representing result rows.
     */
    public function get_results( $query, array $params = [] ) {
        try {
            $results = $this->get_connection()->select( $query, $params );
            
            // Convert array of stdClass objects to array of associative arrays
            return array_map( function( $row ) {
                return (array) $row;
            }, $results );
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return [];
        }
    }

    /**
     * Retrieve a single scalar value.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return mixed|null The first column of the first row, or null if none.
     */
    public function get_var( $query, array $params = [] ) {
        try {
            $result = $this->get_connection()->selectOne( $query, $params );
            
            if ( $result === null ) {
                return null;
            }

            // Get the first property value from the object
            $values = array_values( (array) $result );
            return $values[0] ?? null;
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    /**
     * Retrieve a single column of values.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return array List of column values, or empty array if none found.
     */
    public function get_col( $query, array $params = [] ) {
        try {
            $results = $this->get_connection()->select( $query, $params );
            
            // Extract the first column from each row
            return array_map( function( $row ) {
                $values = array_values( (array) $row );
                return $values[0] ?? null;
            }, $results );
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return [];
        }
    }

    /**
     * Insert a record into the database.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     *
     * @return int|false The inserted record ID on success, false on failure.
     */
    public function insert( $table, array $data ) {
        try {
            // Add timestamps if not present
            if ( ! isset( $data['created_at'] ) ) {
                $data['created_at'] = now();
            }
            if ( ! isset( $data['updated_at'] ) ) {
                $data['updated_at'] = now();
            }

            $result = $this->get_connection()->table( $table )->insert( $data );
            
            if ( $result ) {
                $this->insert_id = $this->get_connection()->getPdo()->lastInsertId();
                return $this->insert_id;
            }

            return false;
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Update existing records.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     * @param array  $where Associative array for WHERE conditions.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function update( $table, array $data, array $where ) {
        try {
            if ( empty( $where ) ) {
                $this->last_error = 'WHERE clause cannot be empty for updates.';
                return false;
            }

            // Add updated_at timestamp if not present
            if ( ! isset( $data['updated_at'] ) ) {
                $data['updated_at'] = now();
            }

            $query = $this->get_connection()->table( $table );
            
            // Apply WHERE conditions
            foreach ( $where as $column => $value ) {
                $query->where( $column, '=', $value );
            }

            $affected = $query->update( $data );
            
            return $affected;
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete records from the database.
     *
     * @param string $table Table name.
     * @param array  $where Associative array for WHERE conditions.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function delete( $table, array $where ) {
        try {
            if ( empty( $where ) ) {
                $this->last_error = 'WHERE clause cannot be empty for deletes.';
                return false;
            }

            $query = $this->get_connection()->table( $table );
            
            // Apply WHERE conditions
            foreach ( $where as $column => $value ) {
                $query->where( $column, '=', $value );
            }

            $affected = $query->delete();
            
            return $affected;
        } catch ( QueryException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Retrieve the last inserted ID.
     *
     * @return int|null Last inserted ID, or null if not available.
     */
    public function get_insert_id() {
        return $this->insert_id;
    }

    /**
     * Retrieve the last database error message.
     *
     * @return string|null Last error message, or null if none.
     */
    public function get_last_error() {
        return $this->last_error;
    }
}