<?php
/**
 * Service Accounts Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package Callismart\DBPrism\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Stores service accounts.
 * 
 * @since 0.2.0
 */
class ServiceAccountsSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Service Accounts';
    }

    public static function get_description() : string {
        return 'Stores service accounts.';
    }

    public static function get_table_name() : string {
        return SMLISER_SERVICE_ACCOUNTS_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::INTEGER )
                ->auto_increment()
                ->required(),

            Column::make( 'identifier' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'owner_id' )
                ->type( ColumnType::INTEGER )
                ->required(),

            Column::make( 'display_name' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'description' )
                ->type( ColumnType::TEXT ),

            Column::make( 'api_key_hash' )
                ->type( ColumnType::VARCHAR )
                ->size( 512 )
                ->required(),

            Column::make( 'status' )
                ->type( ColumnType::VARCHAR )
                ->size( 30 )
                ->default( 'active' )
                ->required(),

            Column::make( 'created_at' )
                ->type( ColumnType::DATETIME ),

            Column::make( 'updated_at' )
                ->type( ColumnType::DATETIME )
                ->required(),

            Column::make( 'last_used_at' )
                ->type( ColumnType::DATETIME ),
        ];
    }

    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}owner_id" )->on( 'owner_id' ),
            Constraint::index( "{$prefx}api_key_hash" )->on( 'api_key_hash' ),
            Constraint::index( "{$prefx}status" )->on( 'status' ),
            Constraint::index( "{$prefx}created_at" )->on( 'created_at' ),
            Constraint::index( "{$prefx}updated_at" )->on( 'updated_at' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_service_acc_';
    }
}