<?php
/**
 * Users Table Schema
 *
 * Defines the structure for user account storage.
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Schema definition for users table.
 *
 * Stores authentication credentials and user account details.
 */
class UsersSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_USERS_TABLE;
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
                'name' => 'display_name',
                'type' => 'varchar',
                'length' => 255,
            ],
            [
                'name' => 'email',
                'type' => 'varchar',
                'length' => 255,
            ],
            [
                'name' => 'password_hash',
                'type' => 'varchar',
                'length' => 300,
            ],
            [
                'name' => 'status',
                'type' => 'enum',
                'values' => ['active', 'suspended', 'disabled'],
                'default' => 'active',
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
                'name' => 'smliser_users_email_unique',
                'columns' => ['email'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_users_created_at',
                'columns' => ['created_at'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_users_updated_at',
                'columns' => ['updated_at'],
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
        return 'users';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'Users';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores human user accounts with credentials.';
    }
}