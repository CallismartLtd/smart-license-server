<?php
/**
 * Send app status changed email job class file.
 *
 * Dispatched when an app's status is changed via HostingController.
 * Resolves the owner's email and sends AppStatusChangedEmail.
 *
 * Triggered from: HostingController::change_app_status() — after
 * successful status update.
 *
 * Payload:
 *   app_type   (string) — e.g. 'plugin', 'theme', 'software'.
 *   app_slug   (string) — The app slug.
 *   old_status (string) — The previous status.
 *   new_status (string) — The new status.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Apps
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Apps;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Email\Templates\Apps\AppStatusChangedEmail;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Sends AppStatusChangedEmail to the app owner when status changes.
 */
class SendAppStatusChangedEmailJob implements JobHandlerInterface {
    use OwnerEmailResolverTrait;

    public static function get_job_name(): string {
        return 'send_app_status_changed_email';
    }

    public static function get_job_description(): string {
        return 'Sends a notification email to the app owner when an app status changes.';
    }

    /**
     * @param array $payload {
     *     @type string $app_type   App type e.g. 'plugin'.
     *     @type string $app_slug   App slug.
     *     @type string $old_status Previous status.
     *     @type string $new_status New status.
     * }
     * @return array{sent: int, skipped: int}
     */
    public function handle( array $payload ): mixed {
        $app_type   = (string) $payload['app_type']   ?? '';
        $app_slug   = (string) $payload['app_slug']   ?? '';
        $old_status = $payload['old_status'] ?? '';
        $new_status = $payload['new_status'] ?? '';

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
            $message = ( new AppStatusChangedEmail( $app, $email, $old_status, $new_status ) )->to_message();

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