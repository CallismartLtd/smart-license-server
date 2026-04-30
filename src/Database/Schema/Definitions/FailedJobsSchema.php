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
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

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
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'job_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

            Column::make( 'job_class' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'queue' )
                ->type( ColumnType::VARCHAR )
                ->size( 50 )
                ->required(),

            Column::make( 'payload' )
                ->type( ColumnType::JSON )
                ->required(),

            Column::make( 'error_message' )
                ->type( ColumnType::TEXT )
                ->default( null ),

            Column::make( 'failed_at' )
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
            Constraint::make( 'index' )->name( 'idx_job_id' )->on( 'job_id' ),
            Constraint::make( 'index' )->name( 'idx_failed_at' )->on( 'failed_at' ),
            Constraint::make( 'index' )->name( 'idx_job_class' )->on( 'job_class' ),
        ];
    }
}