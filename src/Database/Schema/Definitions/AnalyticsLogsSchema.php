<?php
/**
 * Analytics Logs Table Schema
 *
 * Defines the structure for high-volume event logging.
 *
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Schema definition for analytics logs table.
 *
 * Acts as the raw event store for all analytics tracking.
 */
class AnalyticsLogsSchema extends AbstractDatabaseSchema {

    /**
     * {@inheritDoc}
     */
    public static function get_table_name() : string {
        return SMLISER_ANALYTICS_LOGS_TABLE;
    }

    /**
     * {@inheritDoc}
     */
    public static function get_columns() : array {
        return [
            [
                'name'           => 'id',
                'type'           => 'bigint',
                'length'         => 20,
                'unsigned'       => true,
                'nullable'       => false,
                'auto_increment' => true,
            ],
            [
                'name'     => 'app_type',
                'type'     => 'varchar',
                'length'   => 20,
                'nullable' => false,
            ],
            [
                'name'     => 'app_slug',
                'type'     => 'varchar',
                'length'   => 100,
                'nullable' => false,
            ],
            [
                'name'     => 'event_type',
                'type'     => 'varchar',
                'length'   => 50,
                'nullable' => false,
            ],
            [
                'name'     => 'fingerprint',
                'type'     => 'char',
                'length'   => 64,
                'nullable' => true,
                'default'  => null,
            ],
            [
                'name'     => 'created_at',
                'type'     => 'datetime',
                'nullable' => true,
                'default'  => 'CURRENT_TIMESTAMP',
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function get_constraints() : array {
        return [
            [
                'type'    => 'primary',
                'columns' => [ 'id' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'app_identity_idx',
                'columns' => [ 'app_type', 'app_slug' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'event_type_idx',
                'columns' => [ 'event_type' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'lookup_idx',
                'columns' => [ 'app_slug', 'event_type', 'created_at' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'cleanup_idx',
                'columns' => [ 'created_at' ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function get_options() : array {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public static function get_label() : string {
        return 'Analytics Logs';
    }

    /**
     * {@inheritDoc}
     */
    public static function get_description() : string {
        return 'Stores raw analytics events - source of truth for event data.';
    }
}