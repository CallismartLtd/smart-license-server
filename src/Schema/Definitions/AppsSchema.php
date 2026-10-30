<?php
/**
 * Apps Schema definition file.
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
 * Stores every hosted app, whatever its type.
 *
 * An app is one record and its type is a value: the `app_type` column holds
 * the registered type ("wpplugin", "wptheme", "software" or a custom type).
 * Data that only some types have is stored in the app meta table. An app's
 * ID is unique across all types; its slug is unique within its type.
 */
class AppsSchema implements DatabaseSchemaInterface {

	public static function get_label() : string {
		return 'Apps';
	}

	public static function get_description() : string {
		return 'Stores hosted apps of every type.';
	}

	public static function get_table_name() : string {
		return TableName::APPS->value;
	}

	public static function get_columns() : array {
		return [
			Column::make( 'id' )
				->type( ColumnType::BIG_INT )
				->unsigned()
				->auto_increment(),

			Column::make( 'app_type' )
				->type( ColumnType::VARCHAR )
				->size( 50 )
				->required(),

			Column::make( 'owner_id' )
				->type( ColumnType::BIG_INT )
				->default( 0 ),

			Column::make( 'name' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->required(),

			Column::make( 'slug' )
				->type( ColumnType::VARCHAR )
				->size( 300 )
				->required(),

			Column::make( 'status' )
				->type( ColumnType::VARCHAR )
				->size( 55 )
				->default( 'active' )
				->required(),

			Column::make( 'author' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->default( NULL ),

			Column::make( 'author_profile' )
				->type( ColumnType::VARCHAR )
				->size( 255 )
				->default( NULL ),

			Column::make( 'download_link' )
				->type( ColumnType::VARCHAR )
				->size( 400 )
				->default( NULL ),

			Column::make( 'created_at' )
				->type( ColumnType::DATETIME ),

			Column::make( 'updated_at' )
				->type( ColumnType::DATETIME ),
		];
	}

	public static function get_constraints() : array {
		$prefx = static::constraintPrefix();
		return [
			Constraint::primary( "{$prefx}primary" )->on( 'id' ),
			Constraint::unique( "{$prefx}type_slug_unique" )->on( 'app_type', 'slug' ),
			Constraint::index( "{$prefx}type_status_index" )->on( 'app_type', 'status' ),
			Constraint::index( "{$prefx}owner_index" )->on( 'owner_id' ),
			Constraint::index( "{$prefx}name_index" )->on( 'name' ),
			Constraint::index( "{$prefx}slug_index" )->on( 'slug' ),
			Constraint::index( "{$prefx}author_index" )->on( 'author' ),
		];
	}

	protected static function constraintPrefix() : string {
		return 'smliser_apps_';
	}
}
