<?php
/**
 * User Options Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Stores user options.
 */
class UserOptionsSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_USER_OPTIONS_TABLE;
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
                'name' => 'user_id',
                'type' => 'bigint',
                'unsigned' => true,
            ],
            [
                'name' => 'option_key',
                'type' => 'varchar',
                'length' => 255,
            ],
            [
                'name' => 'option_value',
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
                'type' => 'unique',
                'name' => 'smliser_user_options_unique',
                'columns' => ['user_id', 'option_key'],
            ],
            [
                'type' => 'index',
                'name' => 'option_key_index',
                'columns' => ['option_key'],
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
        return 'user_options';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'User Options';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores user preferences.';
    }
}