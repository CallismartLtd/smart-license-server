<?php
/**
 * Failed Jobs Schema definition file.
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
 * Stores failed background jobs for inspection and retries.
 *
 * Carries the full job envelope at the moment of failure — not just
 * the minimum needed to identify it — so this table can serve as a
 * genuine audit record (attempt count, run duration via started_at,
 * any partial result) rather than a stripped-down pointer back to
 * state that no longer exists once the active job row is removed.
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

            Column::make( 'priority' )
                ->type( ColumnType::INTEGER )
                ->unsigned()
                ->default( 5 )
                ->required(),

            Column::make( 'payload' )
                ->type( ColumnType::JSON )
                ->required(),

            Column::make( 'attempts' )
                ->type( ColumnType::INTEGER )
                ->unsigned()
                ->required(),

            Column::make( 'max_attempts' )
                ->type( ColumnType::INTEGER )
                ->unsigned()
                ->required(),

            Column::make( 'created_at' )
                ->type( ColumnType::DATETIME )
                ->required(),

            Column::make( 'started_at' )
                ->type( ColumnType::DATETIME )
                ->default( null ),

            Column::make( 'completed_at' )
                ->type( ColumnType::DATETIME )
                ->default( null ),

            Column::make( 'result' )
                ->type( ColumnType::JSON )
                ->default( null ),

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
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}job_id" )->on( 'job_id' ),
            Constraint::index( "{$prefx}failed_at" )->on( 'failed_at' ),
            Constraint::index( "{$prefx}job_class" )->on( 'job_class' ),
            Constraint::index( "{$prefx}attempts" )->on( 'attempts' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_faild_job_schema_';
    }
}