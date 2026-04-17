<?php
/**
 * Prune license activity logs job class file.
 *
 * ## Recommended schedule
 *
 *   Run nightly via cron or WordPress scheduled event.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Analytics
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Analytics;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\Dates\TimestampValue;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Prunes license activity log entries older than the configured
 * retention window from the settings-backed activity store.
 *
 * After dispatching this job, the inline pruning logic inside
 * get_license_activity_logs() should be removed so reads are clean.
 */
class PruneLicenseActivityLogsJob implements JobHandlerInterface {

    /*
    |----------------------
    | JobHandlerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Expected payload keys:
     *   - retention_days (int) Entries older than this are removed. Default 3.
     *
     * @param array<string, mixed> $payload
     * @return int Number of log entries pruned.
     */
    public function handle( array $payload = [] ): mixed {
        $logs   = RepositoryAnalytics::get_license_activity_logs();

        if ( empty( $logs ) || ! is_array( $logs ) ) {
            return 0;
        }

        $default_retention  = (int) \smliser_settings()->get( 'log_retention_days', 30, true );
        $retention_days     = (int) ( $payload['retention_days'] ?? $default_retention );
        $pruned             = 0;

        foreach ( $logs as $i => $entry ) {
            $timestamp   = TimestampValue::fromTimestamp( $entry['created_at'] ?? 0 );

            if ( $timestamp->addDays( $retention_days )->isPast() ) {
                unset( $logs[ $i ] );
                $pruned++;
            }
        }

        if ( $pruned > 0 ) {
            smliser_settings()->set(
                RepositoryAnalytics::LICENSE_ACTIVITY_KEY,
                $logs,
                true
            );
        }

        return $pruned;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'Prune License Activity Logs';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Removes license activity log entries older than the configured retention window.';
    }
}