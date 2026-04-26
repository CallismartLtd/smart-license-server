<?php
/**
 * Theme Meta Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores metadata for themes.
 */
class ThemeMetaSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_THEMES_META_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'theme_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX theme_id_index (theme_id)',
            'INDEX meta_key_index (meta_key)',
        ];
    }

    public static function get_label() : string { return 'Theme Meta'; }
    public static function get_description() : string { return 'Stores theme metadata.'; }
}