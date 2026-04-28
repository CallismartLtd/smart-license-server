<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class OrganizationsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_ORGANIZATIONS_TABLE;
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
                'name' => 'display_name',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => false,
            ],
            [
                'name' => 'slug',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => false,
            ],
            [
                'name' => 'status',
                'type' => 'enum',
                'values' => ['active', 'suspended', 'disabled'],
                'default' => 'active',
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
                'name' => 'organization_name',
                'columns' => ['display_name'],
            ],
            [
                'type' => 'index',
                'name' => 'organization_slug',
                'columns' => ['slug'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Organizations';
    }

    public static function get_description() : string {
        return 'Stores organizations.';
    }
}