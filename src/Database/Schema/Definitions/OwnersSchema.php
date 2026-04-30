<?php
/**
 * Owners Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

defined( 'SMLISER_ABSPATH' ) || exit;

class OwnersSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Owners';
    }

    public static function get_description() : string {
        return 'Stores resource owners.';
    }

    public static function get_table_name() : string {
        return SMLISER_OWNERS_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'subject_id' )
               ->type( ColumnType::BIG_INT )
                ->required(),

            Column::make( 'type' )
                ->type( ColumnType::VARCHAR )
                ->size( 20 )
                ->default( 'platform' )
                ->required(),

            Column::make( 'name' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

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
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'index' )->name( 'smliser_owners_subject_id' )->on( 'subject_id' ),
            Constraint::make( 'index' )->name( 'smliser_owners_created_at' )->on( 'created_at' ),
            Constraint::make( 'index' )->name( 'smliser_owners_updated_at' )->on( 'updated_at' ),
        ];
    }
}