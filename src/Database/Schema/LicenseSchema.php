<?php
/**
 * License Table Schema
 *
 * Defines the database structure for storing license records.
 *
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Schema definition for the licenses table.
 *
 * Stores license keys, ownership details, and validity periods.
 */
class LicenseSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_LICENSE_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'licensee_fullname VARCHAR(512) DEFAULT NULL',
            'license_key VARCHAR(300) NOT NULL UNIQUE',
            'service_id VARCHAR(300) NOT NULL',
            'app_prop VARCHAR(600) DEFAULT NULL',
            'max_allowed_domains MEDIUMINT(9) DEFAULT NULL',
            'status VARCHAR(30) DEFAULT NULL',
            'start_date DATETIME DEFAULT NULL',
            'end_date DATETIME DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX service_id_index (service_id)',
            'INDEX status_index (status)',
        ];
    }

    public static function get_label() : string {
        return 'Licenses';
    }

    public static function get_description() : string {
        return 'Stores license records with keys, service identifiers, and validity dates.';
    }
}