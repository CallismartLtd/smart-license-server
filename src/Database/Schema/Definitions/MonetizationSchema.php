<?php
/**
 * Monetization Table Schema definition file.
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
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores monetization settings.
 * 
 * @since 0.2.0
 */
class MonetizationSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Monetization';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores monetization settings.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_MONETIZATION_TABLE;
    }

    /**
     * @inheritDoc
     */
    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->auto_increment()
                ->required(),

            Column::make( 'app_type' )
                ->type( ColumnType::VARCHAR )
                ->size( 50 )
                ->required(),

            Column::make( 'app_id' )
                ->type( ColumnType::BIG_INT )
                ->required(),

            Column::make( 'enabled' )
                ->type( ColumnType::INTEGER )
                ->size( 1 )
                ->default( 0 ),

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
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'unique' )
                ->name( 'unique_app_monetization' )
                ->on( 'app_type', 'app_id' ),
        ];
    }
}