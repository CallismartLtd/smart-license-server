<?php
/**
 * Pricing Tier Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores pricing tiers.
 */
class PricingTierSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_PRICING_TIER_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT AUTO_INCREMENT PRIMARY KEY',
            'monetization_id BIGINT NOT NULL',
            'name VARCHAR(255) NOT NULL',
            'product_id VARCHAR(191) NOT NULL',
            'provider_id VARCHAR(50) NOT NULL',
            'billing_cycle VARCHAR(50) DEFAULT NULL',
            'max_sites INT DEFAULT 1',
            'features TEXT DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX monetization_id_index (monetization_id)',
        ];
    }

    public static function get_label() : string { return 'Pricing Tiers'; }
    public static function get_description() : string { return 'Stores pricing tier definitions.'; }
}