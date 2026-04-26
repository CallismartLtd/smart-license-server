<?php
/**
 * Organization Members Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Maps members to organizations.
 */
class OrganizationMembersSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_ORGANIZATION_MEMBERS_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'organization_id BIGINT(20) UNSIGNED NOT NULL',
            'member_id BIGINT(20) UNSIGNED NOT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX organization_member_id (member_id)',
            'INDEX organization_id (organization_id)',
        ];
    }

    public static function get_label() : string { return 'Organization Members'; }
    public static function get_description() : string { return 'Maps members to organizations.'; }
}