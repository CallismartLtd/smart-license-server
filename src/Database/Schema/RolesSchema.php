<?php
/**
 * Roles Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores roles.
 */
class RolesSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_ROLES_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'slug VARCHAR(64) NOT NULL',
            'label VARCHAR(190) NOT NULL',
            'is_canonical TINYINT(1) DEFAULT 0',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'UNIQUE KEY smliser_owner_role_unique (slug)',
            'INDEX smliser_roles_name (slug)',
        ];
    }

    public static function get_label() : string { return 'Roles'; }
    public static function get_description() : string { return 'Stores roles.'; }
}