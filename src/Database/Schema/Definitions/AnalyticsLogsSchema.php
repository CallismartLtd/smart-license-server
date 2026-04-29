<?php
/**
 * Analytics Logs Table Schema
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
 * Schema definition for analytics logs table.
 *
 * Acts as the raw event store for all analytics tracking.
 * 
 * @since 0.2.0
 */
class AnalyticsLogsSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Analytics Logs';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores raw analytics events - source of truth for event data.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_ANALYTICS_LOGS_TABLE;
    }

    /**
     * @inheritDoc
     */
    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( 'bigint' )
                ->size( 20 )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'app_type' )
                ->type( 'varchar' )
                ->size( 20 )
                ->required(),

            Column::make( 'app_slug' )
                ->type( 'varchar' )
                ->size( 100 )
                ->required(),

            Column::make( 'event_type' )
                ->type( 'varchar' )
                ->size( 50 )
                ->required(),

            Column::make( 'fingerprint' )
                ->type( 'char' )
                ->size( 64 )
                ->default( null ),

            Column::make( 'created_at' )
                ->type( 'datetime' )
                ->default( 'CURRENT_TIMESTAMP' ),
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
                ->name( 'app_identity_idx' )
                ->on( 'app_type', 'app_slug' ),

            Constraint::make( 'index' )
                ->name( 'event_type_idx' )
                ->on( 'event_type' ),

            Constraint::make( 'index' )
                ->name( 'lookup_idx' )
                ->on( 'app_slug', 'event_type', 'created_at' ),

            Constraint::make( 'index' )
                ->name( 'cleanup_idx' )
                ->on( 'created_at' ),
        ];
    }
}