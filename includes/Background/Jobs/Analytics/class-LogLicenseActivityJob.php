<?php
/**
 * Log license activity job class file.
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
        $activities = smliser_settings()->get( RepositoryAnalytics::LICENSE_ACTIVITY_KEY, [] );
        if ( ! \is_array( $activities ) ) {
            $activities =   [];
        }
        
        $activities[]   = $payload;
        
        \smliser_settings()->set( RepositoryAnalytics::LICENSE_ACTIVITY_KEY, $activities );
        
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