<?php
/**
 * Software Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Stores software records.
 */
class SoftwareSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_SOFTWARE_TABLE;
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
            ],
            [
                'name' => 'status',
                'type' => 'varchar',
                'length' => 55,
                'default' => 'active',
            ],
            [
                'name' => 'author',
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
                'type' => 'unique',
                'columns' => ['slug'],
            ],
            [
                'type' => 'index',
                'name' => 'software_slug_index',
                'columns' => ['slug'],
            ],
            [
                'type' => 'index',
                'name' => 'software_author_index',
                'columns' => ['author'],
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
        return 'software';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'Software';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores software records.';
    }
}