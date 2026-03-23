<?php
/**
 * Send app ownership changed email job class file.
 *
 * Dispatched when an app's owner_id changes. Resolves emails and
 * display names for both the previous and new owner, then sends
 * AppOwnershipChangedEmail to each. Organisation owners notify all
 * their resource_owner members.
 *
 * Triggered from: HostingController::save_app() — when app_owner_id
 * changes on an existing app.
 *
 * Payload:
 *   app_type          (string) — e.g. 'plugin', 'theme', 'software'.
 *   app_slug          (string) — The app slug.
 *   previous_owner_id (int)    — Owner ID before the transfer.
 *   new_owner_id      (int)    — Owner ID after the transfer.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Apps
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Apps;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Email\Templates\Apps\AppOwnershipChangedEmail;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\OwnerSubjects\Organization;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Sends AppOwnershipChangedEmail to both the previous and new owner.
 */
class SendAppOwnershipChangedEmailJob implements JobHandlerInterface {
    use OwnerEmailResolverTrait;

    public static function get_job_name(): string {
        return 'send_app_ownership_changed_email';
    }

    public static function get_job_description(): string {
        return 'Notifies both the previous and new app owner when ownership is transferred.';
    }

    /**
     * @param array $payload {
     *     @type string $app_type          App type e.g. 'plugin'.
     *     @type string $app_slug          App slug.
     *     @type int    $previous_owner_id Previous owner ID.
     *     @type int    $new_owner_id      New owner ID.
     * }
     * @return array{sent: int, skipped: int}
     */
    public function handle( array $payload ): mixed {
        $app_type          = $payload['app_type']                ?? '';
        $app_slug          = $payload['app_slug']                ?? '';
        $previous_owner_id = (int) ( $payload['previous_owner_id'] ?? 0 );
        $new_owner_id      = (int) ( $payload['new_owner_id']      ?? 0 );

        $sent    = 0;
        $skipped = 0;

        $app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( ! $app ) {
            return compact( 'sent', 'skipped' );
        }

        $previous_owner_name = $this->resolve_owner_name( $previous_owner_id );
        $new_owner_name      = $this->resolve_owner_name( $new_owner_id );

        // Notify previous owner(s).
        foreach ( $this->resolve_owner_recipients( $previous_owner_id ) as [ 'email' => $email, 'name' => $name ] ) {
            $message = ( new AppOwnershipChangedEmail(
                $app,
                $email,
                $name,
                $previous_owner_name,
                $new_owner_name,
                false   // is_new_owner = false
            ) )->to_message();

            if ( $message === null ) { $skipped++; continue; }

            try {
                smliser_mailer()->send( $message );
                $sent++;
            } catch ( \Throwable ) {
                $skipped++;
            }
        }

        // Notify new owner(s).
        foreach ( $this->resolve_owner_recipients( $new_owner_id ) as [ 'email' => $email, 'name' => $name ] ) {
            $message = ( new AppOwnershipChangedEmail(
                $app,
                $email,
                $name,
                $previous_owner_name,
                $new_owner_name,
                true    // is_new_owner = true
            ) )->to_message();

            if ( $message === null ) { $skipped++; continue; }

            try {
                smliser_mailer()->send( $message );
                $sent++;
            } catch ( \Throwable ) {
                $skipped++;
            }
        }

        return compact( 'sent', 'skipped' );
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Resolve all recipients (email + display name) for an owner.
     *
     * Individual owners → one entry.
     * Organisation owners → one entry per resource_owner member.
     *
     * @param int $owner_id
     * @return array<int, array{email: string, name: string}>
     */
    private function resolve_owner_recipients( int $owner_id ): array {
        if ( $owner_id <= 0 ) {
            return [];
        }

        $owner = Owner::get_by_id( $owner_id );

        if ( ! $owner || ! $owner->exists() ) {
            return [];
        }

        if ( $owner->is_individual() ) {
            $user  = User::get_by_id( $owner->get_subject_id() );
            $email = $user?->get_email() ?? '';
            $name  = $user?->get_display_name() ?? '';

            return filter_var( $email, FILTER_VALIDATE_EMAIL )
                ? [ [ 'email' => $email, 'name' => $name ] ]
                : [];
        }

        if ( $owner->is_organization() ) {
            $org        = Organization::get_by_id( $owner->get_subject_id() );
            $members    = $org?->get_members();
            $recipients = [];

            if ( ! $members ) {
                return [];
            }

            foreach ( $members as $member ) {
                if ( $member->get_role()?->get_slug() !== 'resource_owner' ) {
                    continue;
                }

                $email = $member->get_user()->get_email();
                $name  = $member->get_user()->get_display_name();

                if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                    $recipients[] = [ 'email' => $email, 'name' => $name ];
                }
            }

            return $recipients;
        }

        return [];
    }

    /**
     * Resolve the display name for an owner — used as the owner label
     * in the email body shared across all recipients.
     *
     * Individual → user display name.
     * Organisation → organisation display name.
     * Platform / unknown → 'System'.
     *
     * @param int $owner_id
     * @return string
     */
    private function resolve_owner_name( int $owner_id ): string {
        if ( $owner_id <= 0 ) {
            return 'Unknown';
        }

        $owner = Owner::get_by_id( $owner_id );

        if ( ! $owner || ! $owner->exists() ) {
            return 'Unknown';
        }

        if ( $owner->is_individual() ) {
            $user = User::get_by_id( $owner->get_subject_id() );
            return $user?->get_display_name() ?: 'Unknown';
        }

        if ( $owner->is_organization() ) {
            $org = Organization::get_by_id( $owner->get_subject_id() );
            return $org?->get_display_name() ?: 'Unknown';
        }

        return 'System';
    }
}