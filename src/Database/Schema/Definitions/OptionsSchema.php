<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class OptionsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_OPTIONS_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name' => 'option_id',
                'type' => 'bigint',
                'unsigned' => true,
                'auto_increment' => true,
                'nullable' => false,
            ],
            [
                'name' => 'option_name',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => false,
            ],
            [
                'name' => 'option_value',
                'type' => 'longtext',
                'nullable' => true,
            ],
        ];
    }

    public static function get_constraints() : array {
        return [
            [
                'type' => 'primary',
                'columns' => ['option_id'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_option_key',
                'columns' => ['option_name'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Options';
    }

    public static function get_description() : string {
        return 'Stores global configuration.';
    }
}