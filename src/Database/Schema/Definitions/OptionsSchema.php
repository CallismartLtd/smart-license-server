<?php
/**
 * Options Schema definition file.
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
 * Stores global configuration options.
 * 
 * @since 0.2.0
 */
class OptionsSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Options';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores global configuration.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_OPTIONS_TABLE;
    }

    /**
     * @inheritDoc
     */
    public static function get_columns() : array {
        return [
            Column::make( 'option_id' )
                ->type( 'bigint' )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'option_name' )
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
            Constraint::make( 'primary' )->on( 'option_id' ),
            Constraint::make( 'index' )
                ->name( 'smliser_option_key' )
                ->on( 'option_name' ),
        ];
    }
}