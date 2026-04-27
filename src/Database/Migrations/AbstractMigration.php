<?php
/**
 * Abstract Migration Base Class
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Migrations\Helpers\ColumnHelper;
use SmartLicenseServer\Database\Migrations\Helpers\IndexHelper;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Base class for all database migrations.
 *
 * Provides fluent helper methods for common migration operations.
 * All migrations should extend this class.
 *
 * @since 0.2.0
 */
abstract class AbstractMigration implements MigrationInterface {

    /**
     * The database API instance.
     *
     * @var Database
     */
    protected $database;

    /**
     * SQL builder instance.
     *
     * @var SQLBuilder
     */
    protected $sql_builder;

    /**
     * Migration start time (for tracking duration).
     *
     * @var float
     */
    protected $start_time;

    /**
     * Constructor.
     *
     * @param Database|null $database The database API instance or null to use the global instance.
     */
    public function __construct( ?Database $database = null ) {
        $this->database     = $database ?? smliser_db();
        $this->sql_builder  = new SQLBuilder( $this->database->get_engine_type() );
    }

    /**
     * {@inheritDoc}
     */
    abstract public function up() : void;

    /**
     * {@inheritDoc}
     */
    public static function get_description() : string {
        return '';
    }

    /**
     * Extract version from class name.
     *
     * Migration0006 → '0.0.6'
     * Migration0011 → '0.1.1'
     * Migration0020 → '0.2.0'
     *
     * @param string $class_name The migration class name
     *
     * @return string The version string
     *
     * @throws \Exception If version cannot be extracted
     */
    public static function extract_version_from_class( string $class_name ) : string {
        // Extract class name without namespace
        $class_short = basename( str_replace( '\\', '/', $class_name ) );

        // Match Migration + 4 digits
        if ( ! preg_match( '/Migration(\d{4})/', $class_short, $matches ) ) {
            throw new \Exception( "Cannot extract version from class name: {$class_name}" );
        }

        $version_code = $matches[1];

        // Convert 0006 to 0.0.6, 0011 to 0.1.1, 0020 to 0.2.0
        return implode( '.', str_split( $version_code ) );
    }

    /**
     * {@inheritDoc}
     */
    public static function get_version() : string {
        $version_code = preg_replace( '/.*Migration(\d{4}).*/', '$1', static::class );
        return implode( '.', str_split( $version_code ) );
    }

    /**
     * Start tracking execution time.
     *
     * @return void
     */
    public function start_tracking() : void {
        $this->start_time = microtime( true );
    }

    /**
     * Get execution time in milliseconds.
     *
     * @return int Execution time in milliseconds
     */
    public function get_execution_time() : int {
        if ( ! isset( $this->start_time ) ) {
            return 0;
        }
        return (int) ( ( microtime( true ) - $this->start_time ) * 1000 );
    }

    /*
    |---------------------------
    | FLUENT HELPER METHODS
    |---------------------------
    */

    /**
     * Get a column helper for fluent operations.
     *
     * @param string $table The table name
     *
     * @return ColumnHelper
     */
    protected function column( string $table ) : ColumnHelper {
        return new ColumnHelper( $this->database, $this->sql_builder, $table );
    }

    /**
     * Get an index helper for fluent operations.
     *
     * @param string $table The table name
     *
     * @return IndexHelper
     */
    protected function index( string $table ) : IndexHelper {
        return new IndexHelper( $this->database, $this->sql_builder, $table );
    }

    /**
     * Get a constraint helper for fluent operations.
     *
     * @param string $table The table name
     *
     * @return ConstraintHelper
     */
    protected function constraint( string $table ) : ConstraintHelper {
        return new ConstraintHelper( $this->database, $this->sql_builder, $table );
    }

    /**
     * Get a schema inspector for fluent operations.
     *
     * @param string $table The table name
     *
     * @return SchemaInspector
     */
    protected function inspect( string $table ) : SchemaInspector {
        return new SchemaInspector( $this->database, $table );
    }

    /**
     * Get a table helper for fluent operations.
     *
     * @param string $table The table name
     *
     * @return TableHelper
     */
    protected function table( string $table ) : TableHelper {
        return new TableHelper( $this->database, $this->sql_builder, $table );
    }

    /**
     * Get a data helper for fluent operations.
     *
     * @param string $table The table name
     *
     * @return DataHelper
     */
    protected function data( string $table ) : DataHelper {
        return new DataHelper( $this->database, $table );
    }

    /**
     * Execute raw SQL directly.
     *
     * Use only when helpers don't provide what you need.
     *
     * @param string $sql The SQL statement
     *
     * @return bool True on success
     *
     * @throws \Exception On execution failure
     */
    protected function exec( string $sql ) : bool {
        return $this->database->exec( $sql );
    }

    /**
     * Get the database API instance.
     *
     * @return Database
     */
    protected function database() : Database {
        return $this->database;
    }
}