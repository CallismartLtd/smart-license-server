<?php
/**
 * Prune analytics logs job class file.
 *
 * Keeps the analytics_logs table lean by deleting raw entries
 * older than the configured retention window. Should be run
 * periodically via cron — daily or weekly depending on traffic.
 *
 * ## Recommended schedule
 *
 *   Run weekly via cron or WordPress scheduled event.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Analytics
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Analytics;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\Dates\TimestampValue;

/**
 * Deletes raw analytics log entries from SMLISER_ANALYTICS_LOGS_TABLE
 * that are older than the configured retention period.
 *
 * This prevents the table from growing unbounded on high-traffic
 * installations without affecting the accuracy of recent analytics.
 */
class PruneAnalyticsLogsJob implements JobHandlerInterface {

    /*
    |----------------------
    | JobHandlerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Expected payload keys:
     *   - retention_days (int) Entries older than this are deleted. Default 90.
     *
     * @param array<string, mixed> $payload
     * @return int Number of rows deleted.
     */
    public function handle( array $payload = [] ): mixed {
        $default_retention  = (int) \smliser_settings()->get( 'log_retention_days', 90, true );
        $retention_days = (int) ( $payload['retention_days'] ?? $default_retention );
        $cutoff         = TimestampValue::now()->subtractDays( $retention_days )->format( 'Y-m-d H:i:s' );

        $sql    = \smliserQueryBuilder()
            ->delete( SMLISER_ANALYTICS_LOGS_TABLE )
            ->where( 'created_at', '<', $cutoff );

        $affected = (int) smliser_db()->execute( $sql->build(), $sql->get_bindings() );

        if ( ! $affected ) {
            return 0;
        }

        return $affected;
        
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'Prune Analytics Logs';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Deletes raw analytics log entries older than the configured retention period to keep the table lean.';
    }
}