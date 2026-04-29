<?php
/**
 * Analytics Daily Table Schema
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
 * Stores aggregated analytics.
 * 
 * @since 0.2.0
 */
class AnalyticsDailySchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Analytics Daily';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores daily analytics aggregates.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_ANALYTICS_DAILY_TABLE;
    }

    /**
     * @inheritDoc
     */
    public static function get_columns() : array {
        return [
            Column::make( 'app_type' )
                ->type( 'varchar' )
                ->size( 20 )
                ->required(),

            Column::make( 'app_slug' )
                ->type( 'varchar' )
                ->size( 100 )
                ->required(),

            Column::make( 'stats_date' )
                ->type( 'date' )
                ->required(),

            Column::make( 'event_type' )
                ->type( 'varchar' )
                ->size( 50 )
                ->required(),

            Column::make( 'total_count' )
                ->type( 'int' )
                ->size( 10 )
                ->unsigned()
                ->required()
                ->default( 0 ),

            Column::make( 'unique_count' )
                ->type( 'int' )
                ->size( 10 )
                ->unsigned()
                ->required()
                ->default( 0 ),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )
                ->on( 'app_type', 'app_slug', 'stats_date', 'event_type' ),

            Constraint::make( 'index' )
                ->name( 'date_lookup' )
                ->on( 'stats_date' ),
        ];
    }
}