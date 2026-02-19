<?php
/**
 * Interface file for database adapter contracts.
 *
 * Defines a standard database interaction API that all
 * environment-specific adapters (WordPress, Laravel, native PHP, etc.)
 * must implement for the Smart License Server plugin.
 *
 * @package SmartLicenseServer\Interfaces
 */

namespace SmartLicenseServer\Database;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Database Adapter Interface
 *
 * This interface provides a unified contract for database access across
 * different PHP environments.
 */
interface DatabaseAdapterInterface {

    /**
     * Establish a database connection.
     *
     * @return bool True on success, false on failure.
     */
    public function connect();

    /**
     * Close the active database connection.
     *
     * @return void
     */
    public function close();

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function begin_transaction();

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit();

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback();

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  The SQL query with placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return mixed The native statement/result object, or false on failure.
     */
    public function query( $query, array $params = [] );

    /**
     * Retrieve a single row as an associative array.
     *
     * @param string $query  The SQL query with placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return array|null Associative array of the row, or null if not found.
     */
    public function get_row( $query, array $params = [] );

    /**
     * Retrieve multiple rows as an array of associative arrays.
     *
     * @param string $query  The SQL query with placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return array List of associative arrays representing result rows.
     */
    public function get_results( $query, array $params = [] );

    /**
     * Retrieve a single scalar value.
     *
     * @param string $query  The SQL query with placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return mixed|null The first column of the first row, or null if none.
     */
    public function get_var( $query, array $params = [] );

    /**
     * Retrieve a single column of values.
     *
     * @param string $query  The SQL query with placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return array List of column values, or empty array if none found.
     */
    public function get_col( $query, array $params = [] );

    /**
     * Insert a record into the database.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     *
     * @return int|false The inserted record ID on success, false on failure.
     */
    public function insert( $table, array $data );

    /**
     * Update existing records.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     * @param array  $where Associative array for WHERE conditions.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function update( $table, array $data, array $where );

    /**
     * Delete records from the database.
     *
     * @param string $table Table name.
     * @param array  $where Associative array for WHERE conditions.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function delete( $table, array $where );

    /**
     * Retrieve the last inserted ID.
     *
     * @return int|null The last inserted ID, or null if not available.
     */
    public function get_insert_id();

    /**
     * Retrieve the last database error.
     *
     * @return string|null The last error message, or null if none.
     */
    public function get_last_error();

    /**
     * Get the database server version.
     *
     * @return string The server version (e.g., "8.0.32", "15.1").
     */
    public function get_server_version();

    /**
     * Get the database engine/driver name.
     *
     * @return string Lowercase name of the engine (e.g., "mysql", "pgsql", "sqlite").
     */
    public function get_engine_type();

    /**
     * Get information about the connection host.
     *
     * @return string Information like host IP or connection method (TCP/IP, Socket).
     */
    public function get_host_info();

    /**
     * Retrieve the database protocol version.
     *
     * @return string|int|null The protocol version, or null if not applicable/available.
     */
    public function get_protocol_version();
}
