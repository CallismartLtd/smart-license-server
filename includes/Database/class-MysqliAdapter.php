<?php
/**
 * MySQLi Database Adapter
 *
 * Implements the DatabaseAdapterInterface for environments using the mysqli extension.
 *
 * @package SmartLicenseServer\Database
 */

namespace SmartLicenseServer\Database;

use mysqli;
use mysqli_stmt;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Adapter for MySQLi database access.
 */
class MysqliAdapter implements DatabaseAdapterInterface {

    /**
     * The MySQLi connection instance.
     *
     * @var mysqli|null
     */
    protected $mysqli;

    /**
     * Configuration settings for the connection.
     *
     * @var array
     */
    protected $config;

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
     * Constructor.
     *
     * @param array $config Database connection configuration.
     */
    public function __construct( array $config ) {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Establish a database connection.
     *
     * @return bool True on success, false on failure.
     */
    public function connect() {
        if ( $this->mysqli ) {
            return true;
        }
        
        // Suppress connection error output
        $mysqli = @new mysqli(
            $this->config['host'] ?? 'localhost',
            $this->config['username'] ?? 'root',
            $this->config['password'] ?? '',
            $this->config['database'] ?? ''
        );

        if ( $mysqli->connect_error ) {
            $this->last_error = 'Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
            return false;
        }

        $this->mysqli = $mysqli;
        $this->mysqli->set_charset( $this->config['charset'] ?? 'utf8mb4' );
        return true;
    }

    /**
     * Close the active database connection.
     *
     * @return void
     */
    public function close() {
        if ( $this->mysqli ) {
            $this->mysqli->close();
        }
        $this->mysqli = null;
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function begin_transaction() {
        if ( $this->mysqli ) {
            $this->mysqli->begin_transaction();
        }
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() {
        if ( $this->mysqli ) {
            $this->mysqli->commit();
        }
    }

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback() {
        if ( $this->mysqli ) {
            $this->mysqli->rollback();
        }
    }

    /**
     * Execute a parameterized query with ? placeholders.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Values to bind in the query.
     * @return mysqli_stmt|false Prepared statement on success, false on failure.
     */
    public function query( $query, $params = [] ) {
        if ( ! $this->mysqli ) {
            $this->last_error = 'No active MySQLi connection.';
            return false;
        }

        $stmt = $this->mysqli->prepare( $query );

        if ( ! $stmt ) {
            $this->last_error = 'Prepare failed: ' . $this->mysqli->error;
            return false;
        }

        if ( ! empty( $params ) ) {
            $types = $this->get_bind_types( $params );
            
            if ( ! $stmt->bind_param( $types, ...$params ) ) {
                $this->last_error = 'Bind param failed: ' . $stmt->error;
                $stmt->close();
                return false;
            }
        }

        if ( ! $stmt->execute() ) {
            $this->last_error = 'Execute failed: ' . $stmt->error;
            $stmt->close();
            return false;
        }

        // Store insert ID for INSERT queries
        if ( $this->mysqli->insert_id > 0 ) {
            $this->insert_id = $this->mysqli->insert_id;
        }

        return $stmt;
    }

    /**
     * Generate bind_param type string from parameter values.
     * 
     * @param array $params Array of parameters.
     * @return string String of types (i = integer, d = double, s = string, b = blob).
     */
    protected function get_bind_types( array $params ) {
        $types = '';
        
        foreach ( $params as $param ) {
            if ( is_int( $param ) ) {
                $types .= 'i';
            } elseif ( is_float( $param ) ) {
                $types .= 'd';
            } elseif ( is_string( $param ) ) {
                $types .= 's';
            } else {
                $types .= 's'; // Default to string for other types
            }
        }
        
        return $types;
    }

    /**
     * Retrieve a single row as an associative array.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Values to bind in the query.
     * @return array|null Associative array of the row, or null if not found.
     */
    public function get_row( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        
        if ( false === $stmt ) {
            return null;
        }

        $result = $stmt->get_result();
        
        if ( ! $result ) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ?: null;
    }

    /**
     * Retrieve multiple rows from the database as an associative array.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Values to bind to query placeholders.
     *
     * @return array Array of results (each row as an associative array).
     */
    public function get_results( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        
        if ( false === $stmt ) {
            return [];
        }

        $result = $stmt->get_result();
        
        if ( ! $result ) {
            $stmt->close();
            return [];
        }

        $rows = [];
        while ( $row = $result->fetch_assoc() ) {
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }

    /**
     * Fetch a single value from the database.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Values to bind.
     * @return mixed|null The first column of the first row or null if not found.
     */
    public function get_var( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        
        if ( false === $stmt ) {
            return null;
        }

        $result = $stmt->get_result();
        
        if ( ! $result ) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_array( MYSQLI_NUM );
        $stmt->close();
        
        return $row ? $row[0] : null;
    }

    /**
     * Fetch a single column from the database.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Values to bind.
     * @return array Array of column values.
     */
    public function get_col( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        
        if ( false === $stmt ) {
            return [];
        }

        $result = $stmt->get_result();
        
        if ( ! $result ) {
            $stmt->close();
            return [];
        }

        $column = [];
        while ( $row = $result->fetch_array( MYSQLI_NUM ) ) {
            $column[] = $row[0];
        }

        $stmt->close();
        return $column;
    }

    /**
     * Insert a record into the database.
     * 
     * @param string $table The table name.
     * @param array  $data  An associative array of column_name => value.
     * @return int|false The inserted record ID on success, false on failure.
     */
    public function insert( $table, $data ) {
        if ( empty( $data ) ) {
            $this->last_error = 'Insert data cannot be empty.';
            return false;
        }

        $columns = array_keys( $data );
        $placeholders = array_fill( 0, count( $data ), '?' );

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode( ', ', $columns ),
            implode( ', ', $placeholders )
        );

        $params = array_values( $data );
        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return false;
        }

        $stmt->close();
        return $this->get_insert_id();
    }

    /**
     * Update records in the database.
     * 
     * @param string $table The table name.
     * @param array  $data  An associative array of column_name => value.
     * @param array  $where An associative array of column_name => value for WHERE clause.
     * @return int|false Number of affected rows on success, false on failure.
     */
    public function update( $table, $data, $where ) {
        if ( empty( $data ) || empty( $where ) ) {
            $this->last_error = 'Update data and WHERE condition cannot be empty.';
            return false;
        }

        // Build SET clause with ? placeholders
        $set_clauses = array_map( function( $column ) {
            return "$column = ?";
        }, array_keys( $data ) );

        // Build WHERE clause with ? placeholders
        $where_clauses = array_map( function( $column ) {
            return "$column = ?";
        }, array_keys( $where ) );

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode( ', ', $set_clauses ),
            implode( ' AND ', $where_clauses )
        );

        // Merge data and where values for positional binding
        $params = array_merge( array_values( $data ), array_values( $where ) );
        
        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return false;
        }

        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows;
    }

    /**
     * Delete records from the database.
     * 
     * @param string $table The table name.
     * @param array  $where An associative array of column_name => value for WHERE clause.
     * @return int|false Number of affected rows on success, false on failure.
     */
    public function delete( $table, $where ) {
        if ( empty( $where ) ) {
            $this->last_error = 'Delete WHERE condition cannot be empty.';
            return false;
        }

        // Build WHERE clause with ? placeholders
        $where_clauses = array_map( function( $column ) {
            return "$column = ?";
        }, array_keys( $where ) );

        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode( ' AND ', $where_clauses )
        );

        $params = array_values( $where );
        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return false;
        }

        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows;
    }

    /**
     * Retrieve the last inserted ID.
     *
     * @return int|null The last inserted ID, or null if not available.
     */
    public function get_insert_id() {
        return $this->insert_id;
    }

    /**
     * Retrieve the last database error.
     *
     * @return string|null The last error message, or null if none.
     */
    public function get_last_error() {
        return $this->last_error;
    }
}