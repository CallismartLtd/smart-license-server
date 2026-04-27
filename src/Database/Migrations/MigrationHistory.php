<?php
/**
 * Migration History for Tracking Executed Migrations
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

use SmartLicenseServer\Database\Database;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Tracks which migrations have been executed.
 *
 * Stores migration execution history in database table.
 * Provides queries to check execution status and retrieves history info.
 *
 * @since 0.2.0
 */
class MigrationHistory {

    /**
     * Table name for migration history.
     *
     * @var string
     */
    private $table = 'smliser_migrations';

    /**
     * Database adapter instance.
     *
     * @var \SmartLicenseServer\Database\Adapters\DatabaseAdapterInterface
     */
    private $adapter;

    /**
     * Statement executor instance.
     *
     * @var Database
     */
    private $executor;

    /**
     * Constructor.
     *
     * @param \SmartLicenseServer\Database\Adapters\DatabaseAdapterInterface $adapter The database adapter
     */
    public function __construct( Database $database ) {
        $this->executor = $database;
    }

    /**
     * Create the migration history table if it doesn't exist.
     *
     * @return void
     *
     * @throws \Exception If table creation fails
     */
    public function create_table() : void {
        if ( $this->executor->table_exists( $this->table ) ) {
            return;
        }

        $sql = "
            CREATE TABLE {$this->table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(20) NOT NULL UNIQUE,
                migration_class VARCHAR(255) NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                execution_time INT DEFAULT NULL,
                status ENUM('success', 'failed') DEFAULT 'success',
                error_message LONGTEXT DEFAULT NULL,
                INDEX version_index (version),
                INDEX status_index (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->executor->exec( $sql );
    }

    /**
     * Record that a migration was executed.
     *
     * @param string $version      The migration version
     * @param string $class_name   The migration class name
     * @param int    $exec_time    Execution time in milliseconds
     * @param bool   $success      Whether the migration succeeded
     * @param string $error_msg    Error message if failed
     *
     * @return void
     *
     * @throws \Exception If insert fails
     */
    public function record( string $version, string $class_name, int $exec_time = 0, bool $success = true, string $error_msg = '' ) : void {
        $data = [
            'version' => $version,
            'migration_class' => $class_name,
            'execution_time' => $exec_time,
            'status' => $success ? 'success' : 'failed',
            'error_message' => $success ? null : $error_msg,
        ];

        $this->executor->insert( $this->table, $data );
    }

    /**
     * Check if a migration has been executed.
     *
     * @param string $version The migration version
     *
     * @return bool True if migration was executed
     */
    public function has_executed( string $version ) : bool {
        try {
            $query = "SELECT 1 FROM {$this->table} WHERE version = ? AND status = 'success'";
            $result = $this->executor->get_var( $query, [ $version ] );
            return null !== $result;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get the last executed migration version.
     *
     * @return string|null The version (e.g., '0.2.0'), or null if none executed
     */
    public function get_last_executed_version() : ?string {
        try {
            $query = "SELECT version FROM {$this->table} WHERE status = 'success' ORDER BY executed_at DESC LIMIT 1";
            return $this->executor->get_var( $query );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * Get all executed migrations.
     *
     * @return array Array of migration records
     */
    public function get_all_executed() : array {
        try {
            $query = "SELECT * FROM {$this->table} WHERE status = 'success' ORDER BY executed_at ASC";
            return $this->executor->get_results( $query );
        } catch ( \Exception $e ) {
            return [];
        }
    }

    /**
     * Get execution history record for a migration.
     *
     * @param string $version The migration version
     *
     * @return array|null The history record, or null if not found
     */
    public function get( string $version ) : ?array {
        try {
            $query = "SELECT * FROM {$this->table} WHERE version = ?";
            return $this->executor->get_row( $query, [ $version ] );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * Get all executed versions.
     *
     * @return array Array of version strings
     */
    public function get_all_versions() : array {
        try {
            $query = "SELECT version FROM {$this->table} WHERE status = 'success' ORDER BY executed_at ASC";
            return $this->executor->get_col( $query );
        } catch ( \Exception $e ) {
            return [];
        }
    }

    /**
     * Delete a migration history record.
     *
     * @param string $version The migration version
     *
     * @return void
     */
    public function delete( string $version ) : void {
        try {
            $this->executor->delete( $this->table, [ 'version' => $version ] );
        } catch ( \Exception $e ) {
            // Silently ignore
        }
    }

    /**
     * Clear all migration history.
     *
     * @return void
     */
    public function clear_all() : void {
        try {
            $this->executor->exec( "TRUNCATE TABLE {$this->table}" );
        } catch ( \Exception $e ) {
            // Silently ignore
        }
    }

    /**
     * Get the executor instance.
     *
     * @return Database
     */
    public function get_executor() : Database {
        return $this->executor;
    }
}