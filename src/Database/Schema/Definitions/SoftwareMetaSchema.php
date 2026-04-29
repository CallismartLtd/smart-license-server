<?php
/**
 * Software Meta Table Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Stores metadata for software.
 */
class SoftwareMetaSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_SOFTWARE_META_TABLE;
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
                'name' => 'software_id',
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
                'name' => 'app_id_index',
                'columns' => ['software_id'],
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
        return 'software_meta';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'Software Meta';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores software metadata.';
    }
}