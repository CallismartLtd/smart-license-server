<?php
/**
 * License Table Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package Callismart\DBPrism\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Stores core license records.
 *
 * A license is the contract: what it grants (the app, the number of
 * domains, its term and features) is held here, copied from the pricing
 * tier it was issued from, and edited here afterwards.
 *
 * Where it came from: `tier_id` (informational), and `provider_id` with
 * `external_ref`, the provider's own reference for the sale. A provider and
 * reference identify one license; licenses without a reference (NULL) are
 * not constrained.
 *
 * Who holds it: `licensee_email` receives the license and is the only
 * value used to link a registered user (`user_id`). `service_id` is the
 * identifier a client activates with, together with the license key.
 * 
 * @since 0.2.0
 */
class LicenseSchema implements DatabaseSchemaInterface {

	/**
	 * @inheritDoc
	 */
	public static function get_label() : string {
		return 'Licenses';
	}

	/**
	 * @inheritDoc
	 */
	public static function get_description() : string {
		return 'Stores license records with keys, service identifiers, and validity dates.';
	}

	/**
	 * @inheritDoc
	 */
	public static function get_table_name() : string {
		return TableName::LICENSES->value;
	}

	/**
	 * @inheritDoc
	 */
	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->auto_increment()
				->required(),

			Column::make( 'licensee_fullname' )
				->type( ColumnType::STRING )
				->size( 512 ),

			Column::make( 'license_key' )
				->type( ColumnType::STRING )
				->size( 300 )
				->required(),

			Column::make( 'service_id' )
				->type( ColumnType::STRING )
				->size( 300 )
				->required(),

			Column::make( 'licensee_email' )
				->type( ColumnType::STRING )
				->size( 255 ),

			Column::make( 'user_id' )
				->type( ColumnType::BIG_INT )
				->unsigned(),

			Column::make( 'app_prop' )
				->type( ColumnType::STRING )
				->size( 600 ),

			Column::make( 'tier_id' )
				->type( ColumnType::BIG_INT ),

			Column::make( 'provider_id' )
				->type( ColumnType::STRING )
				->size( 100 ),

			Column::make( 'external_ref' )
				->type( ColumnType::STRING )
				->size( 191 ),

			Column::make( 'features' )
				->type( ColumnType::TEXT ),

			Column::make( 'max_allowed_domains' )
				->type( ColumnType::BIG_INT )
				->size( 20 ),

			Column::make( 'status' )
				->type( ColumnType::STRING )
				->size( 30 ),

			Column::make( 'start_date' )
				->type( ColumnType::DATETIME ),

			Column::make( 'end_date' )
				->type( ColumnType::DATETIME ),

			Column::make( 'created_at' )
				->type( ColumnType::DATETIME ),

			Column::make( 'updated_at' )
				->type( ColumnType::DATETIME )
				->required(),
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),
			Constraint::unique( "{$prefx}unique" )->on( 'license_key' ),
			Constraint::index( "{$prefx}service_id_index" )->on( 'service_id' ),
			Constraint::index( "{$prefx}status_index" )->on( 'status' ),
			Constraint::index( "{$prefx}licensee_email_index" )->on( 'licensee_email' ),
			Constraint::index( "{$prefx}user_id_index" )->on( 'user_id' ),
			Constraint::unique( "{$prefx}source_unique" )->on( 'provider_id', 'external_ref' ),
		];
	}

	protected static function constraintPrefix() : string {
		return 'smliser_licenses_';
	}
}
