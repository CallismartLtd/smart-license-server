<?php
/**
 * Organizations Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

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
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}name" )->on( 'display_name' ),
            Constraint::index( "{$prefx}slug" )->on( 'slug' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_orgshema_';
    }
}