<?php
/**
 * Roles Table Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;

defined( 'SMLISER_ABSPATH' ) || exit;

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
                ->type( 'bigint' )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'slug' )
                ->type( 'varchar' )
                ->size( 64 )
                ->required(),

            Column::make( 'label' )
                ->type( 'varchar' )
                ->size( 190 )
                ->required(),

            Column::make( 'is_canonical' )
                ->type( 'tinyint' )
                ->size( 1 )
                ->default( 0 ),

            Column::make( 'created_at' )
                ->type( 'datetime' ),

            Column::make( 'updated_at' )
                ->type( 'datetime' )
                ->required(),
        ];
    }

    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'unique' )
                ->name( 'smliser_owner_role_unique' )
                ->on( 'slug' ),
            Constraint::make( 'index' )
                ->name( 'smliser_roles_name' )
                ->on( 'slug' ),
        ];
    }
}