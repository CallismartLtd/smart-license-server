<?php
/**
 * License Activations Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Definitions
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Stores the domains a license is activated on.
 *
 * One row per license and host. `secret_hash` is the HMAC of the site
 * secret handed to the site at activation; the site proves itself with
 * that secret on later requests.
 */
class LicenseActivationsSchema implements DatabaseSchemaInterface {

	public static function get_label() : string {
		return 'License Activations';
	}

	public static function get_description() : string {
		return 'Stores the domains each license is activated on.';
	}

	public static function get_table_name() : string {
		return TableName::LICENSE_ACTIVATIONS->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->auto_increment()
				->required(),

			Column::make( 'license_id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->required(),

			Column::make( 'host' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->required(),

			Column::make( 'origin' )
				->type( ColumnType::VARCHAR )
				->size( 400 )
				->required(),

			Column::make( 'secret_hash' )
				->type( ColumnType::VARCHAR )
				->size( 128 )
				->required(),

			Column::make( 'activated_at' )
				->type( ColumnType::DATETIME )
				->required(),
		];
	}

	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),
			Constraint::unique( "{$prefx}license_host_unique" )->on( 'license_id', 'host' ),
			Constraint::index( "{$prefx}host_index" )->on( 'host' ),
		];
	}

	protected static function constraintPrefix() : string {
		return 'smliser_license_activations_';
	}
}
