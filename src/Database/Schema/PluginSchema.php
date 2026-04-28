<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class PluginSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_PLUGINS_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name' => 'id',
                'type' => 'int',
                'unsigned' => true,
                'auto_increment' => true,
                'nullable' => false,
            ],
            [
                'name' => 'owner_id',
                'type' => 'bigint',
                'nullable' => true,
            ],
            [
                'name' => 'name',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => false,
            ],
            [
                'name' => 'slug',
                'type' => 'varchar',
                'length' => 300,
                'nullable' => true,
            ],
            [
                'name' => 'status',
                'type' => 'varchar',
                'length' => 300,
                'default' => 'active',
                'nullable' => true,
            ],
            [
                'name' => 'author',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => true,
            ],
            [
                'name' => 'author_profile',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => true,
            ],
            [
                'name' => 'download_link',
                'type' => 'varchar',
                'length' => 400,
                'nullable' => true,
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
                'name' => 'plugin_download_link_index',
                'columns' => ['download_link'],
            ],
            [
                'type' => 'index',
                'name' => 'plugin_slug_index',
                'columns' => ['slug'],
            ],
            [
                'type' => 'index',
                'name' => 'plugin_author_index',
                'columns' => ['author'],
            ],
            [
                'type' => 'index',
                'name' => 'plugin_status_index',
                'columns' => ['status'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Plugins';
    }

    public static function get_description() : string {
        return 'Stores plugin information.';
    }
}