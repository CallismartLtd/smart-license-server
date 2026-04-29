<?php
/**
 * User Options Schema definition file.
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
 * Stores user-specific preferences and configuration.
 * 
 * @since 0.2.0
 */
class UserOptionsSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'User Options';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores user preferences.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_USER_OPTIONS_TABLE;
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

            Column::make( 'user_id' )
                ->type( 'bigint' )
                ->unsigned()
                ->required(),

            Column::make( 'option_key' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'option_value' )
                ->type( 'longtext' ),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),

            Constraint::make( 'unique' )
                ->name( 'smliser_user_options_unique' )
                ->on( 'user_id', 'option_key' ),

            Constraint::make( 'index' )
                ->name( 'option_key_index' )
                ->on( 'option_key' ),
        ];
    }
}