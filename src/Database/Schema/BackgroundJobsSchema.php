<?php
/**
 * Background Jobs Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores queued jobs.
 */
class BackgroundJobsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_BACKGROUND_JOBS_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'job_class VARCHAR(255) NOT NULL',
            'queue VARCHAR(50) NOT NULL DEFAULT \'default\'',
            'priority TINYINT UNSIGNED NOT NULL DEFAULT 5',
            'status VARCHAR(20) NOT NULL DEFAULT \'pending\'',
            'payload JSON NOT NULL',
            'attempts TINYINT UNSIGNED NOT NULL DEFAULT 0',
            'max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3',
            'available_at DATETIME NOT NULL',
            'started_at DATETIME DEFAULT NULL',
            'completed_at DATETIME DEFAULT NULL',
            'created_at DATETIME NOT NULL',
            'result JSON DEFAULT NULL',
            'error_message TEXT DEFAULT NULL',
            'INDEX idx_queue_status_available (queue, status, available_at)',
            'INDEX idx_status (status)',
            'INDEX idx_started_at (started_at)',
            'INDEX idx_completed_at (completed_at)',
        ];
    }

    public static function get_label() : string { return 'Background Jobs'; }
    public static function get_description() : string { return 'Stores background jobs.'; }
}