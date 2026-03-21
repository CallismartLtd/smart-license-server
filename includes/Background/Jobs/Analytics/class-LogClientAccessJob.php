<?php
/**
 * Log client access job class file.
 *
 * Moves the synchronous DB insert and meta counter update from
 * AppsAnalytics::log_client_access() off the request lifecycle
 * and into the background queue.
 *
 * ## Dispatch site (in AppsAnalytics::log_client_access())
 *
 *   // Replace the synchronous write with a dispatch:
 *   smliser_job_queue()->dispatch(
 *       JobDTO::make(
 *           job_class : LogClientAccessJob::class,
 *           payload   : [
 *               'app_type'   => $app->get_type(),
 *               'app_slug'   => $app->get_slug(),
 *               'event_type' => $event_type,
 *               'fingerprint'=> hash( 'sha256', smliser_get_client_ip() . '|' . smliser_get_user_agent( true ) ),
 *               'created_at' => gmdate( 'Y-m-d H:i:s' ),
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

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Asynchronously inserts an analytics log entry and increments
 * the app's lifetime counter meta value.
 *
 * Offloading this from the request path eliminates the per-request
 * DB write that was slowing down every API call.
 */
class LogClientAccessJob implements JobHandlerInterface {

    /*
    |----------------------
    | JobHandlerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Expected payload keys:
     *   - app_type    (string) The hosted app type e.g. 'plugin', 'theme', 'software'.
     *   - app_slug    (string) The hosted app slug.
     *   - event_type  (string) The event type e.g. 'download', 'activation'.
     *   - fingerprint (string) SHA-256 hash of IP + user agent.
     *   - created_at  (string) Y-m-d H:i:s datetime of the original request.
     *
     * @param array<string, mixed> $payload
     * @return bool True on successful insert, false otherwise.
     */
    public function handle( array $payload ): mixed {
        $app_type    = (string) ( $payload['app_type']    ?? '' );
        $app_slug    = (string) ( $payload['app_slug']    ?? '' );
        $event_type  = (string) ( $payload['event_type']  ?? '' );
        $fingerprint = (string) ( $payload['fingerprint'] ?? '' );
        $created_at  = (string) ( $payload['created_at']  ?? gmdate( 'Y-m-d H:i:s' ) );

        if ( $app_type === '' || $app_slug === '' || $event_type === '' ) {
            return false;
        }

        $db = smliser_db();

        // Insert the raw analytics log entry.
        $inserted = $db->insert( SMLISER_ANALYTICS_LOGS_TABLE, [
            'app_type'    => $app_type,
            'app_slug'    => $app_slug,
            'event_type'  => $event_type,
            'fingerprint' => $fingerprint,
            'created_at'  => $created_at,
        ] );

        if ( ! $inserted ) {
            return false;
        }

        // Resolve the app instance and increment its lifetime counter meta.
        $app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( $app === null ) {
            return true; // Log inserted — meta update is best-effort.
        }

        $meta_key      = ( 'download' === $event_type )
            ? AppsAnalytics::DOWNLOAD_COUNT_META_KEY
            : AppsAnalytics::CLIENT_ACCESS_META_KEY;

        $current_total = (int) $app->get_meta( $meta_key, 0 );
        $app->update_meta( $meta_key, $current_total + 1 );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'Log Client Access';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Asynchronously writes an analytics log entry and updates the app lifetime access counter.';
    }
}