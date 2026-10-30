<?php
/**
 * App Meta Schema definition file.
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
 * Stores key-value metadata for hosted apps of every type.
 *
 * `app_id` is the ID of the app in the apps table, which is unique across
 * all types.
 */
class AppMetaSchema implements DatabaseSchemaInterface {

	public static function get_label() : string {
		return 'App Meta';
	}

	public static function get_description() : string {
		return 'Stores key-value metadata for hosted apps.';
	}

	public static function get_table_name() : string {
		return TableName::APP_META->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->auto_increment()
				->required(),

			Column::make( 'app_id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->required(),

			Column::make( 'meta_key' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->required(),

			Column::make( 'meta_value' )
				->type( ColumnType::LONG_TEXT ),
		];
	}

	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),
			Constraint::index( "{$prefx}app_key_index" )->on( 'app_id', 'meta_key' ),
			Constraint::index( "{$prefx}meta_key_index" )->on( 'meta_key' ),
		];
	}

	protected static function constraintPrefix() : string {
		return 'smliser_app_meta_';
	}
}
