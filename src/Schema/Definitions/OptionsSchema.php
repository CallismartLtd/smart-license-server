<?php
/**
 * Options Schema definition file.
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
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'option_name' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'option_value' )
                ->type( ColumnType::LONG_TEXT ),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'option_id' ),
            Constraint::index( "{$prefx}option_key" )->on( 'option_name' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_licensemeta_';
    }
}