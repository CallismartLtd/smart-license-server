<?php
/**
 * Migration Runner
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Executes registered migrations.
 *
 * Runs migrations in version order with transaction support.
 * Tracks execution history and handles errors.
 *
 * @since 0.2.0
 */
class MigrationRunner {

    /**
     * The migration registry.
     *
     * @var MigrationRegistry
     */
    private $registry;

    /**
     * The migration history tracker.
     *
     * @var MigrationHistory
     */
    private $history;

    /**
     * Database adapter.
     *
     * @var \SmartLicenseServer\Database\Adapters\DatabaseAdapterInterface
     */
    private $adapter;

    /**
     * Execution log.
     *
     * @var array
     */
    private $log = [];

    /**
     * Constructor.
     *
     * @param MigrationRegistry $registry The migration registry
     * @param MigrationHistory  $history  The migration history tracker
     * @param mixed             $adapter  The database adapter
     */
    public function __construct( MigrationRegistry $registry, MigrationHistory $history, $adapter ) {
        $this->registry = $registry;
        $this->history = $history;
        $this->adapter = $adapter;

        // Ensure history table exists
        $this->history->create_table();
    }

    /**
     * Run pending migrations.
     *
     * Executes all migrations after the last executed version in version order.
     *
     * @return array Execution results and log
     *
     * @throws \Exception On migration failure
     */
    public function run_pending() : array {
        $last_version = $this->history->get_last_executed_version();
        $pending = $this->registry->get_pending( $last_version );

        return $this->run_migrations( $pending );
    }

    /**
     * Run all migrations.
     *
     * Executes all registered migrations in version order.
     *
     * @return array Execution results and log
     *
     * @throws \Exception On migration failure
     */
    public function run_all() : array {
        $all = $this->registry->get_all();
        $migrations = array_values( $all );

        // Sort by version
        usort( $migrations, fn( $a, $b ) => version_compare( $a['version'], $b['version'] ) );

        return $this->run_migrations( $migrations );
    }

    /**
     * Run specific migrations.
     *
     * @param array $migrations Array of migration metadata
     *
     * @return array Execution results and log
     *
     * @throws \Exception On migration failure
     */
    private function run_migrations( array $migrations ) : array {
        $this->log = [];
        $results = [
            'total' => count( $migrations ),
            'executed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'log' => [],
        ];

        foreach ( $migrations as $migration_meta ) {
            $version = $migration_meta['version'];
            $class_name = $migration_meta['class'];

            // Check if already executed
            if ( $this->history->has_executed( $version ) ) {
                $results['skipped']++;
                $this->log_entry( "SKIP", $version, "Already executed" );
                continue;
            }

            try {
                // Start transaction
                $this->adapter->begin_transaction();

                // Instantiate and run migration
                $migration = new $class_name( $this->adapter );
                $migration->start_tracking();

                // Execute
                $migration->up();

                $exec_time = $migration->get_execution_time();

                // Commit transaction
                $this->adapter->commit();

                // Record success
                $this->history->record( $version, $class_name, $exec_time, true );

                $results['executed']++;
                $this->log_entry( "SUCCESS", $version, "Executed in {$exec_time}ms" );

            } catch ( \Exception $e ) {
                // Rollback transaction
                try {
                    $this->adapter->rollback();
                } catch ( \Exception $rollback_error ) {
                    // Transaction may have already rolled back
                }

                // Record failure
                $error_msg = $e->getMessage();
                $this->history->record( $version, $class_name, 0, false, $error_msg );

                $results['failed']++;
                $results['errors'][ $version ] = $error_msg;
                $this->log_entry( "FAILED", $version, $error_msg );

                // Stop on first failure
                throw new \Exception( "Migration {$version} failed: {$error_msg}" );
            }
        }

        $results['log'] = $this->log;
        return $results;
    }

    /**
     * Log an entry.
     *
     * @param string $status  The status (SUCCESS, FAILED, SKIP)
     * @param string $version The migration version
     * @param string $message The log message
     *
     * @return void
     */
    private function log_entry( string $status, string $version, string $message ) : void {
        $this->log[] = [
            'status' => $status,
            'version' => $version,
            'message' => $message,
            'timestamp' => date( 'Y-m-d H:i:s' ),
        ];
    }

    /**
     * Get execution log.
     *
     * @return array The execution log entries
     */
    public function get_log() : array {
        return $this->log;
    }

    /**
     * Get the registry.
     *
     * @return MigrationRegistry
     */
    public function get_registry() : MigrationRegistry {
        return $this->registry;
    }

    /**
     * Get the history.
     *
     * @return MigrationHistory
     */
    public function get_history() : MigrationHistory {
        return $this->history;
    }
}