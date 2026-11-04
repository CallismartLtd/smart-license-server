<?php
/**
 * Two-factor authentication enabled email template.
 *
 * Sent after a second sign-in factor has been turned on for a user.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Security;

use SmartLicenseServer\Security\Actors\User;

class TwoFactorEnabledEmail extends SecurityEmailTemplate {

	/**
	 * @param User     $user        The user whose account changed.
	 * @param string   $to          Recipient email address.
	 * @param string   $ip_address  IP address the change came from.
	 * @param string   $user_agent  User agent the change came from.
	 * @param string   $secure_url  Where the "wasn't you?" button leads.
	 * @param int|null $occurred_at Unix timestamp of the change.
	 * @param string   $factor      'email' or 'totp'.
	 */
	public function __construct(
		User $user,
		string $to,
		string $ip_address = 'unknown',
		string $user_agent = 'unknown',
		string $secure_url = '',
		?int $occurred_at = null,
		private readonly string $factor = 'totp'
	) {
		parent::__construct( $user, $to, $ip_address, $user_agent, $secure_url, $occurred_at );
	}

	public static function template_key() : string {
		return 'two_factor_enabled';
	}

	protected function action() : string {
		return 'Two-factor authentication enabled';
	}

	protected function summary() : string {
		$method = match ( $this->factor ) {
			'email' => 'email codes',
			default => 'an authenticator app',
		};

		return sprintf( 'Two-factor authentication with %s was turned on for your account.', $method );
	}

	public function label() : string {
		return 'Two-Factor Authentication Enabled';
	}

	public function description() : string {
		return 'Sent after a user turns on email codes or an authenticator app as a second sign-in factor.';
	}

	public static function preview() : static {
		[ $ip, $agent, $url ] = static::preview_context();

		return new static( static::preview_user(), 'preview@example.com', $ip, $agent, $url, null, 'totp' );
	}
}
