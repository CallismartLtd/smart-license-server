<?php
/**
 * Users Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Utils\Column;
use SmartLicenseServer\Database\Utils\Constraint;
use SmartLicenseServer\Database\Utils\ColumnType;

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
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'display_name' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'email' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'password_hash' )
                ->type( ColumnType::VARCHAR )
                ->size( 300 )
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

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),

            Constraint::unique( "{$prefx}email_unique" )->on( 'email' ),

            Constraint::index( "{$prefx}created_at" )->on( 'created_at' ),

            Constraint::index( "{$prefx}updated_at" )->on( 'updated_at' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_users_';
    }
}