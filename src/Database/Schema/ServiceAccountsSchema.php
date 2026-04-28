<?php
/**
 * Service Accounts Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined('SMLISER_ABSPATH') || exit;

/**
 * Stores service accounts.
 */
class ServiceAccountsSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritdoc}
     */
    public static function get_table_name(): string {
        return SMLISER_SERVICE_ACCOUNTS_TABLE;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_columns(): array {
        return [
            [
                'name' => 'id',
                'type' => 'int',
                'auto_increment' => true,
            ],
            [
                'name' => 'identifier',
                'type' => 'varchar',
                'length' => 255,
            ],
            [
                'name' => 'owner_id',
                'type' => 'int',
            ],
            [
                'name' => 'display_name',
                'type' => 'varchar',
                'length' => 255,
            ],
            [
                'name' => 'description',
                'type' => 'text',
                'nullable' => true,
            ],
            [
                'name' => 'api_key_hash',
                'type' => 'varchar',
                'length' => 512,
            ],
            [
                'name' => 'status',
                'type' => 'enum',
                'default' => 'active',
                'values' => ['active', 'suspended', 'disabled'],
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
            [
                'name' => 'last_used_at',
                'type' => 'datetime',
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
                'type' => 'index',
                'name' => 'smliser_service_acct_owner_id',
                'columns' => ['owner_id'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_service_acct_api_key_hash',
                'columns' => ['api_key_hash'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_service_acct_status',
                'columns' => ['status'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_service_acct_created_at',
                'columns' => ['created_at'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_service_acct_updated_at',
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
        return 'service_accounts';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_label(): string {
        return 'Service Accounts';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_description(): string {
        return 'Stores service accounts.';
    }
}