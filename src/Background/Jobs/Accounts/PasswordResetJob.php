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
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Email\Templates\Accounts\PasswordResetEmail;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\PasswordResetToken;

/**
 * Asynchronously handles a password reset request.
 *
 * The web request only queues this job with the submitted email address, so
 * it does the same work whether or not an account exists and cannot reveal
 * which addresses are registered. The account lookup, the token and the
 * email all happen here; an address without an account ends the job quietly.
 */
class PasswordResetJob implements JobHandlerInterface {
    public function __construct(
        protected Mailer $mailer,
        protected PasswordResetToken $tokens,
        protected URLManager $urlmanager
    ) {}

    /*
    |----------------------
    | JobHandlerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Expected payload keys:
     *   - email      (string) The email address the reset was requested for.
     *   - ip_address (string) IP address of the requesting client.
     *   - user_agent (string) User agent of the requesting client.
     *
     * @param array<string, mixed> $payload
     * @return bool|array.
     */
    public function handle( array $payload = [] ): mixed {
        $email  = (string) ( $payload['email'] ?? '' );
        $user   = '' !== $email ? User::get_by_email( $email ) : null;

        // No account for this address: nothing to send, and nothing to record.
        if ( ! $user ) {
            return true;
        }

        $reset_url  = $this->urlmanager->client_dashboard_url( '', [ 'key' => $this->tokens->issue( $user ) ] )
            ->set_hash( 'reset-password' );

        $reset_email    = new PasswordResetEmail(
            $user,
            $user->get_email(),
            (string) $reset_url,
            PasswordResetToken::TTL_MINUTES,
            (string) ( $payload['ip_address'] ?? 'unknown' ),
            (string) ( $payload['user_agent'] ?? 'unknown' )
        );

        $response   = $this->mailer->send( $reset_email->to_message() );

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