<?php
/**
 * Monetization Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores monetization config.
 */
class MonetizationSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_MONETIZATION_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT AUTO_INCREMENT PRIMARY KEY',
            'app_type VARCHAR(50) NOT NULL',
            'app_id BIGINT NOT NULL',
            'enabled TINYINT(1) DEFAULT 0',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'UNIQUE KEY unique_app_monetization (app_type, app_id)'
        ];
    }

    public static function get_label() : string { return 'Monetization'; }
    public static function get_description() : string { return 'Stores monetization settings.'; }
}