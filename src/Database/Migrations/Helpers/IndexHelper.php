<?php
/**
 * Index Helper for Fluent Index Operations
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations\Helpers;

use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Migrations\SQLBuilder;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides fluent interface for index operations.
 *
 * Handles:
 * - Adding indexes
 * - Dropping indexes
 * - Renaming indexes
 * - Creating composite indexes
 * - Checking index existence
 *
 * @since 0.2.0
 */
class IndexHelper {

    /**
     * Statement executor.
     *
     * @var Database
     */
    private $executor;

    /**
     * SQL builder.
     *
     * @var SQLBuilder
     */
    private $sql_builder;

    /**
     * Table name.
     *
     * @var string
     */
    private $table;

    /**
     * Constructor.
     *
     * @param Database $executor    The statement executor
     * @param SQLBuilder        $sql_builder The SQL builder
     * @param string            $table       The table name
     */
    public function __construct( Database $executor, SQLBuilder $sql_builder, string $table ) {
        $this->executor = $executor;
        $this->sql_builder = $sql_builder;
        $this->table = $table;
    }

    /**
     * Add a simple index to the table.
     *
     * @param string $index_name The index name
     * @param string $column     The column to index
     *
     * @return self For method chaining
     *
     * @throws \Exception If index already exists or operation fails
     *
     * @example
     * $migration->index('users')->add('email_index', 'email');
     */
    public function add( string $index_name, string $column ) : self {
        return $this->addComposite( $index_name, [ $column ] );
    }

    /**
     * Add a composite (multi-column) index to the table.
     *
     * @param string $index_name The index name
     * @param array  $columns    Array of column names
     *
     * @return self For method chaining
     *
     * @throws \Exception If index already exists or operation fails
     *
     * @example
     * $migration->index('logs')
     *     ->addComposite('lookup_idx', ['app_slug', 'event_type', 'created_at']);
     */
    public function addComposite( string $index_name, array $columns ) : self {
        // Check if index already exists
        if ( $this->exists( $index_name ) ) {
            throw new \Exception( "Index '{$index_name}' already exists on table '{$this->table}'" );
        }

        $sql = $this->sql_builder->add_index( $index_name, $columns );
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Add a unique index to the table.
     *
     * @param string       $index_name The index name
     * @param string|array $columns    Column or array of columns
     *
     * @return self For method chaining
     *
     * @throws \Exception If index already exists or operation fails
     *
     * @example
     * $migration->index('users')->addUnique('email_unique', 'email');
     */
    public function addUnique( string $index_name, $columns ) : self {
        // Check if index already exists
        if ( $this->exists( $index_name ) ) {
            throw new \Exception( "Index '{$index_name}' already exists on table '{$this->table}'" );
        }

        $columns = (array) $columns;
        $sql = $this->sql_builder->add_index( $index_name, $columns, 'UNIQUE' );
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Add a fulltext index to the table (MySQL only).
     *
     * @param string       $index_name The index name
     * @param string|array $columns    Column or array of columns
     *
     * @return self For method chaining
     *
     * @throws \Exception If not MySQL or operation fails
     *
     * @example
     * $migration->index('posts')->addFulltext('content_fulltext', 'content');
     */
    public function addFulltext( string $index_name, $columns ) : self {
        if ( 'mysql' !== $this->executor->get_engine() ) {
            throw new \Exception( 'FULLTEXT indexes are only supported in MySQL' );
        }

        // Check if index already exists
        if ( $this->exists( $index_name ) ) {
            throw new \Exception( "Index '{$index_name}' already exists on table '{$this->table}'" );
        }

        $columns = (array) $columns;
        $sql = $this->sql_builder->add_index( $index_name, $columns, 'FULLTEXT' );
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Drop an index from the table.
     *
     * @param string $index_name The index name
     *
     * @return self For method chaining
     *
     * @throws \Exception If index doesn't exist or operation fails
     *
     * @example
     * $migration->index('users')->drop('email_index');
     */
    public function drop( string $index_name ) : self {
        // Check if index exists
        if ( ! $this->exists( $index_name ) ) {
            throw new \Exception( "Index '{$index_name}' does not exist on table '{$this->table}'" );
        }

        $sql = $this->sql_builder->drop_index( $index_name );
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Rename an index (if supported by database).
     *
     * Note: Not all databases support renaming indexes.
     *
     * @param string $old_name The old index name
     * @param string $new_name The new index name
     *
     * @return self For method chaining
     *
     * @throws \Exception If not supported, index doesn't exist, or operation fails
     *
     * @example
     * $migration->index('users')->rename('idx_old', 'idx_new');
     */
    public function rename( string $old_name, string $new_name ) : self {
        $engine = $this->executor->get_engine();

        // Check database support
        if ( 'mysql' !== $engine ) {
            throw new \Exception( "Renaming indexes is not supported in {$engine}" );
        }

        // Check if old index exists
        if ( ! $this->exists( $old_name ) ) {
            throw new \Exception( "Index '{$old_name}' does not exist on table '{$this->table}'" );
        }

        // Check if new name doesn't exist
        if ( $this->exists( $new_name ) ) {
            throw new \Exception( "Index '{$new_name}' already exists on table '{$this->table}'" );
        }

        // MySQL: ALTER TABLE table RENAME INDEX old TO new
        $sql = "ALTER TABLE {$this->table} RENAME INDEX {$old_name} TO {$new_name}";
        $this->executor->exec( $sql );

        return $this;
    }

    /**
     * Check if an index exists on the table.
     *
     * @param string $index_name The index name
     *
     * @return bool True if index exists
     *
     * @example
     * if ($migration->index('users')->exists('email_index')) {
     *     $migration->index('users')->drop('email_index');
     * }
     */
    public function exists( string $index_name ) : bool {
        $engine = $this->executor->get_engine();

        try {
            switch ( $engine ) {
                case 'mysql':
                    $query = "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?";
                    $result = $this->executor->get_var( $query, [ $this->table, $index_name ] );
                    return null !== $result;

                case 'pgsql':
                    $query = "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?";
                    $result = $this->executor->get_var( $query, [ $this->table, $index_name ] );
                    return null !== $result;

                case 'sqlite':
                    $query = "SELECT 1 FROM sqlite_master WHERE type='index' AND name = ? AND tbl_name = ?";
                    $result = $this->executor->get_var( $query, [ $index_name, $this->table ] );
                    return null !== $result;

                default:
                    return false;
            }
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get all indexes on the table.
     *
     * @return array Array of index names
     *
     * @example
     * $indexes = $migration->index('users')->list();
     * // Returns: ['PRIMARY', 'email_index', 'created_at_index']
     */
    public function list() : array {
        $engine = $this->executor->get_engine();

        try {
            switch ( $engine ) {
                case 'mysql':
                    $query = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? GROUP BY index_name";
                    return $this->executor->get_col( $query, [ $this->table ] );

                case 'pgsql':
                    $query = "SELECT indexname FROM pg_indexes WHERE tablename = ?";
                    return $this->executor->get_col( $query, [ $this->table ] );

                case 'sqlite':
                    $query = "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ?";
                    return $this->executor->get_col( $query, [ $this->table ] );

                default:
                    return [];
            }
        } catch ( \Exception $e ) {
            return [];
        }
    }
}