<?php
/**
 * Database schema inspection interface file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database
 */

namespace SmartLicenseServer\Database\Inspection\Contracts;

/**
 * Contracts for database inspection engines.
 * 
 * Provides an engine-agnostic database schema inspection API.
 */
interface InspectionInterface {

	/**
	 * Retrieve a list of all tables in the current database.
	 * 
	 * @return array List of table names.
	 */
	public function get_all_tables(): array;

	/**
	 * Check if a table exists.
	 *
	 * @param string $table Table name.
	 * @return bool True if the table exists.
	 */
	public function table_exists( string $table ): bool;

	/**
	 * Check if a column exists in a table.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return bool True if the column exists.
	 */
	public function column_exists( string $table, string $column ): bool;

	/**
	 * Get the type of a column.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return string|null Column type or null if not found.
	 */
	public function get_column_type( string $table, string $column ): ?string;

	/**
	 * Get all columns in a table.
	 *
	 * @param string $table Table name.
	 * @return array List of column names.
	 */
	public function get_columns( string $table ): array;

	/**
	 * Get detailed column information for a table.
	 *
	 * @param string $table Table name.
	 * @return array Associative array where keys are column names and values are
	 *               associative arrays with keys: 'type', 'nullable', 'default',
	 *               'auto_increment' (if applicable).
	 */
	public function get_column_details( string $table ): array;

	/**
	 * Check if an index exists on the table.
	 *
	 * @param string $table The table name
	 * @param string $index_name The index name
	 * @return bool True if index exists
	 */
	public function has_index( string $table, string $index_name ): bool;

	/**
	 * Get all indexes for a table.
	 *
	 * @param string $table Table name.
	 * @return array Associative array where keys are index names and values
	 *               are associative arrays with 'columns' (array of column names)
	 *               and 'unique' (bool).
	 */
	public function get_indexes( string $table ): array;

	/**
	 * Get the primary key column(s) for a table.
	 *
	 * @param string $table Table name.
	 * @return array|null Array of column names that form the primary key,
	 *                    or null if no primary key exists.
	 */
	public function get_primary_key( string $table ): ?array;

	/**
	 * Get all foreign key constraints for a table.
	 *
	 * @param string $table Table name.
	 * @return array Associative array where keys are constraint names and values
	 *               are associative arrays with: 'columns' (array of local columns),
	 *               'referenced_table', 'referenced_columns' (array).
	 */
	public function get_foreign_keys( string $table ): array;

	/**
	 * Check if a foreign key constraint exists.
	 *
	 * @param string $table      Table name.
	 * @param string $constraint Constraint name.
	 * @return bool True if the foreign key constraint exists.
	 */
	public function has_foreign_key( string $table, string $constraint ): bool;

	/**
	 * Get all unique constraints (excluding primary key) for a table.
	 *
	 * @param string $table Table name.
	 * @return array Associative array where keys are constraint names and values
	 *               are arrays of column names.
	 */
	public function get_unique_constraints( string $table ): array;

	/**
	 * Get all check constraints for a table.
	 *
	 * @param string $table Table name.
	 * @return array Associative array where keys are constraint names and values
	 *               are associative arrays with 'definition' (the constraint expression).
	 */
	public function get_check_constraints( string $table ): array;

	/**
	 * Get metadata about a table.
	 *
	 * @param string $table Table name.
	 * @return array Associative array with: 'engine' (if applicable, e.g., InnoDB),
	 *               'charset' (if applicable), 'collation' (if applicable),
	 *               'row_count' (approximate), 'comment'.
	 */
	public function get_table_metadata( string $table ): array;

	/**
	 * Check if a column is nullable.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return bool|null True if nullable, false if not null, null if column not found.
	 */
	public function is_column_nullable( string $table, string $column ): ?bool;

	/**
	 * Get the default value for a column.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return mixed The default value, or null if none is set or column not found.
	 */
	public function get_column_default( string $table, string $column );

	/**
	 * Get information about the connection host.
	 *
	 * @return string Information like host IP or connection method (TCP/IP, Socket).
	 */
	public function get_host_info(): string;

	/**
	 * Retrieve the database protocol version.
	 *
	 * @return string|int|null The protocol version, or null if not applicable/available.
	 */
	public function get_protocol_version();

	/**
	 * Get the database server version.
	 *
	 * @return string The server version (e.g., "8.0.32", "15.1").
	 */
	public function get_server_version(): string;

	/**
	 * Get the database engine/driver name.
	 *
	 * @return string Lowercase name of the engine (e.g., "mysql", "pgsql", "sqlite").
	 */
	public function get_engine_type(): string;
}