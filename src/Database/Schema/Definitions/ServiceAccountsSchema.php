<?php
/**
 * Service Accounts Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;

defined( 'SMLISER_ABSPATH' ) || exit;

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
                ->type( 'int' )
                ->auto_increment()
                ->required(),

            Column::make( 'identifier' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'owner_id' )
                ->type( 'int' )
                ->required(),

            Column::make( 'display_name' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'description' )
                ->type( 'text' ),

            Column::make( 'api_key_hash' )
                ->type( 'varchar' )
                ->size( 512 )
                ->required(),

            Column::make( 'status' )
                ->type( 'varchar' )
                ->size( 30 )
                ->default( 'active' )
                ->required(),

            Column::make( 'created_at' )
                ->type( 'datetime' ),

            Column::make( 'updated_at' )
                ->type( 'datetime' )
                ->required(),

            Column::make( 'last_used_at' )
                ->type( 'datetime' ),
        ];
    }

    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'index' )->name( 'smliser_service_acct_owner_id' )->on( 'owner_id' ),
            Constraint::make( 'index' )->name( 'smliser_service_acct_api_key_hash' )->on( 'api_key_hash' ),
            Constraint::make( 'index' )->name( 'smliser_service_acct_status' )->on( 'status' ),
            Constraint::make( 'index' )->name( 'smliser_service_acct_created_at' )->on( 'created_at' ),
            Constraint::make( 'index' )->name( 'smliser_service_acct_updated_at' )->on( 'updated_at' ),
        ];
    }
}