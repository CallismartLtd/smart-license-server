<?php
/**
 * SQLite3 Database Adapter (Native)
 *
 * Implements the DatabaseAdapterInterface using the native SQLite3 PHP extension.
 *
 * @package SmartLicenseServer\Database
 */

namespace SmartLicenseServer\Database;

use SQLite3;
use Exception;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Adapter for native SQLite3 database access.
 */
class SqliteAdapter implements DatabaseAdapterInterface {

    /**
     * The SQLite3 connection instance.
     *
     * @var SQLite3|null
     */
    protected $sqlite;

    /**
     * Configuration settings.
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
     * @param array $config Database configuration including 'database' path and optional 'flags'.
     */
    public function __construct( array $config ) {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Establish a database connection using the SQLite3 class.
     *
     * @return bool True on success, false on failure.
     */
    public function connect() {
        if ( $this->sqlite ) {
            return true;
        }

        try {
            $path       = $this->config['database'] ?? ':memory:';
            $flags      = $this->config['flags'] ?? (SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $encryption = $this->config['encryption_key'] ?? '';
            
            $this->sqlite = new SQLite3( $path, $flags, $encryption );

            return true;
        } catch ( Exception $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Close the active SQLite3 database connection.
     *
     * @return void
     */
    public function close() {
        if ( $this->sqlite ) {
            $this->sqlite->close();
        }
        $this->sqlite = null;
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function begin_transaction() {
        $this->sqlite->exec( 'BEGIN TRANSACTION' );
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() {
        $this->sqlite->exec( 'COMMIT' );
    }

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback() {
        $this->sqlite->exec( 'ROLLBACK' );
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  The SQL query with ? placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return \SQLite3Result|bool The result object or true on success, false on failure.
     */
    public function query( $query, array $params = [] ) {
        if ( ! $this->sqlite ) {
            $this->last_error = 'No active SQLite3 connection.';
            return false;
        }

        $stmt = $this->sqlite->prepare( $query );
        if ( ! $stmt ) {
            $this->last_error = $this->sqlite->lastErrorMsg();
            return false;
        }

        if ( ! empty( $params ) ) {
            foreach ( $params as $i => $value ) {
                $stmt->bindValue( $i + 1, $value, $this->get_sqlite_type( $value ) );
            }
        }

        $result = $stmt->execute();
        
        if ( ! $result ) {
            $this->last_error = $this->sqlite->lastErrorMsg();
            return false;
        }

        // Store insert ID if this was an INSERT operation
        if ( stripos( trim( $query ), 'INSERT' ) === 0 ) {
            $this->insert_id = $this->sqlite->lastInsertRowID();
        }

        return $result;
    }

    /**
     * Determine the correct SQLITE3 type constant for a PHP value.
     *
     * @param mixed $value The value to check.
     * @return int SQLITE3 constant (INTEGER, FLOAT, TEXT, NULL, or BLOB).
     */
    protected function get_sqlite_type( $value ) {
        if ( is_int( $value ) ) return SQLITE3_INTEGER;
        if ( is_float( $value ) ) return SQLITE3_FLOAT;
        if ( is_null( $value ) ) return SQLITE3_NULL;
        if ( is_bool( $value ) ) return SQLITE3_INTEGER;
        if ( is_resource( $value ) ) return SQLITE3_BLOB;
        return SQLITE3_TEXT;
    }

    /**
     * Retrieve a single row as an associative array.
     *
     * @param string $query  The SQL query with ? placeholders.
     * @param array  $params Optional. Bound values.
     * @return array|null Associative array of the row, or null if not found.
     */
    public function get_row( $query, array $params = [] ) {
        $result = $this->query( $query, $params );
        if ( ! $result ) return null;
        
        $row = $result->fetchArray( SQLITE3_ASSOC );
        return $row ?: null;
    }

    /**
     * Retrieve multiple rows as an array of associative arrays.
     *
     * @param string $query  The SQL query with ? placeholders.
     * @param array  $params Optional. Bound values.
     * @return array List of associative arrays.
     */
    public function get_results( $query, array $params = [] ) {
        $result = $this->query( $query, $params );
        if ( ! $result ) return [];

        $rows = [];
        while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Retrieve a single scalar value.
     *
     * @param string $query  The SQL query with ? placeholders.
     * @param array  $params Optional. Bound values.
     * @return mixed|null The first column of the first row, or null if none.
     */
    public function get_var( $query, array $params = [] ) {
        $result = $this->query( $query, $params );
        if ( ! $result ) return null;

        $row = $result->fetchArray( SQLITE3_NUM );
        return $row ? $row[0] : null;
    }

    /**
     * Retrieve a single column of values.
     *
     * @param string $query  The SQL query with ? placeholders.
     * @param array  $params Optional. Bound values.
     * @return array List of column values.
     */
    public function get_col( $query, array $params = [] ) {
        $result = $this->query( $query, $params );
        if ( ! $result ) return [];

        $cols = [];
        while ( $row = $result->fetchArray( SQLITE3_NUM ) ) {
            $cols[] = $row[0];
        }
        return $cols;
    }

    /**
     * Insert a record into the database.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     * @return int|false The inserted record ID on success, false on failure.
     */
    public function insert( $table, array $data ) {
        $columns = array_keys( $data );
        $placeholders = array_fill( 0, count( $data ), '?' );

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode( ', ', $columns ),
            implode( ', ', $placeholders )
        );

        $success = $this->query( $query, array_values( $data ) );
        return $success ? $this->get_insert_id() : false;
    }

    /**
     * Update existing records.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     * @param array  $where Associative array for WHERE conditions.
     * @return int|false Number of affected rows, or false on failure.
     */
    public function update( $table, array $data, array $where ) {
        $set_clauses = array_map( fn($col) => "$col = ?", array_keys( $data ) );
        $where_clauses = array_map( fn($col) => "$col = ?", array_keys( $where ) );

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode( ', ', $set_clauses ),
            implode( ' AND ', $where_clauses )
        );

        $params = array_merge( array_values( $data ), array_values( $where ) );
        $success = $this->query( $query, $params );
        
        return $success ? $this->sqlite->changes() : false;
    }

    /**
     * Delete records from the database.
     *
     * @param string $table Table name.
     * @param array  $where Associative array for WHERE conditions.
     * @return int|false Number of affected rows, or false on failure.
     */
    public function delete( $table, array $where ) {
        $where_clauses = array_map( fn($col) => "$col = ?", array_keys( $where ) );

        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode( ' AND ', $where_clauses )
        );

        $success = $this->query( $query, array_values( $where ) );
        return $success ? $this->sqlite->changes() : false;
    }

    /**
     * Retrieve the last inserted ID.
     *
     * @return int|null The last inserted row ID.
     */
    public function get_insert_id() {
        return $this->insert_id;
    }

    /**
     * Retrieve the last database error message.
     *
     * @return string|null The error message, or null if none.
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Get the SQLite library version.
     *
     * @return string Version string (e.g., "3.34.0").
     */
    public function get_server_version() {
        $version = SQLite3::version();
        return $version['versionString'] ?? '0.0.0';
    }

    /**
     * Get the database engine name.
     *
     * @return string 'sqlite'.
     */
    public function get_engine_type() {
        return 'sqlite';
    }

    /**
     * Get connection host information.
     *
     * @return string The path to the database file or ':memory:'.
     */
    public function get_host_info() {
        return 'SQLite3 Native: ' . ($this->config['database'] ?? ':memory:');
    }

    public function get_protocol_version() {
        return 'N/A (File-based)';
    }
}