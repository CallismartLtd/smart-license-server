<?php
/**
 * Background Jobs Schema definition file.
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
 * Stores queued jobs for background processing.
 * 
 * @since 0.2.0
 */
class BackgroundJobsSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Background Jobs';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores background jobs.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_BACKGROUND_JOBS_TABLE;
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

            Column::make( 'job_class' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'queue' )
                ->type( 'varchar' )
                ->size( 50 )
                ->default( 'default' )
                ->required(),

            Column::make( 'priority' )
                ->type( 'tinyint' )
                ->unsigned()
                ->default( 5 )
                ->required(),

            Column::make( 'status' )
                ->type( 'varchar' )
                ->size( 20 )
                ->default( 'pending' )
                ->required(),

            Column::make( 'payload' )
                ->type( 'json' )
                ->required(),

            Column::make( 'attempts' )
                ->type( 'tinyint' )
                ->unsigned()
                ->default( 0 )
                ->required(),

            Column::make( 'max_attempts' )
                ->type( 'tinyint' )
                ->unsigned()
                ->default( 3 )
                ->required(),

            Column::make( 'available_at' )
                ->type( 'datetime' )
                ->required(),

            Column::make( 'started_at' )
                ->type( 'datetime' )
                ->default( null ),

            Column::make( 'completed_at' )
                ->type( 'datetime' )
                ->default( null ),

            Column::make( 'created_at' )
                ->type( 'datetime' )
                ->required(),

            Column::make( 'result' )
                ->type( 'json' )
                ->default( null ),

            Column::make( 'error_message' )
                ->type( 'text' )
                ->default( null ),
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
                ->name( 'idx_queue_status_available' )
                ->on( 'queue', 'status', 'available_at' ),

            Constraint::make( 'index' )
                ->name( 'idx_status' )
                ->on( 'status' ),

            Constraint::make( 'index' )
                ->name( 'idx_started_at' )
                ->on( 'started_at' ),

            Constraint::make( 'index' )
                ->name( 'idx_completed_at' )
                ->on( 'completed_at' ),
        ];
    }
}