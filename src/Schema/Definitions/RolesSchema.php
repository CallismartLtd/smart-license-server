<?php
/**
 * Roles Table Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

/**
 * Stores roles definitions.
 */
class RolesSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Roles';
    }

    public static function get_description() : string {
        return 'Stores roles.';
    }

    public static function get_table_name() : string {
        return SMLISER_ROLES_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'slug' )
                ->type( ColumnType::VARCHAR )
                ->size( 64 )
                ->required(),

            Column::make( 'label' )
                ->type( ColumnType::VARCHAR )
                ->size( 190 )
                ->required(),

            Column::make( 'is_canonical' )
                ->type( ColumnType::INTEGER )
                ->size( 1 )
                ->default( 0 ),

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
            Constraint::unique( "{$prefx}owner_role_unique" )->on( 'slug' ),
            Constraint::index( "{$prefx}name" )->on( 'slug' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_rolesschema_';
    }
}