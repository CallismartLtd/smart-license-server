<?php
/**
 * Password reset job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Accounts
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Accounts;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Email\Templates\Accounts\PasswordResetEmail;
use SmartLicenseServer\Security\Actors\User;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Asynchronously sends password reset emails.
 */
class PasswordResetJob implements JobHandlerInterface {

    /*
    |----------------------
    | JobHandlerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Expected payload keys:
     *   - user_id  (int) The user requesting password reset.
     *   - recipient (string) Recipient email address.
     *   - reset_url (string) The password reset link.
     *   - expires_in (string) Number of minutes until the reset link expires.
     *   - ip_address (string) IP address of the requesting user.
     *   - user_agent (string) User agent of the requesting client.
     *
     * @param array<string, mixed> $payload
     * @return bool|array.
     */
    public function handle( array $payload ): mixed {
        $user_id    = (int) $payload['user_id'] ?? 0;
        $user       = User::get_by_id( $user_id );

        if ( ! $user ) {
            return false;
        }

        $recipient      = (string) $payload['recipient'] ?? '';
        $reset_url      = (string) $payload['reset_url'] ?? '';

        if ( ! $recipient || ! $reset_url ) {
            return false;
        }

        $expires_in     = (int) $payload['expires_in'] ?? 0;
        $ip_address     = (string) $payload['ip_address'] ?? 'unknown';
        $user_agent     = (string) $payload['user_agent'] ?? 'unknown';

        $reset_email    = new PasswordResetEmail(
            $user, 
            $recipient, 
            $reset_url, 
            $expires_in, 
            $ip_address, 
            $user_agent
        );

        $response   = \smliser_mailer()->send( $reset_email->to_message() );

        return $response->to_array();
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'Send Password Reset Email';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Asyncronously send password reset emails.';
    }
}