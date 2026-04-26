<?php
/**
 * Role Capabilities Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores role capabilities.
 */
class RoleCapabilitiesSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_ROLE_CAPABILITIES_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'role_id BIGINT UNSIGNED NOT NULL',
            'capabilities LONGTEXT DEFAULT NULL'
        ];
    }

    public static function get_label() : string { return 'Role Capabilities'; }
    public static function get_description() : string { return 'Stores role capabilities.'; }
}