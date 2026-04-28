<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class PluginMetaSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_PLUGINS_META_TABLE;
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
                'name' => 'plugin_id',
                'type' => 'bigint',
                'unsigned' => true,
                'nullable' => false,
            ],
            [
                'name' => 'meta_key',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => false,
            ],
            [
                'name' => 'meta_value',
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
            [
                'type' => 'index',
                'name' => 'plugin_id_index',
                'columns' => ['plugin_id'],
            ],
            [
                'type' => 'index',
                'name' => 'meta_key_index',
                'columns' => ['meta_key'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Plugin Meta';
    }

    public static function get_description() : string {
        return 'Stores arbitrary key-value metadata for plugins.';
    }
}