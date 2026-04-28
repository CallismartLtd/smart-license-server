<?php
/**
 * Theme Meta Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Stores metadata for themes.
 */
class ThemeMetaSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_THEMES_META_TABLE;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_columns(): array {
        return [
            [
                'name' => 'id',
                'type' => 'bigint',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            [
                'name' => 'theme_id',
                'type' => 'bigint',
                'unsigned' => true,
            ],
            [
                'name' => 'meta_key',
                'type' => 'varchar',
                'length' => 255,
            ],
            [
                'name' => 'meta_value',
                'type' => 'longtext',
                'nullable' => true,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function get_constraints(): array {
        return [
            [
                'type' => 'primary',
                'columns' => ['id'],
            ],
            [
                'type' => 'index',
                'name' => 'theme_id_index',
                'columns' => ['theme_id'],
            ],
            [
                'type' => 'index',
                'name' => 'meta_key_index',
                'columns' => ['meta_key'],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function get_options(): array {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function get_id(): string {
        return 'theme_meta';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'Theme Meta';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores theme metadata.';
    }
}