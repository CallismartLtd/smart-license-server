<?php
/**
 * Prune license activity logs job class file.
 *
 * Replaces the inline pruning that currently runs inside
 * RepositoryAnalytics::get_license_activity_logs() on every read.
 * Moving it here means reads are never blocked by a write operation.
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

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;

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
     *   - retention_months (int) Entries older than this are removed. Default 3.
     *
     * @param array<string, mixed> $payload
     * @return int Number of log entries pruned.
     */
    public function handle( array $payload ): mixed {
        $retention_months = (int) ( $payload['retention_months'] ?? 3 );
        $expiration       = time() - ( $retention_months * MONTH_IN_SECONDS );

        $logs = smliser_settings_adapter()->get(
            \SmartLicenseServer\Analytics\RepositoryAnalytics::LICENSE_ACTIVITY_KEY,
            [],
            true
        );

        if ( empty( $logs ) || ! is_array( $logs ) ) {
            return 0;
        }

        $pruned = 0;

        foreach ( $logs as $time => $entry ) {
            if ( strtotime( $time ) < $expiration ) {
                unset( $logs[ $time ] );
                $pruned++;
            }
        }

        if ( $pruned > 0 ) {
            smliser_settings_adapter()->set(
                \SmartLicenseServer\Analytics\RepositoryAnalytics::LICENSE_ACTIVITY_KEY,
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