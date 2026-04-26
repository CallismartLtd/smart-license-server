<?php
/**
 * Analytics Daily Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores aggregated analytics.
 */
class AnalyticsDailySchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_ANALYTICS_DAILY_TABLE';
    }

    public static function get_columns() : array {
        return [
            'app_type VARCHAR(20) NOT NULL',
            'app_slug VARCHAR(100) NOT NULL',
            'stats_date DATE NOT NULL',
            'event_type VARCHAR(50) NOT NULL',
            'total_count INT(10) UNSIGNED DEFAULT 0',
            'unique_count INT(10) UNSIGNED DEFAULT 0',
            'PRIMARY KEY (app_type, app_slug, stats_date, event_type)',
            'INDEX date_lookup (stats_date)',
        ];
    }

    public static function get_label() : string { return 'Analytics Daily'; }
    public static function get_description() : string { return 'Stores daily analytics aggregates.'; }
}