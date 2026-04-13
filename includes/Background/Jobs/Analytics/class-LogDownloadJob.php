<?php
/**
 * Log download job class file.
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

        $db = smliser_db();

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