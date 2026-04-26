<?php
/**
 * Analytics Logs Table Schema
 *
 * Defines the structure for high-volume event logging.
 *
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Schema definition for analytics logs table.
 *
 * Acts as the raw event store for all analytics tracking.
 */
class AnalyticsLogsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_ANALYTICS_LOGS_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'app_type VARCHAR(20) NOT NULL',
            'app_slug VARCHAR(100) NOT NULL',
            'event_type VARCHAR(50) NOT NULL',
            'fingerprint CHAR(64) DEFAULT NULL',
            'created_at DATETIME DEFAULT CURRENT_TIMESTAMP',
            'INDEX app_identity_idx (app_type, app_slug)',
            'INDEX event_type_idx (event_type)',
            'INDEX lookup_idx (app_slug, event_type, created_at)',
            'INDEX cleanup_idx (created_at)',
        ];
    }

    public static function get_label() : string {
        return 'Analytics Logs';
    }

    public static function get_description() : string {
        return 'Stores raw analytics events - source of truth for event data.';
    }
}