<?php
/**
 * Schema Inspector for Table Introspection
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Inspection;

use SmartLicenseServer\Database\Database;

/**
 * Provides fluent interface for querying table structure.
 *
 * Handles:
 * - Checking if table/column exists
 * - Getting column types
 * - Getting table engine, charset, collation
 * - Getting all columns
 * - Checking constraints
 *
 * @since 0.2.0
 */
class SchemaInspector {

    /**
     * The database abstraction API instance.
     *
     * @var Database
     */
    private $database;

    /**
     * Table name.
     *
     * @var string
     */
    private $table;

    /**
     * Constructor.
     *
     * @param Database $database The database instance to use for queries
     * @param string $table The table name to inspect
     */
    public function __construct( Database $database, string $table ) {
        $this->database = $database;
        $this->table = $table;
    }

    /**
     * Check if the table exists.
     *
     * @return bool True if table exists
     *
     */
    public function tableExists() : bool {
        return $this->database->table_exists( $this->table );
    }

    /**
     * Check if a column exists in the table.
     *
     * @param string $column The column name
     *
     * @return bool True if column exists
     *
     */
    public function columnExists( string $column ) : bool {
        return $this->database->column_exists( $this->table, $column );
    }

    /**
     * Get the type of a column.
     *
     * @param string $column The column name
     *
     * @return string|null The column type (e.g., 'VARCHAR(30)', 'BIGINT'), or null if not found
     */
    public function columnType( string $column ) : ?string {
        return $this->database->get_column_type( $this->table, $column );
    }

    /**
     * Get all column names in the table.
     *
     * @return array Array of column names in order
     */
    public function columns() : array {
        return $this->database->get_columns( $this->table );
    }

    /**
     * Check if a column has a specific type.
     *
     * Does a string match on the type (e.g., 'VARCHAR' matches 'VARCHAR(255)').
     *
     * @param string $column    The column name
     * @param string $type_part The type to match (e.g., 'VARCHAR')
     *
     * @return bool True if column type contains the specified type
     */
    public function columnTypeIs( string $column, string $type_part ) : bool {
        $actual_type = $this->columnType( $column );
        if ( null === $actual_type ) {
            return false;
        }
        return stripos( $actual_type, $type_part ) !== false;
    }

    /**
     * Check if an index exists on the table.
     *
     * @param string $index_name The index name
     *
     * @return bool True if index exists
     *
     * @example
     * if ($migration->inspect('licenses')->hasIndex('status_index')) {
     *     // Index exists
     * }
     */
    public function hasIndex( string $index_name ) : bool {
        $engine = $this->database->get_driver();

        try {
            switch ( $engine ) {
                case 'mysql':
                    $query = "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?";
                    $result = $this->database->get_var( $query, [ $this->table, $index_name ] );
                    return null !== $result;

                case 'pgsql':
                    $query = "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?";
                    $result = $this->database->get_var( $query, [ $this->table, $index_name ] );
                    return null !== $result;

                case 'sqlite':
                    $query = "SELECT 1 FROM sqlite_master WHERE type='index' AND name = ? AND tbl_name = ?";
                    $result = $this->database->get_var( $query, [ $index_name, $this->table ] );
                    return null !== $result;

                default:
                    return false;
            }
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get the row count in the table.
     *
     * @return int The number of rows in the table
     *
     * @example
     * $count = $migration->inspect('licenses')->rowCount();
     */
    public function rowCount() : int {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table}";
            $result = $this->database->get_var( $query );
            return (int) $result;
        } catch ( \Exception ) {
            return 0;
        }
    }
}