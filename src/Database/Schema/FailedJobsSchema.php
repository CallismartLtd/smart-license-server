<?php
/**
 * Failed Jobs Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores failed jobs.
 */
class FailedJobsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_FAILED_JOBS_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name'            => 'id',
                'type'            => 'bigint',
                'unsigned'        => true,
                'auto_increment'  => true,
                'nullable'        => false,
            ],
            [
                'name'      => 'job_id',
                'type'      => 'bigint',
                'unsigned'  => true,
                'nullable'  => false,
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
            ],
            [
                'name'      => 'payload',
                'type'      => 'json',
                'nullable'  => false,
            ],
            [
                'name'      => 'error_message',
                'type'      => 'text',
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'failed_at',
                'type'      => 'datetime',
                'nullable'  => false,
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
                'name'    => 'idx_job_id',
                'columns' => [ 'job_id' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'idx_failed_at',
                'columns' => [ 'failed_at' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'idx_job_class',
                'columns' => [ 'job_class' ],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Failed Jobs';
    }

    public static function get_description() : string {
        return 'Stores failed jobs.';
    }
}