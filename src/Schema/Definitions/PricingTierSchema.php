<?php
/**
 * Pricing Tier Table Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Stores pricing tiers: the templates licenses are issued from.
 *
 * A tier defines what a new license grants (term, number of sites,
 * features) and names the one provider that sells it, with that provider's
 * own data for it (`provider_data`). Its terms are copied onto each license
 * at issue; changing a tier never changes licenses already issued. A tier
 * that licenses were issued from is archived, not deleted.
 */
class PricingTierSchema implements DatabaseSchemaInterface {

	public static function get_label() : string {
		return 'Pricing Tiers';
	}

	public static function get_description() : string {
		return 'Stores pricing tier definitions.';
	}

	public static function get_table_name() : string {
		return TableName::PRICING_TIERS->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->auto_increment()
				->required(),

			Column::make( 'monetization_id' )
				->type( ColumnType::BIG_INT )
				->required(),

			Column::make( 'name' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->required(),

			Column::make( 'term_unit' )
				->type( ColumnType::VARCHAR )
				->size( 20 )
				->default( 'lifetime' )
				->required(),

			Column::make( 'term_length' )
				->type( ColumnType::INTEGER )
				->default( 1 ),

			Column::make( 'max_sites' )
				->type( ColumnType::INTEGER )
				->default( 1 ),

			Column::make( 'features' )
				->type( ColumnType::TEXT ),

			Column::make( 'provider_id' )
				->type( ColumnType::VARCHAR )
				->size( 100 ),

			Column::make( 'provider_data' )
				->type( ColumnType::TEXT ),

			Column::make( 'status' )
				->type( ColumnType::VARCHAR )
				->size( 20 )
				->default( 'active' )
				->required(),

			Column::make( 'created_at' )
				->type( ColumnType::DATETIME ),

			Column::make( 'updated_at' )
				->type( ColumnType::DATETIME )
				->required(),
		];
	}

	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),
			Constraint::index( "{$prefx}monetization_id_index" )->on( 'monetization_id' ),
			Constraint::index( "{$prefx}provider_index" )->on( 'provider_id' ),
			Constraint::foreign_key( "{$prefx}monetization_fk" )
				->on( 'monetization_id' )
				->references( TableName::MONETIZATION->table(), 'id' )
				->on_delete( 'CASCADE' )
		];
	}

	protected static function constraintPrefix() : string {
		return 'smliser_pricingtiers_';
	}
}
