<?php
/**
 * Users Schema definition file.
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
 * Stores authentication credentials and user account details.
 * 
 * @since 0.2.0
 */
class UsersSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Users';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores human user accounts with credentials.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_USERS_TABLE;
    }

    /**
     * @inheritDoc
     */
    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( 'bigint' )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'display_name' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'email' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'password_hash' )
                ->type( 'varchar' )
                ->size( 300 )
                ->required(),

            Column::make( 'status' )
                ->type( 'varchar' )
                ->size( 20 )
                ->default( 'active' )
                ->required(),

            Column::make( 'created_at' )
                ->type( 'datetime' ),

            Column::make( 'updated_at' )
                ->type( 'datetime' )
                ->required(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),

            Constraint::make( 'unique' )
                ->name( 'smliser_users_email_unique' )
                ->on( 'email' ),

            Constraint::make( 'index' )
                ->name( 'smliser_users_created_at' )
                ->on( 'created_at' ),

            Constraint::make( 'index' )
                ->name( 'smliser_users_updated_at' )
                ->on( 'updated_at' ),
        ];
    }
}