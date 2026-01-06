<?php
/**
 * WordPress Database Adapter
 *
 * Implements the DatabaseAdapterInterface for WordPress environments
 * using the global $wpdb object.
 *
 * @package SmartLicenseServer\Database
 */

namespace SmartLicenseServer\Database;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Adapter for WordPress database access.
 *
 * This adapter provides a unified interface to WordPress's $wpdb,
 * allowing Smart License Server to operate in WP environments.
 */
class WPAdapter implements DatabaseAdapterInterface {

    /**
     * Global $wpdb instance.
     *
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * Last executed error message.
     *
     * @var string|null
     */
    protected $last_error = null;

    /**
     * Last inserted ID.
     *
     * @var int|null
     */
    protected $insert_id = null;

    /**
     * Constructor.
     *
     * Initializes the adapter with the global $wpdb instance.
     *
     * @param \wpdb|null $wpdb Optional. Custom wpdb instance. Defaults to global $wpdb.
     */
    public function __construct( $wpdb = null ) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
    }

    /**
     * Establish a database connection.
     *
     * For WordPress, the connection is already established via $wpdb.
     *
     * @return bool Always true.
     */
    public function connect() {
        return true;
    }

    /**
     * Close the active database connection.
     *
     * WordPress does not support explicit closing; this method is a no-op.
     *
     * @return void
     */
    public function close() {
        // No-op for WordPress
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function begin_transaction() {
        $this->wpdb->query( 'START TRANSACTION' );
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() {
        $this->wpdb->query( 'COMMIT' );
    }

    /**
     * Rollback the current transaction.
     *
     * @return void
     */
    public function rollback() {
        $this->wpdb->query( 'ROLLBACK' );
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return mixed The result from $wpdb (depends on query type), false on failure.
     */
    public function query( $query, array $params = [] ) {
        $prepared = $this->prepare( $query, $params );

        $result   = $this->wpdb->query( $prepared );

        if ( $result === false ) {
            $this->last_error = $this->wpdb->last_error;
        } else {
            $this->insert_id = $this->wpdb->insert_id;
        }

        return $result;
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
        $prepared = $this->prepare( $query, $params );

        $row      = $this->wpdb->get_row( $prepared, ARRAY_A );

        if ( null === $row && $this->wpdb->last_error ) {
            $this->last_error = $this->wpdb->last_error;
        }

        return $row;
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
        $prepared = $this->prepare( $query, $params );
        $rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

        if ( $rows === null && $this->wpdb->last_error ) {
            $this->last_error = $this->wpdb->last_error;
            return [];
        }

        return $rows ?? [];
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
        $prepared = $this->prepare( $query, $params );

        $var      = $this->wpdb->get_var( $prepared );

        if ( null === $var && $this->wpdb->last_error ) {
            $this->last_error = $this->wpdb->last_error;
        }

        return $var;
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
        $prepared = $this->prepare( $query, $params );
        $col      = $this->wpdb->get_col( $prepared );

        if ( $col === null && $this->wpdb->last_error ) {
            $this->last_error = $this->wpdb->last_error;
            return [];
        }

        return $col ?? [];
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
        $result = $this->wpdb->insert( $table, $data );

        if ( false === $result ) {
            $this->last_error = $this->wpdb->last_error;
            return false;
        }

        $this->insert_id = $this->wpdb->insert_id;

        return $this->insert_id;
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
        $result = $this->wpdb->update( $table, $data, $where );

        if ( false === $result ) {
            $this->last_error = $this->wpdb->last_error;
        }

        return $result;
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
        $result = $this->wpdb->delete( $table, $where );

        if ( false === $result ) {
            $this->last_error = $this->wpdb->last_error;
        }

        return $result;
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

    protected function prepare( string $query, array $params = [] ) : string {
        if ( empty( $params ) ) {
            return $query;
        }

        $i = 0;
        $formatted = preg_replace_callback( '/\?/', function( $matches ) use ( &$i, $params ) {
            $param = $params[ $i++ ];
            if ( is_int( $param ) ) return '%d';
            if ( is_float( $param ) ) return '%f';
            return '%s';
        }, $query );

        return $this->wpdb->prepare( $formatted, ...$params );
    }

    /**
     * Get the database server version.
     *
     * @return string
     */
    public function get_server_version() {
        return $this->wpdb->db_version();
    }

    /**
     * Get the database engine/driver name.
     *
     * @return string
     */
    public function get_engine_type() {
        // WordPress is historically MySQL-based.
        return 'mysql';
    }

    /**
     * Get information about the connection host.
     *
     * @return string
     */
    public function get_host_info() {
        // Returns the value of DB_HOST defined in wp-config.php
        return defined('DB_HOST') ? DB_HOST : 'unknown';
    }

    public function get_protocol_version() {
        return $this->get_var( "SELECT @@protocol_version" );
    }
}
