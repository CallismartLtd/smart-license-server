<?php
/**
 * Plugin Table Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Stores core plugin information.
 */
class PluginSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Plugins';
    }

    public static function get_description() : string {
        return 'Stores plugin information.';
    }

    public static function get_table_name() : string {
        return SMLISER_PLUGINS_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::INTEGER )
                ->unsigned()
                ->auto_increment(),

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
                ->size( 300 )
                ->default( 'active' ),

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
                ->type( ColumnType::DATETIME )
        ];
    }

    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}name_index" )->on( 'name' ),
            Constraint::index( "{$prefx}download_link_index" )->on( 'download_link' ),
            Constraint::index( "{$prefx}slug_index" )->on( 'slug' ),
            Constraint::index( "{$prefx}author_index" )->on( 'author' ),
            Constraint::index( "{$prefx}status_index" )->on( 'status' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_plugins_';
    }
}