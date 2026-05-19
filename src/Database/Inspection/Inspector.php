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
use SmartLicenseServer\Database\Inspection\Contracts\InspectionInterface;
use SmartLicenseServer\Database\Inspection\Providers\MysqlInspector;
use SmartLicenseServer\Database\Inspection\Providers\PostgresInspector;
use SmartLicenseServer\Database\Inspection\Providers\SQLiteInspector;

/**
 * Database Schema Inspection Facade
 *
 * Provides a static proxy to an underlying database inspection engine implementation.
 * This facade exposes an engine-agnostic API for schema introspection across supported
 * database drivers such as MySQL, PostgreSQL, and SQLite.
 *
 * @method static array get_all_tables() Retrieve a list of all tables in the current database.
 * @method static bool table_exists(string $table) Check if a table exists.
 * @method static bool column_exists(string $table, string $column) Check if a column exists in a table.
 * @method static string|null get_column_type(string $table, string $column) Get the type of a column.
 * @method static array get_columns(string $table) Get all column names in a table.
 * @method static array get_column_details(string $table) Get detailed column metadata for a table.
 * @method static bool has_index(string $table, string $index_name) Check if an index exists on the table.
 * @method static array get_indexes(string $table) Get all indexes for a table.
 * @method static array|null get_primary_key(string $table) Get primary key column(s) for a table.
 * @method static array get_foreign_keys(string $table) Get all foreign key constraints for a table.
 * @method static bool has_foreign_key(string $table, string $constraint) Check if a foreign key constraint exists.
 * @method static array get_unique_constraints(string $table) Get all unique constraints for a table.
 * @method static array get_check_constraints(string $table) Get all check constraints for a table.
 * @method static array get_table_metadata(string $table) Get metadata about a table (engine, charset, etc.).
 * @method static bool|null is_column_nullable(string $table, string $column) Check if a column is nullable.
 * @method static mixed get_column_default(string $table, string $column) Get default value of a column.
 * @method static string get_host_info() Get information about the database connection host.
 * @method static string|int|null get_protocol_version() Retrieve database protocol version.
 * @method static string get_server_version() Get database server version.
 * @method static string get_engine_type() Get database engine type (mysql, pgsql, sqlite).
 */
class Inspector {
    /**
     * The schema inspector.
     * 
     * @var InspectionInterface
     */
    private InspectionInterface $inspector;

    /**
     * Constructor.
     *
     * @param Database $database The database instance to use for queries
     */
    public function __construct( 
        private Database $database, 
    ) {
        $this->inspector = $this->resolve_inspector();
    }

    private function resolve_inspector() : InspectionInterface {
        return match( $this->database->get_driver() ) {
            'mysql'     => new MysqlInspector( $this->database->get_adapter() ),
            'pgsql'     => new PostgresInspector( $this->database->get_adapter() ),
            'sqlite'    => new SQLiteInspector( $this->database->get_adapter() ),
        };
    }

    /**
     * Proxy calls to the adapter methods.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     *
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call( $method, $args ) {
        if ( method_exists( $this->inspector, $method ) ) {
            return call_user_func_array( [ $this->inspector, $method ], $args );
        }

        $backtrace  = \debug_backtrace( \DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
        $file       = $backtrace[0]['file'] ?? null;
        $line       = $backtrace[0]['line'] ?? null;
        $message    = sprintf(
            'Method %s::%s does not exist.', 
            get_class( $this ),
            $method
        );

        throw new \ErrorException( $message, 0, 1, $file, $line );
    }

}