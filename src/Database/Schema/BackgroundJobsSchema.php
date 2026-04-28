<?php
/**
 * Background Jobs Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores queued jobs.
 */
class BackgroundJobsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_BACKGROUND_JOBS_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name'            => 'id',
                'type'            => 'bigint',
                'unsigned'        => true,
                'nullable'        => false,
                'auto_increment'  => true,
            ],
            [
                'name'      => 'job_class',
                'type'      => 'varchar',
                'length'    => 255,
                'nullable'  => false,
            ],
            [
                'name'      => 'queue',
                'type'      => 'varchar',
                'length'    => 50,
                'nullable'  => false,
                'default'   => 'default',
            ],
            [
                'name'      => 'priority',
                'type'      => 'tinyint',
                'unsigned'  => true,
                'nullable'  => false,
                'default'   => 5,
            ],
            [
                'name'      => 'status',
                'type'      => 'varchar',
                'length'    => 20,
                'nullable'  => false,
                'default'   => 'pending',
            ],
            [
                'name'      => 'payload',
                'type'      => 'json',
                'nullable'  => false,
            ],
            [
                'name'      => 'attempts',
                'type'      => 'tinyint',
                'unsigned'  => true,
                'nullable'  => false,
                'default'   => 0,
            ],
            [
                'name'      => 'max_attempts',
                'type'      => 'tinyint',
                'unsigned'  => true,
                'nullable'  => false,
                'default'   => 3,
            ],
            [
                'name'      => 'available_at',
                'type'      => 'datetime',
                'nullable'  => false,
            ],
            [
                'name'      => 'started_at',
                'type'      => 'datetime',
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'completed_at',
                'type'      => 'datetime',
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'created_at',
                'type'      => 'datetime',
                'nullable'  => false,
            ],
            [
                'name'      => 'result',
                'type'      => 'json',
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'error_message',
                'type'      => 'text',
                'nullable'  => true,
                'default'   => null,
            ],
        ];
    }

    public static function get_constraints() : array {
        return [
            [
                'type'    => 'primary',
                'columns' => [ 'id' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'idx_queue_status_available',
                'columns' => [ 'queue', 'status', 'available_at' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'idx_status',
                'columns' => [ 'status' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'idx_started_at',
                'columns' => [ 'started_at' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'idx_completed_at',
                'columns' => [ 'completed_at' ],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Background Jobs';
    }

    public static function get_description() : string {
        return 'Stores background jobs.';
    }
}