<?php
/**
 * Theme Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Stores theme records.
 */
class ThemeSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_THEMES_TABLE;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_columns(): array {
        return [
            [
                'name' => 'id',
                'type' => 'int',
                'unsigned' => true,
                'auto_increment' => true,
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
            ],
            [
                'name' => 'slug',
                'type' => 'varchar',
                'length' => 300,
                'nullable' => true,
            ],
            [
                'name' => 'author',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => true,
            ],
            [
                'name' => 'status',
                'type' => 'varchar',
                'length' => 55,
                'default' => 'active',
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
                'name' => 'theme_slug_index',
                'columns' => ['slug'],
            ],
            [
                'type' => 'index',
                'name' => 'theme_author_index',
                'columns' => ['author'],
            ],
            [
                'type' => 'index',
                'name' => 'theme_download_link_index',
                'columns' => ['download_link'],
            ],
            [
                'type' => 'index',
                'name' => 'theme_status_index',
                'columns' => ['status'],
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
        return 'themes';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'Themes';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores theme information.';
    }
}