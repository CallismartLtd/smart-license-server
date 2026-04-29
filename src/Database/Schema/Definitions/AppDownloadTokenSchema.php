<?php
/**
 * App Download Token Schema definition file.
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
 * Stores download tokens.
 * 
 * @since 0.2.0
 */
class AppDownloadTokenSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'App Download Tokens';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores download tokens.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_APP_DOWNLOAD_TOKEN_TABLE;
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

            Column::make( 'app_prop' )
                ->type( 'varchar' )
                ->size( 255 )
                ->default( null ),

            Column::make( 'license_key' )
                ->type( 'varchar' )
                ->size( 255 )
                ->default( null ),

            Column::make( 'token' )
                ->type( 'varchar' )
                ->size( 255 )
                ->default( null ),

            Column::make( 'expiry' )
                ->type( 'int' )
                ->required(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )
                ->on( 'id' ),

            Constraint::make( 'index' )
                ->name( 'expiry_index' )
                ->on( 'expiry' ),

            Constraint::make( 'index' )
                ->name( 'dtoken_index' )
                ->on( 'token' ),
        ];
    }
}