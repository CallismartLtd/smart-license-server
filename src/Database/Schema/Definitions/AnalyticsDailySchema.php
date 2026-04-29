<?php
/**
 * Analytics Daily Table Schema
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores aggregated analytics.
 */
class AnalyticsDailySchema extends AbstractDatabaseSchema {

    /**
     * {@inheritDoc}
     */
    public static function get_table_name() : string {
        return SMLISER_ANALYTICS_DAILY_TABLE;
    }

    /**
     * {@inheritDoc}
     */
    public static function get_columns() : array {
        return [
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
                'name'     => 'stats_date',
                'type'     => 'date',
                'nullable' => false,
            ],
            [
                'name'     => 'event_type',
                'type'     => 'varchar',
                'length'   => 50,
                'nullable' => false,
            ],
            [
                'name'      => 'total_count',
                'type'      => 'int',
                'length'    => 10,
                'unsigned'  => true,
                'nullable'  => false,
                'default'   => 0,
            ],
            [
                'name'      => 'unique_count',
                'type'      => 'int',
                'length'    => 10,
                'unsigned'  => true,
                'nullable'  => false,
                'default'   => 0,
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
                'columns' => [
                    'app_type',
                    'app_slug',
                    'stats_date',
                    'event_type',
                ],
            ],
            [
                'type'    => 'index',
                'name'    => 'date_lookup',
                'columns' => [ 'stats_date' ],
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
        return 'Analytics Daily';
    }

    /**
     * {@inheritDoc}
     */
    public static function get_description() : string {
        return 'Stores daily analytics aggregates.';
    }
}