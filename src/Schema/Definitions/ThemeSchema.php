<?php
/**
 * Theme Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Utils\Column;
use SmartLicenseServer\Database\Utils\Constraint;
use SmartLicenseServer\Database\Utils\ColumnType;

/**
 * Theme Schema
 */
class ThemeSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Themes';
    }

    public static function get_description() : string {
        return 'Stores theme information.';
    }

    public static function get_table_name() : string {
        return SMLISER_THEMES_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::INTEGER )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'owner_id' )
                ->type( ColumnType::BIG_INT ),

            Column::make( 'name' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'slug' )
                ->type( ColumnType::VARCHAR )
                ->size( 300 ),

            Column::make( 'author' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 ),

            Column::make( 'status' )
                ->type( ColumnType::VARCHAR )
                ->size( 55 )
                ->default( 'active' )
                ->required(),

            Column::make( 'download_link' )
                ->type( ColumnType::VARCHAR )
                ->size( 400 ),

            Column::make( 'created_at' )
                ->type( ColumnType::DATETIME ),

            Column::make( 'updated_at' )
                ->type( ColumnType::DATETIME )
                ->required(),
        ];
    }

    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}slug_index" )->on( 'slug' ),
            Constraint::index( "{$prefx}author_index" )->on( 'author' ),
            Constraint::index( "{$prefx}download_link_index" )->on( 'download_link' ),
            Constraint::index( "{$prefx}status_index" )->on( 'status' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_themes_';
    }
}