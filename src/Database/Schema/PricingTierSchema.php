<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class PricingTierSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_PRICING_TIER_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name' => 'id',
                'type' => 'bigint',
                'auto_increment' => true,
                'nullable' => false,
            ],
            [
                'name' => 'monetization_id',
                'type' => 'bigint',
                'nullable' => false,
            ],
            [
                'name' => 'name',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => false,
            ],
            [
                'name' => 'product_id',
                'type' => 'varchar',
                'length' => 191,
                'nullable' => false,
            ],
            [
                'name' => 'provider_id',
                'type' => 'varchar',
                'length' => 50,
                'nullable' => false,
            ],
            [
                'name' => 'billing_cycle',
                'type' => 'varchar',
                'length' => 50,
                'nullable' => true,
            ],
            [
                'name' => 'max_sites',
                'type' => 'int',
                'default' => 1,
            ],
            [
                'name' => 'features',
                'type' => 'text',
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
                'name' => 'monetization_id_index',
                'columns' => ['monetization_id'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Pricing Tiers';
    }

    public static function get_description() : string {
        return 'Stores pricing tier definitions.';
    }
}