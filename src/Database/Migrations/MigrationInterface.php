<?php
/**
 * Migration Interface
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Interface that all database migrations must implement.
 *
 * Each migration represents a version-based set of database changes.
 * Migrations are forward-only (no rollback support).
 *
 * @since 0.2.0
 */
interface MigrationInterface {

    /**
     * Get the version this migration targets.
     *
     * Must match the class name pattern. Examples:
     * - Migration0006 → '0.0.6'
     * - Migration0011 → '0.1.1'
     * - Migration0020 → '0.2.0'
     *
     * @return string Version string (e.g., '0.2.0')
     */
    public static function get_version() : string;

    /**
     * Execute this migration's database changes.
     *
     * This method runs all changes for this version.
     * Changes should be idempotent (safe to run multiple times).
     *
     * @return void
     *
     * @throws \Exception On migration failure
     */
    public function up() : void;

    /**
     * Get a brief description of what this migration does.
     *
     * @return string Description of changes (e.g., "Add owner_id to apps tables")
     */
    public static function get_description() : string;
}