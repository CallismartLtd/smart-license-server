<?php
/**
 * Failed Jobs Schema definition file.
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
 * Stores failed background jobs for inspection and retries.
 * 
 * @since 0.2.0
 */
class FailedJobsSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Failed Jobs';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores failed jobs.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_FAILED_JOBS_TABLE;
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

            Column::make( 'job_id' )
                ->type( 'bigint' )
                ->unsigned()
                ->required(),

            Column::make( 'job_class' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'queue' )
                ->type( 'varchar' )
                ->size( 50 )
                ->required(),

            Column::make( 'payload' )
                ->type( 'json' )
                ->required(),

            Column::make( 'error_message' )
                ->type( 'text' )
                ->default( null ),

            Column::make( 'failed_at' )
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
            Constraint::make( 'index' )->name( 'idx_job_id' )->on( 'job_id' ),
            Constraint::make( 'index' )->name( 'idx_failed_at' )->on( 'failed_at' ),
            Constraint::make( 'index' )->name( 'idx_job_class' )->on( 'job_class' ),
        ];
    }
}