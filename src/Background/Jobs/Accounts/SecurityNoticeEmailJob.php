<?php
/**
 * Security notice email background job class file.
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
use SmartLicenseServer\Email\Templates\Security\PasskeyAddedEmail;
use SmartLicenseServer\Email\Templates\Security\PasskeyRemovedEmail;
use SmartLicenseServer\Email\Templates\Security\SecurityEmailTemplate;
use SmartLicenseServer\Email\Templates\Security\TwoFactorDisabledEmail;
use SmartLicenseServer\Email\Templates\Security\TwoFactorEnabledEmail;
use SmartLicenseServer\Security\Actors\User;

/**
 * Asynchronously tells a user that their sign-in security changed.
 *
 * One job covers every security notice; the payload names the template by its
 * template key, and only the keys listed in handle() are accepted.
 */
class SecurityNoticeEmailJob implements JobHandlerInterface {
	public function __construct(
		protected Mailer $mailer,
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
	 *   - user_id     (int)    The user whose account changed.
	 *   - notice      (string) Template key: passkey_added | passkey_removed | two_factor_enabled | two_factor_disabled.
	 *   - factor      (string) 'email' or 'totp'. Two-factor notices only.
	 *   - ip_address  (string) IP address the change came from.
	 *   - user_agent  (string) User agent the change came from.
	 *   - occurred_at (int)    Unix timestamp of the change.
	 *
	 * @param array<string, mixed> $payload
	 * @return bool|array
	 */
	public function handle( array $payload = [] ) : mixed {
		$user_id = (int) ( $payload['user_id'] ?? 0 );
		$user    = $user_id > 0 ? User::get_by_id( $user_id ) : null;

		if ( ! $user || ! $user->get_email() ) {
			return true;
		}

		$email = $this->make_email( $user, $payload );

		if ( null === $email ) {
			return [ 'success' => false, 'message' => 'Unknown security notice.' ];
		}

		$message = $email->to_message();

		if ( null === $message ) {
			return [ 'success' => false, 'message' => sprintf( 'The "%s" template is disabled.', $email->label() ) ];
		}

		return $this->mailer->send( $message )->to_array();
	}

	/**
	 * Build the template for a payload.
	 *
	 * @param User                 $user
	 * @param array<string, mixed> $payload
	 * @return SecurityEmailTemplate|null Null when the notice key is not recognised.
	 */
	protected function make_email( User $user, array $payload ) : ?SecurityEmailTemplate {
		$args = [
			$user,
			$user->get_email(),
			(string) ( $payload['ip_address'] ?? 'unknown' ),
			(string) ( $payload['user_agent'] ?? 'unknown' ),
			(string) $this->urlmanager->login_url( 'forgot-password' ),
			isset( $payload['occurred_at'] ) ? (int) $payload['occurred_at'] : null,
		];
		$factor = (string) ( $payload['factor'] ?? 'totp' );

		return match ( (string) ( $payload['notice'] ?? '' ) ) {
			PasskeyAddedEmail::template_key()      => new PasskeyAddedEmail( ...$args ),
			PasskeyRemovedEmail::template_key()    => new PasskeyRemovedEmail( ...$args ),
			TwoFactorEnabledEmail::template_key()  => new TwoFactorEnabledEmail( ...$args, factor: $factor ),
			TwoFactorDisabledEmail::template_key() => new TwoFactorDisabledEmail( ...$args, factor: $factor ),
			default                                => null,
		};
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return 'Send Security Notice Email';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return 'Asynchronously notify a user when a passkey or two-factor method is added, removed, enabled or disabled.';
	}
}
