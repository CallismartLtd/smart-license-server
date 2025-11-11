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
use Exception;

defined( 'SMLISER_PATH' ) || exit;

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
        $this->mysqli->begin_transaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() {
        $this->mysqli->commit();
    }

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback() {
        $this->mysqli->rollback();
    }

    /**
     * Execute a parameterized query (SELECT, INSERT, UPDATE, DELETE).
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Values to bind in the query.
     * @return mysqli_stmt   Prepared statement on success.
     * @throws Exception     When query preparation or execution fails.
     */
    public function query( $query, $params = [] ) {
        $stmt = $this->mysqli->prepare( $query );

        if ( ! $stmt ) {
            $this->last_error = 'Database prepare failed: ' . $this->mysqli->error;
            throw new Exception( $this->last_error );
        }

        if ( ! empty( $params ) ) {
            $types = $this->bind_param_types( $params );
            $stmt->bind_param( $types, ...$params );
        }

        if ( ! $stmt->execute() ) {
            $this->last_error = 'Statement execute failed: ' . $stmt->error;
            throw new Exception( $this->last_error );
        }

        return $stmt;
    }


    /**
     * Append the corresponding value for the parameters.
     * 
     * @param array $params
     * @return string String type for binding (s = string, i = integer, d = double)
     */
    private function bind_param_types( $params ) {
        $types = '';
    
        foreach ( $params as $param ) {
            if ( is_int( $param ) ) {
                $types .= 'i'; // Integer
            } elseif ( is_float( $param ) ) {
                $types .= 'd'; // Double (float)
            } elseif ( is_string( $param ) ) {
                $types .= 's'; // String
            } else {
                $types .= 'b'; // Blob (or unknown type)
            }
        }
    
        return $types;
    }
    

    /**
     * Retrieve a single row as an associative array.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Values to bind in the query.
     * @return array|null|false Associative array of the row, null if no result, false on failure.
     */
    public function get_row( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        return $stmt ? ( $stmt->get_result()->fetch_assoc() ?: null ) : false;
    }


    /**
     * Fetch a single column from the database.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Values to bind.
     * @return array|false Array of column values or false on failure.
     */
    public function get_col( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        if ( $stmt ) {
            $result = $stmt->get_result();
            $column = [];
            while ( $row = $result->fetch_array( MYSQLI_NUM ) ) {
                $column[] = $row[0];
            }
            return $column;
        }
        return false;
    }

    /**
     * Fetch a single value from the database.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Values to bind.
     * @return mixed The first column of the first row or false on failure.
     */
    public function get_var( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        if ( $stmt ) {
            $result = $stmt->get_result();
            $row = $result->fetch_array( MYSQLI_NUM );
            return $row ? $row[0] : null;
        }
        return false;
    }

    /**
     * Retrieve multiple rows from the database as an associative array.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Values to bind to query placeholders.
     *
     * @return array|false Array of results (each row as an associative array) or false on failure.
     */
    public function get_results( $query, $params = [] ) {
        $stmt = $this->query( $query, $params );
        if ( ! $stmt ) {
            return false;
        }

        $result = $stmt->get_result();
        $rows = [];
        while ( $row = $result->fetch_assoc() ) {
            $rows[] = $row;
        }

        return $rows;
    }


    /**
     * Insert a record into the database.
     * 
     * @param string $table The table name.
     * @param array $data An associative array of column_name => value.
     */
    public function insert( $table, $data ) {
        try {
            $this->begin_transaction();

            $columns = implode( ', ', array_keys( $data ) );
            $placeholders = implode( ', ', array_fill( 0, count( $data ), '?' ) );
            $query = "INSERT INTO $table ( $columns ) VALUES ( $placeholders )";

            $params = array_values( $data );

            $stmt = $this->query( $query, $params );
            if ( ! $stmt ) {
                throw new Exception( "Insert failed: " . $this->last_error );
            }

            $this->insert_id = $this->mysqli->insert_id; 

            $this->commit();
            
            return $this->insert_id;

        } catch ( Exception $e ) {
            $this->rollback();
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Update records in the database.
     * 
     * @param string $table The table name.
     * @param array $data An associative array of column_name => value.
     * @param array $where An associative array of the column_name => value Where this update should happen.
     */
    public function update( $table, $data, $where ) {
        try {
            $this->begin_transaction();

            $set_clause = implode( ', ', array_map( fn( $key ) => "$key = ?", array_keys( $data ) ) );
            $where_clause = implode( ' AND ', array_map( fn( $key ) => "$key = ?", array_keys( $where ) ) );

            $query = "UPDATE $table SET $set_clause WHERE $where_clause";

            $params = array_merge( array_values( $data ), array_values( $where ) );

            $stmt = $this->query( $query, $params );
            if ( ! $stmt ) {
                throw new Exception( "Update failed: " . $this->last_error );
            }

            $affected_rows = $stmt->affected_rows;
            $this->commit();
            return $affected_rows;
        } catch ( Exception $e ) {
            $this->rollback();
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete records from the database.
     * 
     * @param string $table The table name.
     * @param array $where an associative array of column_name => value where this entry should be deleted.
     */
    public function delete( $table, $where ) {
        try {
            $this->begin_transaction();

            $where_clause = implode( ' AND ', array_map( fn( $key ) => "$key = ?", array_keys( $where ) ) );
            $query = "DELETE FROM $table WHERE $where_clause";

            $params = array_values( $where );

            $stmt = $this->query( $query, $params );
            if ( ! $stmt ) {
                throw new Exception( "Delete failed: " . $this->last_error );
            }

            $this->commit();
            return $stmt->affected_rows;
        } catch ( Exception $e ) {
            $this->rollback();
            $this->last_error = $e->getMessage();

            return false;
        }
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