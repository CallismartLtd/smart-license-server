<?php
/**
 * Theme Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores theme records.
 */
class ThemeSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_THEMES_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'owner_id BIGINT(20) DEFAULT NULL',
            'name VARCHAR(255) NOT NULL',
            'slug VARCHAR(300) DEFAULT NULL',
            'author VARCHAR(255) DEFAULT NULL',
            'status VARCHAR(55) DEFAULT \'active\'',
            'download_link VARCHAR(400) DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX theme_slug_index (slug)',
            'INDEX theme_author_index (author)',
            'INDEX theme_download_link_index (download_link)',
            'INDEX theme_status_index (status)',
        ];
    }

    public static function get_label() : string { return 'Themes'; }
    public static function get_description() : string { return 'Stores theme information.'; }
}