<?php
/**
 * Email verification job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Accounts
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Accounts;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Email\Templates\Accounts\VerifyEmailEmail;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\EmailVerificationToken;

/**
 * Sends a user a link to verify their email address.
 *
 * Queued after signup and when a signed-in user asks for a new link. A user
 * whose address is already verified gets nothing.
 */
class EmailVerificationJob implements JobHandlerInterface {

	public function __construct(
		protected Mailer $mailer,
		protected EmailVerificationToken $tokens,
		protected URLManager $urlmanager
	) {}

	/**
	 * {@inheritdoc}
	 *
	 * @param array{user_id?: int} $payload
	 * @return mixed
	 */
	public function handle( array $payload = [] ) : mixed {
		$user = User::get_by_id( (int) ( $payload['user_id'] ?? 0 ) );

		if ( ! $user ) {
			return [ 'success' => false, 'message' => 'User does not exist.' ];
		}

		if ( $user->is_email_verified() ) {
			return [ 'success' => true, 'message' => 'The email address is already verified.' ];
		}

		// The verification page (AuthPages::verify_email()).
		$verify_url = $this->urlmanager->login_url( 'verify-email', [ 'key' => $this->tokens->issue( $user ) ] );

		$message = ( new VerifyEmailEmail( $user, $user->get_email(), (string) $verify_url, EmailVerificationToken::TTL_HOURS ) )->to_message();

		if ( null === $message ) {
			return [ 'success' => false, 'message' => 'The "Verify Email" template is disabled.' ];
		}

		return $this->mailer->send( $message )->to_array();
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return 'Send Email Verification';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return 'Asynchronously send a user a link to verify their email address.';
	}
}
