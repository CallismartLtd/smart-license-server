<?php
/**
 * Plugin Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores plugin records.
 */
class PluginSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_PLUGINS_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'owner_id BIGINT(20) DEFAULT NULL',
            'name VARCHAR(255) NOT NULL',
            'slug VARCHAR(300) DEFAULT NULL',
            'status VARCHAR(300) DEFAULT \'active\'',
            'author VARCHAR(255) DEFAULT NULL',
            'author_profile VARCHAR(255) DEFAULT NULL',
            'download_link VARCHAR(400) DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX plugin_download_link_index (download_link)',
            'INDEX plugin_slug_index (slug)',
            'INDEX plugin_author_index (author)',
            'INDEX plugin_status_index (status)',
        ];
    }

    public static function get_label() : string { return 'Plugins'; }
    public static function get_description() : string { return 'Stores plugin information.'; }
}