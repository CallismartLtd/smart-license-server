<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class RoleCapabilitiesSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_ROLE_CAPABILITIES_TABLE;
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
                'unsigned' => true,
                'nullable' => false,
            ],
            [
                'name' => 'capabilities',
                'type' => 'longtext',
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
        return 'Role Capabilities';
    }

    public static function get_description() : string {
        return 'Stores role capabilities.';
    }
}