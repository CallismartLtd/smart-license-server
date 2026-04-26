<?php
/**
 * Software Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores software records.
 */
class SoftwareSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_SOFTWARE_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'owner_id BIGINT(20) DEFAULT NULL',
            'name VARCHAR(255) NOT NULL',
            'slug VARCHAR(300) UNIQUE NOT NULL',
            'status VARCHAR(55) DEFAULT \'active\'',
            'author VARCHAR(255) DEFAULT NULL',
            'download_link VARCHAR(400) DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX software_slug_index (slug)',
            'INDEX software_author_index (author)',
        ];
    }

    public static function get_label() : string { return 'Software'; }
    public static function get_description() : string { return 'Stores software records.'; }
}