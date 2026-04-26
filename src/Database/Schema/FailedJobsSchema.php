<?php
/**
 * Failed Jobs Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores failed jobs.
 */
class FailedJobsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_FAILED_JOBS_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'job_id BIGINT UNSIGNED NOT NULL',
            'job_class VARCHAR(255) NOT NULL',
            'queue VARCHAR(50) NOT NULL',
            'payload JSON NOT NULL',
            'error_message TEXT DEFAULT NULL',
            'failed_at DATETIME NOT NULL',
            'INDEX idx_job_id (job_id)',
            'INDEX idx_failed_at (failed_at)',
            'INDEX idx_job_class (job_class)',
        ];
    }

    public static function get_label() : string { return 'Failed Jobs'; }
    public static function get_description() : string { return 'Stores failed jobs.'; }
}