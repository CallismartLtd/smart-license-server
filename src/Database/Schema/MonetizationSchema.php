<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class MonetizationSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_MONETIZATION_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name' => 'id',
                'type' => 'bigint',
                'auto_increment' => true,
                'nullable' => false,
            ],
            [
                'name' => 'app_type',
                'type' => 'varchar',
                'length' => 50,
                'nullable' => false,
            ],
            [
                'name' => 'app_id',
                'type' => 'bigint',
                'nullable' => false,
            ],
            [
                'name' => 'enabled',
                'type' => 'tinyint',
                'length' => 1,
                'default' => 0,
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
                'type' => 'unique',
                'name' => 'unique_app_monetization',
                'columns' => ['app_type', 'app_id'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Monetization';
    }

    public static function get_description() : string {
        return 'Stores monetization settings.';
    }
}