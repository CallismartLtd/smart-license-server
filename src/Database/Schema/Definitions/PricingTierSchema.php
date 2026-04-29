<?php
/**
 * Pricing Tier Table Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores pricing tier definitions.
 */
class PricingTierSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Pricing Tiers';
    }

    public static function get_description() : string {
        return 'Stores pricing tier definitions.';
    }

    public static function get_table_name() : string {
        return SMLISER_PRICING_TIER_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( 'bigint' )
                ->auto_increment()
                ->required(),

            Column::make( 'monetization_id' )
                ->type( 'bigint' )
                ->required(),

            Column::make( 'name' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'product_id' )
                ->type( 'varchar' )
                ->size( 191 )
                ->required(),

            Column::make( 'provider_id' )
                ->type( 'varchar' )
                ->size( 50 )
                ->required(),

            Column::make( 'billing_cycle' )
                ->type( 'varchar' )
                ->size( 50 ),

            Column::make( 'max_sites' )
                ->type( 'int' )
                ->default( 1 ),

            Column::make( 'features' )
                ->type( 'text' ),

            Column::make( 'created_at' )
                ->type( 'datetime' ),

            Column::make( 'updated_at' )
                ->type( 'datetime' )
                ->required(),
        ];
    }

    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'index' )->name( 'monetization_id_index' )->on( 'monetization_id' ),
        ];
    }
}