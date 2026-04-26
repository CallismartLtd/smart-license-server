<?php
/**
 * License Meta Table Schema
 *
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores metadata for licenses.
 */
class LicenseMetaSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_LICENSE_META_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'license_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX license_id_index (license_id)',
            'INDEX meta_key_index (meta_key)',
        ];
    }

    public static function get_label() : string { return 'License Meta'; }
    public static function get_description() : string { return 'Stores arbitrary key-value metadata for licenses.'; }
}