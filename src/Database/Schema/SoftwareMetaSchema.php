<?php
/**
 * Software Meta Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores metadata for software.
 */
class SoftwareMetaSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_SOFTWARE_META_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'software_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX app_id_index (software_id)',
            'INDEX meta_key_index (meta_key)',
        ];
    }

    public static function get_label() : string { return 'Software Meta'; }
    public static function get_description() : string { return 'Stores software metadata.'; }
}