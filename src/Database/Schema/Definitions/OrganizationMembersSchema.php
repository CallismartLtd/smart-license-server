<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class OrganizationMembersSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_ORGANIZATION_MEMBERS_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name' => 'id',
                'type' => 'bigint',
                'unsigned' => true,
                'auto_increment' => true,
                'nullable' => false,
            ],
            [
                'name' => 'organization_id',
                'type' => 'bigint',
                'unsigned' => true,
                'nullable' => false,
            ],
            [
                'name' => 'member_id',
                'type' => 'bigint',
                'unsigned' => true,
                'nullable' => false,
            ],
            [
                'name' => 'created_at',
                'type' => 'datetime',
                'nullable' => true,
            ],
            [
                'name' => 'updated_at',
                'type' => 'datetime',
                'nullable' => false,
            ],
        ];
    }

    public static function get_constraints() : array {
        return [
            [
                'type' => 'primary',
                'columns' => ['id'],
            ],
            [
                'type' => 'index',
                'name' => 'organization_member_id',
                'columns' => ['member_id'],
            ],
            [
                'type' => 'index',
                'name' => 'organization_id',
                'columns' => ['organization_id'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Organization Members';
    }

    public static function get_description() : string {
        return 'Maps members to organizations.';
    }
}