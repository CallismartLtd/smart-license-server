<?php
/**
 * SQLite3 Database Adapter (Native)
 *
 * Implements the DatabaseAdapterInterface using the native SQLite3 PHP extension.
 *
 * @package SmartLicenseServer\Database\Adapters
 */

namespace SmartLicenseServer\Database\Adapters;

use SQLite3;
use Exception;
use SmartLicenseServer\Database\DBConfigDTO;
use SmartLicenseServer\Database\SqliteCompatibilityTrait;
use SQLite3Result;

/**
 * Adapter for native SQLite3 database access.
 */
class SqliteAdapter implements DatabaseAdapterInterface {
    use SqliteCompatibilityTrait;

    /**
     * The SQLite3 connection instance.
     *
     * @var SQLite3|null
     */
    protected $sqlite;

    /**
     * Configuration settings.
     *
     * @var DBConfigDTO
     */
    protected DBConfigDTO $config;

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
     * @param DBConfigDTO $config Database configuration including 'database' path and optional 'flags'.
     */
    public function __construct( DBConfigDTO $config ) {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Destructor.
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Establish a database connection using the SQLite3 class.
     *
     * @return bool True on success, false on failure.
     */
    protected function connect() {
        if ( $this->sqlite ) {
            return true;
        }

        try {
            $path       = $this->config->path;

            $flags      = $this->config->flags ?? ( SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE );
            $encryption = $this->config->encryption_key ?? '';

            $this->sqlite = new SQLite3( $path, (int) $flags, $encryption );
            $this->sqlite->exec( 'PRAGMA journal_mode=WAL;' );
            $this->sqlite->exec( 'PRAGMA synchronous=NORMAL;' );
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
    protected function close() : void {
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
    public function begin_transaction() : void {
        $this->sqlite->exec( 'BEGIN TRANSACTION' );
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() : void {
        $this->sqlite->exec( 'COMMIT' );
    }

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback() : void {
        $this->sqlite->exec( 'ROLLBACK' );
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  The SQL query with ? placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return SQLite3Result|false The result object or true on success, false on failure.
     */
    protected function query( $query, array $params = [] ) : SQLite3Result|false {
        if ( ! $this->ensure_connection() ) {
            return false;
        }

        $query  = $this->translate_mysql_to_sqlite( $query );
        $stmt   = @$this->sqlite->prepare( $query );
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

        // Store insert ID if this was an INSERT operation.
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
    protected function get_sqlite_type( $value ) : int {
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
    public function get_row( $query, array $params = [] ) : ?array {
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
    public function get_results( $query, array $params = [] ) : array {
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
    public function get_var( $query, array $params = [] ) : mixed {
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
    public function get_col( $query, array $params = [] ) : array {
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
    public function insert( $table, array $data ) : int|false {
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
    public function update( $table, array $data, array $where ) : int|false {
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
    public function delete( $table, array $where ) : int|false {
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
    public function get_insert_id() : ?int {
        return $this->insert_id;
    }

    /**
     * Retrieve the last database error message.
     *
     * @return string|null The error message, or null if none.
     */
    public function get_last_error() : ?string {
        return $this->last_error;
    }

    /**
     * Get the SQLite library version.
     *
     * @return string Version string (e.g., "3.34.0").
     */
    public function get_server_version() : string {
        $version = SQLite3::version();
        return $version['versionString'] ?? '0.0.0';
    }

    /**
     * Get the database engine name.
     *
     * @return string 'sqlite'.
     */
    public function get_engine_type() : string {
        return 'sqlite';
    }

    /**
     * {@inheritdoc}
     *
     * @return string The path to the database file or ':memory:'.
     */
    public function get_host_info()  : string {
        return 'SQLite3 Native: ' . ($this->config['database'] ?? ':memory:');
    }

    /**
     * {@inheritdoc}
     */
    public function get_protocol_version() : string {
        return 'N/A (File-based)';
    }

    /**
     * {@inheritdoc}
     */
    public function exec( string $query ) : bool {
        if ( ! $this->ensure_connection() ) {
            return false;
        }

        try {
            $query = $this->translate_mysql_to_sqlite( $query );

            /**
             * SQLite returns:
             * - result set object for SELECT-like queries
             * - TRUE/FALSE for exec()
             */

            $result = @$this->sqlite->exec( $query );

            if ( false === $result ) {
                $this->last_error = $this->sqlite->lastErrorMsg();
                return false;
            }

            $this->insert_id    = $this->sqlite->lastInsertRowID();

            return true;

        } catch ( \Exception $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute( string $query, array $params = [] ) : int {
        if ( ! $this->ensure_connection() ) {
            return 0;
        }

        $result = $this->query( $query, $params );
        
        if ( ! $result ) {
            return 0;
        }

        $affected_rows = $this->sqlite->changes();
        $result->finalize();

        return $affected_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function get_all_tables() : array {
        $query  = "SELECT name FROM sqlite_master  WHERE type = 'table'
            AND name NOT LIKE 'sqlite_%'";
        $results    = $this->sqlite->query( $query );

        if ( false === $results ) {
            return [];
        }

        $table_names    = [];

        while( $result = $results->fetchArray( \SQLITE3_ASSOC ) ) {
            $table_names[] = $result['name'];
        }

        return $table_names;
    }

    /**
     * Check if a table exists.
     */
    public function table_exists( string $table ): bool {
        if ( ! $this->ensure_connection() ) return false;

        $query = "SELECT name FROM sqlite_master WHERE type='table' AND name = ?";
        return null !== $this->get_var( $query, [$table] );
    }

    /**
     * Check if a column exists in a table.
     */
    public function column_exists( string $table, string $column ): bool {
        if ( ! $this->ensure_connection() ) return false;

        $query = "PRAGMA table_info($table)";
        $columns = $this->get_results( $query );

        foreach ( $columns as $col ) {
            if ( ( $col['name'] ?? null ) === $column ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get column type.
     */
    public function get_column_type( string $table, string $column ): ?string {
        if ( ! $this->ensure_connection() ) return null;

        $query = "PRAGMA table_info($table)";
        $columns = $this->get_results( $query );

        foreach ( $columns as $col ) {
            if ( ( $col['name'] ?? null ) === $column ) {
                return $col['type'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get all columns in a table.
     */
    public function get_columns( string $table ): array {
        if ( ! $this->ensure_connection() ) return [];

        $query = "PRAGMA table_info($table)";
        $columns = $this->get_results( $query );

        return array_map( fn( $col ) => $col['name'], $columns );
    }

    /**
     * Check connection state.
     */
    public function is_connected(): bool {
        return $this->sqlite instanceof \SQLite3;
    }

    protected function ensure_connection() : bool {
        if ( $this->is_connected() ) {
            return true;
        }

        $this->connect();

        if ( ! $this->sqlite ) {
            $this->last_error = 'No active SQLite3 connection.';
            return false;
        }

        return true;
    }
}