<?php
/**
 * Send app updated email job class file.
 *
 * Dispatched when an existing app's zip file is updated.
 * Resolves the owner's email address and sends AppUpdatedEmail.
 *
 * Triggered from: AbstractHostedApp::save() — update branch,
 * after successful zip upload.
 *
 * Payload:
 *   app_type (string) — e.g. 'plugin', 'theme', 'software'.
 *   app_slug (string) — The app slug.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Apps
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Apps;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Email\Templates\Apps\AppUpdatedEmail;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Sends AppUpdatedEmail to the app owner when an app file is updated.
 */
class SendAppUpdatedEmailJob implements JobHandlerInterface {
    use OwnerEmailResolverTrait;

    public static function get_job_name(): string {
        return 'send_app_updated_email';
    }

    public static function get_job_description(): string {
        return 'Sends a notification email to the app owner when an app is updated.';
    }

    /**
     * @param array $payload {
     *     @type string $app_type App type e.g. 'plugin'.
     *     @type string $app_slug App slug.
     * }
     * @return array{sent: int, skipped: int}
     */
    public function handle( array $payload = [] ): mixed {
        $app_type       = (string) $payload['app_type'] ?? '';
        $app_slug       = (string) $payload['app_slug'] ?? '';
        $old_version    = (string) $payload['old_version'] ?? '';

        $sent    = 0;
        $skipped = 0;

        $app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( ! $app ) {
            return compact( 'sent', 'skipped' );
        }

        $emails = $this->resolve_owner_emails( $app->get_owner_id() );

        if ( empty( $emails ) ) {
            return compact( 'sent', 'skipped' );
        }

        foreach ( $emails as $email ) {
            $message = ( new AppUpdatedEmail( $app, $email, $old_version ) )->to_message();

            if ( $message === null ) {
                $skipped++;
                continue;
            }

            try {
                smliser_mailer()->send( $message );
                $sent++;
            } catch ( \Throwable ) {
                $skipped++;
            }
        }

        return compact( 'sent', 'skipped' );
    }
}