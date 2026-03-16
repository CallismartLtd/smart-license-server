<?php
/**
 * Log license activity job class file.
 *
 * Moves the synchronous read-modify-write in
 * RepositoryAnalytics::log_license_activity() off the request
 * lifecycle and into the background queue.
 *
 * ## Dispatch site (in RepositoryAnalytics::log_license_activity())
 *
 *   // Replace the synchronous write with a dispatch:
 *   smliser_job_queue()->dispatch(
 *       JobDTO::make(
 *           job_class : LogLicenseActivityJob::class,
 *           payload   : [
 *               'license_id' => $data['license_id'] ?? 'N/A',
 *               'event_type' => $data['event_type'] ?? 'activation',
 *               'ip_address' => $data['ip_address'] ?? smliser_get_client_ip(),
 *               'user_agent' => $data['user_agent'] ?? smliser_get_user_agent(),
 *               'website'    => $data['website']    ?? 'N/A',
 *               'comment'    => $data['comment']    ?? 'N/A',
 *               'duration'   => $data['duration']   ?? 'N/A',
 *           ],
 *           queue : JobDTO::QUEUE_DEFAULT,
 *       )
 *   );
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Analytics
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Analytics;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Background\Jobs\JobHandlerInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Asynchronously appends a license activity entry to the
 * settings-backed activity log.
 *
 * Offloading the read-modify-write from the request path prevents
 * every license event from blocking on a settings adapter read and write.
 */
class LogLicenseActivityJob implements JobHandlerInterface {

    /*
    |----------------------
    | JobHandlerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Expected payload keys:
     *   - license_id (string|int) The license identifier.
     *   - event_type (string)     Structured event type e.g. 'activation'.
     *   - ip_address (string)     Client IP address.
     *   - user_agent (string)     Client user agent string.
     *   - website    (string)     Client website or domain.
     *   - comment    (string)     Optional comment.
     *   - duration   (string)     Optional duration of the operation.
     *
     * @param array<string, mixed> $payload
     * @return bool True on success.
     */
    public function handle( array $payload ): mixed {
        RepositoryAnalytics::log_license_activity( $payload );
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'Log License Activity';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Asynchronously appends a license activity entry to the activity log.';
    }
}