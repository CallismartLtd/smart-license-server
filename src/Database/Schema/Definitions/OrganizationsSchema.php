<?php
/**
 * Organizations Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

defined( 'SMLISER_ABSPATH' ) || exit;

class OrganizationsSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Organizations';
    }

    public static function get_description() : string {
        return 'Stores organizations.';
    }

    public static function get_table_name() : string {
        return SMLISER_ORGANIZATIONS_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'display_name' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'slug' )
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
            Constraint::make( 'index' )->name( 'organization_name' )->on( 'display_name' ),
            Constraint::make( 'index' )->name( 'organization_slug' )->on( 'slug' ),
        ];
    }
}