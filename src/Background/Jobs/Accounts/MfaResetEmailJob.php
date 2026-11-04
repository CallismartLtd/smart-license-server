<?php
/**
 * MFA reset email background job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Accounts
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Accounts;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Email\Templates\Accounts\MfaResetEmail;
use SmartLicenseServer\Security\Actors\User;

/**
 * Asynchronously sends an MFA reset notification email to a user.
 */
class MfaResetEmailJob implements JobHandlerInterface {
	public function __construct(
		protected Mailer $mailer
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
	 *   - user_id (int) The user ID whose 2FA was reset.
	 *
	 * @param array<string, mixed> $payload
	 * @return bool|array.
	 */
	public function handle( array $payload = [] ) : mixed {
		$user_id = (int) ( $payload['user_id'] ?? 0 );
		$user    = $user_id > 0 ? User::get_by_id( $user_id ) : null;

		if ( ! $user || ! $user->get_email() ) {
			return true;
		}

		$email    = new MfaResetEmail( $user, $user->get_email() );
		$response = $this->mailer->send( $email->to_message() );

		return $response->to_array();
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return 'Send MFA Reset Email';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return 'Asynchronously send a notification email when 2FA is reset by an administrator.';
	}
}
