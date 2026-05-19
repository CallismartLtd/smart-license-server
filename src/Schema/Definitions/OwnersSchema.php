<?php
/**
 * Owners Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Utils\Column;
use SmartLicenseServer\Database\Utils\Constraint;
use SmartLicenseServer\Database\Utils\ColumnType;

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
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}subject_id" )->on( 'subject_id' ),
            Constraint::index( "{$prefx}created_at" )->on( 'created_at' ),
            Constraint::index( "{$prefx}updated_at" )->on( 'updated_at' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_ownersshema_';
    }
}