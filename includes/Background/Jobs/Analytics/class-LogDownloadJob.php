<?php
/**
 * Log download job class file.
 *
 * Moves the synchronous DB insert and download counter update from
 * AppsAnalytics::log_download() off the request lifecycle and into
 * the background queue.
 *
 * This is a distinct event from LogClientAccessJob — it tracks download
 * count specifically via DOWNLOAD_COUNT_META_KEY, whereas LogClientAccessJob
 * tracks general client access via CLIENT_ACCESS_META_KEY.
 *
 * Both jobs are dispatched independently for a download event, mirroring
 * the original two-callback design in FileRequestController:
 *
 *   $response->register_after_serve_callback( [AppsAnalytics::class, 'log_download'], [$app] );
 *   $response->register_after_serve_callback( [AppsAnalytics::class, 'log_client_access'], [$app, 'download'] );
 *
 * ## Dispatch site (in AppsAnalytics::log_download())
 *
 *   // Replace the delegation to log_client_access() with a direct dispatch:
 *   smliser_job_queue()->dispatch(
 *       JobDTO::make(
 *           job_class : LogDownloadJob::class,
 *           payload   : [
 *               'app_type'    => $app->get_type(),
 *               'app_slug'    => $app->get_slug(),
 *               'fingerprint' => hash( 'sha256', smliser_get_client_ip() . '|' . smliser_get_user_agent( true ) ),
 *               'created_at'  => gmdate( 'Y-m-d H:i:s' ),
 *           ],
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
 * Asynchronously inserts a download analytics log entry and increments
 * the app's lifetime download counter meta value.
 *
 * Intentionally separate from LogClientAccessJob — download count and
 * client access count are distinct metrics tracked independently.
 */
class LogDownloadJob implements JobHandlerInterface {

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
     *   - fingerprint (string) SHA-256 hash of IP + user agent.
     *   - created_at  (string) Y-m-d H:i:s datetime of the original request.
     *
     * @param array<string, mixed> $payload
     * @return bool True on successful insert, false otherwise.
     */
    public function handle( array $payload ): mixed {
        $app_type    = (string) ( $payload['app_type']    ?? '' );
        $app_slug    = (string) ( $payload['app_slug']    ?? '' );
        $fingerprint = (string) ( $payload['fingerprint'] ?? '' );
        $created_at  = (string) ( $payload['created_at']  ?? gmdate( 'Y-m-d H:i:s' ) );

        if ( $app_type === '' || $app_slug === '' ) {
            return false;
        }

        $db = smliser_dbclass();

        // Insert the raw download log entry.
        $inserted = $db->insert( SMLISER_ANALYTICS_LOGS_TABLE, [
            'app_type'    => $app_type,
            'app_slug'    => $app_slug,
            'event_type'  => 'download',
            'fingerprint' => $fingerprint,
            'created_at'  => $created_at,
        ] );

        if ( ! $inserted ) {
            return false;
        }

        // Resolve the app and increment its lifetime download counter.
        $app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( $app === null ) {
            return true; // Log inserted — meta update is best-effort.
        }

        $current_total = (int) $app->get_meta( AppsAnalytics::DOWNLOAD_COUNT_META_KEY, 0 );
        $app->update_meta( AppsAnalytics::DOWNLOAD_COUNT_META_KEY, $current_total + 1 );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'Log Download';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Asynchronously writes a download log entry and updates the app lifetime download counter.';
    }
}