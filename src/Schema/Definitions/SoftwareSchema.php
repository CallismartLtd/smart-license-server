<?php
/**
 * Software Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Utils\Column;
use SmartLicenseServer\Database\Utils\Constraint;
use SmartLicenseServer\Database\Utils\ColumnType;

class SoftwareSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Software';
    }

    public static function get_description() : string {
        return 'Stores software records.';
    }

    public static function get_table_name() : string {
        return SMLISER_SOFTWARE_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
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
                ->size( 300 )
                ->required(),

            Column::make( 'status' )
                ->type( ColumnType::VARCHAR )
                ->size( 55 )
                ->default( 'active' )
                ->required(),

            Column::make( 'author' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 ),

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
            Constraint::unique( "{$prefx}unique" )->on( 'slug' ),
            Constraint::index( "{$prefx}slug_index" )->on( 'slug' ),
            Constraint::index( "{$prefx}author_index" )->on( 'author' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_softwaremeta_';
    }
}
