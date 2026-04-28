<?php
/**
 * Roles Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Stores roles.
 */
class RolesSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_ROLES_TABLE;
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
                'name' => 'slug',
                'type' => 'varchar',
                'length' => 64,
            ],
            [
                'name' => 'label',
                'type' => 'varchar',
                'length' => 190,
            ],
            [
                'name' => 'is_canonical',
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
                'name' => 'smliser_owner_role_unique',
                'columns' => ['slug'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_roles_name',
                'columns' => ['slug'],
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
        return 'roles';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'Roles';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores roles.';
    }
}