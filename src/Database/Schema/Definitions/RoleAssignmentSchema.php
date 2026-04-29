<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class RoleAssignmentSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_ROLE_ASSIGNMENT_TABLE;
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
                'name' => 'role_id',
                'type' => 'bigint',
                'nullable' => false,
            ],
            [
                'name' => 'principal_type',
                'type' => 'enum',
                'values' => ['individual', 'service_account', 'platform'],
                'nullable' => false,
            ],
            [
                'name' => 'principal_id',
                'type' => 'bigint',
                'unsigned' => true,
                'nullable' => false,
            ],
            [
                'name' => 'owner_subject_type',
                'type' => 'enum',
                'values' => ['platform', 'individual', 'organization'],
                'nullable' => false,
            ],
            [
                'name' => 'owner_subject_id',
                'type' => 'bigint',
                'unsigned' => true,
                'nullable' => false,
            ],
            [
                'name' => 'created_by',
                'type' => 'bigint',
                'unsigned' => true,
                'nullable' => true,
            ],
            [
                'name' => 'created_at',
                'type' => 'datetime',
                'nullable' => true,
            ],
        ];
    }

    public static function get_constraints() : array {
        return [
            [
                'type' => 'primary',
                'columns' => ['id'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Role Assignments';
    }

    public static function get_description() : string {
        return 'Maps roles to principals.';
    }
}