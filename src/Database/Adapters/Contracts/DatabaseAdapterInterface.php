<?php
/**
 * Database Adapter interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database
 */

namespace SmartLicenseServer\Database\Adapters\Contracts;

use SmartLicenseServer\Database\DBConfigDTO;

/**
 * Database adapter contracts.
 * 
 * Provides a unified API to execute database queries regardless of the
 * database engine.
 */
interface DatabaseAdapterInterface {

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
     * Execute a parameterized query and return the number of affected rows.
     *
     * Use this for UPDATE, DELETE, or REPLACE queries where you need
     * to know how many records were modified.
     *
     * @param string $query  The SQL query with placeholders.
     * @param array  $params The bound values for placeholders.
     *
     * @return int The number of affected rows.
     */
    public function execute( string $query, array $params = [] ): int;

    /**
     * Execute a raw SQL query without prepared statements.
     *
     * ⚠️ UNSAFE: Bypasses parameter binding. Do not use with untrusted input.
     *
     * Intended for:
     * - schema inspection (SHOW / DESCRIBE)
     * - migrations / admin tooling
     * - engine-specific queries
     *
     * @param string $query
     * @return bool
     */
    public function exec(string $query) : bool;

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
     * Check whether the database connection is alive.
     *
     * @return bool
     */
    public function is_connected(): bool;

    /**
     * Get the database driver string.
     * 
     * @return string
     */
    public function get_driver() : string;

    /**
     * Get database configuration object.
     * 
     * @return DBConfigDTO
     */
    public function get_config() : DBConfigDTO;
}