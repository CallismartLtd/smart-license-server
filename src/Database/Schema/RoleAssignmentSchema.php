<?php
/**
 * Role Assignment Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Maps roles to principals.
 */
class RoleAssignmentSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_ROLE_ASSIGNMENT_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'role_id BIGINT(20) NOT NULL',
            'principal_type ENUM(\'individual\', \'service_account\', \'platform\') NOT NULL',
            'principal_id BIGINT(20) UNSIGNED NOT NULL',
            'owner_subject_type ENUM(\'platform\', \'individual\', \'organization\') NOT NULL',
            'owner_subject_id BIGINT UNSIGNED NOT NULL',
            'created_by BIGINT UNSIGNED DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL'
        ];
    }

    public static function get_label() : string { return 'Role Assignments'; }
    public static function get_description() : string { return 'Maps roles to principals.'; }
}