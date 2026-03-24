<?php
/**
 * Send app published email job class file.
 *
 * Dispatched when a new application is published to the repository.
 * Resolves the owner's email address and sends AppPublishedEmail.
 *
 * Triggered from: AbstractHostedApp::save() — new app branch,
 * after successful insert.
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
use SmartLicenseServer\Email\Templates\Apps\AppPublishedEmail;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Sends AppPublishedEmail to the app owner when a new app is published.
 */
class SendAppPublishedEmailJob implements JobHandlerInterface {
    use OwnerEmailResolverTrait;

    public static function get_job_name(): string {
        return 'send_app_published_email';
    }

    public static function get_job_description(): string {
        return 'Sends a notification email to the app owner when a new app is published.';
    }

    /**
     * @param array $payload {
     *     @type string $app_type App type e.g. 'plugin'.
     *     @type string $app_slug App slug.
     * }
     * @return array{sent: int, skipped: int}
     */
    public function handle( array $payload ): mixed {
        $app_type = (string) $payload['app_type'] ?? '';
        $app_slug = (string) $payload['app_slug'] ?? '';

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
            $message = ( new AppPublishedEmail( $app, $email ) )->to_message();

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