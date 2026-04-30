<?php
/**
 * Software Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

defined( 'SMLISER_ABSPATH' ) || exit;

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
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'unique' )->on( 'slug' ),
            Constraint::make( 'index' )->name( 'software_slug_index' )->on( 'slug' ),
            Constraint::make( 'index' )->name( 'software_author_index' )->on( 'author' ),
        ];
    }
}
