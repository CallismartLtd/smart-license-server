<?php
/**
 * Data Helpers class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

use SmartLicenseServer\Database\Database;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides fluent interface for safe data transformations.
 *
 * Handles:
 * - Updating data
 * - Moving data between columns
 * - Batch processing
 * - Data transformations
 *
 * @since 0.2.0
 */
class DataHelper {

    /**
     * Statement executor.
     *
     * @var Database
     */
    private $executor;

    /**
     * Table name.
     *
     * @var string
     */
    private $table;

    /**
     * Constructor.
     *
     * @param Database $executor The statement executor
     * @param string            $table    The table name
     */
    public function __construct( Database $executor, string $table ) {
        $this->executor = $executor;
        $this->table = $table;
    }

    /**
     * Update data in the table.
     *
     * @param array $data  Array of column => value pairs to update
     * @param array $where Array of column => value pairs for WHERE clause
     *
     * @return int Number of affected rows
     *
     * @throws \Exception If operation fails
     *
     * @example
     * $migration->data('licenses')
     *     ->update(
     *         ['status' => 'active'],
     *         ['status' => null]  // WHERE status IS NULL
     *     );
     */
    public function update( array $data, array $where = [] ) : int {
        return (int) $this->executor->update( $this->table, $data, $where );
    }

    /**
     * Insert a row into the table.
     *
     * @param array $data Array of column => value pairs
     *
     * @return int|false The inserted row ID, or false on failure
     *
     * @throws \Exception If operation fails
     *
     * @example
     * $migration->data('roles')->insert([
     *     'slug' => 'admin',
     *     'label' => 'Administrator'
     * ]);
     */
    public function insert( array $data ) {
        return $this->executor->insert( $this->table, $data );
    }

    /**
     * Delete rows from the table.
     *
     * @param array $where Array of column => value pairs for WHERE clause
     *
     * @return int Number of affected rows
     *
     * @throws \Exception If operation fails
     *
     * @example
     * $migration->data('logs')->delete(['status' => 'deleted']);
     */
    public function delete( array $where ) : int {
        return (int) $this->executor->delete( $this->table, $where );
    }

    /**
     * Process data in batches.
     *
     * Useful for large tables where you need to process all rows.
     *
     * @param int      $batch_size The number of rows to process per iteration
     * @param callable $callback   Function to process each row: function($row) {}
     *
     * @return void
     *
     * @throws \Exception If operation fails
     *
     * @example
     * $migration->data('licenses')->batch(100, function($row) {
     *     // Process each row
     *     $app_prop = $row['type'] . '/' . $row['slug'];
     *     // Update or process as needed
     * });
     */
    public function batch( int $batch_size, callable $callback ) : void {
        $query = "SELECT * FROM {$this->table} LIMIT ? OFFSET ?";
        $offset = 0;

        while ( true ) {
            $rows = $this->executor->get_results( $query, [ $batch_size, $offset ] );

            if ( empty( $rows ) ) {
                break;
            }

            foreach ( $rows as $row ) {
                call_user_func( $callback, $row );
            }

            $offset += $batch_size;
        }
    }

    /**
     * Get all rows from the table.
     *
     * @return array Array of associative arrays
     *
     * @example
     * $all_rows = $migration->data('licenses')->all();
     */
    public function all() : array {
        $query = "SELECT * FROM {$this->table}";
        return $this->executor->get_results( $query );
    }

    /**
     * Get row count.
     *
     * @return int Number of rows
     *
     * @example
     * $count = $migration->data('licenses')->count();
     */
    public function count() : int {
        $query = "SELECT COUNT(*) FROM {$this->table}";
        return (int) $this->executor->get_var( $query );
    }

    /**
     * Access the executor directly for complex operations.
     *
     * @return Database
     */
    public function executor() : Database {
        return $this->executor;
    }
}