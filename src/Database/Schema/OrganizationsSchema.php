<?php
/**
 * Organizations Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores organizations.
 */
class OrganizationsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_ORGANIZATIONS_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'display_name VARCHAR(255) NOT NULL',
            'slug VARCHAR(255) NOT NULL',
            'status ENUM(\'active\',\'suspended\',\'disabled\') NOT NULL DEFAULT \'active\'',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX organization_name (display_name)',
            'INDEX organization_slug (slug)',
        ];
    }

    public static function get_label() : string { return 'Organizations'; }
    public static function get_description() : string { return 'Stores organizations.'; }
}