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

defined( 'SMLISER_ABSPATH' ) || exit;

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
    public function handle( array $payload ): mixed {
        $retention_days = (int) ( $payload['retention_days'] ?? 90 );
        $cutoff         = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        $result = smliser_db()->query(
            'DELETE FROM ' . SMLISER_ANALYTICS_LOGS_TABLE . ' WHERE created_at < ?',
            [ $cutoff ]
        );

        if ( $result === false ) {
            return 0;
        }

        // Return affected row count if the statement supports it.
        if ( is_object( $result ) && method_exists( $result, 'rowCount' ) ) {
            return (int) $result->rowCount();
        }

        return 0;
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