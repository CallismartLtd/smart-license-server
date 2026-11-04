<?php
/**
 * Email one-time code template.
 *
 * Sent with the six-digit code a user types in to sign in with, enable or
 * disable email two-factor authentication.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Security;

use SmartLicenseServer\Security\Actors\User;

class EmailOtpEmail extends SecurityEmailTemplate {

	/**
	 * @param User     $user        The user the code was issued to.
	 * @param string   $to          Recipient email address.
	 * @param string   $code        The one-time code.
	 * @param string   $purpose     What the code is for, e.g. 'Sign-in verification code'.
	 * @param int      $expires_in  Minutes until the code expires.
	 * @param string   $ip_address  IP address the code was requested from.
	 * @param string   $user_agent  User agent the code was requested from.
	 * @param string   $secure_url  Where the "wasn't you?" button leads.
	 * @param int|null $occurred_at Unix timestamp of the request.
	 */
	public function __construct(
		User $user,
		string $to,
		private readonly string $code,
		private readonly string $purpose,
		private readonly int $expires_in,
		string $ip_address = 'unknown',
		string $user_agent = 'unknown',
		string $secure_url = '',
		?int $occurred_at = null
	) {
		parent::__construct( $user, $to, $ip_address, $user_agent, $secure_url, $occurred_at );
	}

	public static function template_key() : string {
		return 'email_otp';
	}

	protected function action() : string {
		return $this->purpose;
	}

	protected function subject() : string {
		return $this->purpose;
	}

	protected function preheader() : string {
		return sprintf( 'Your code is %s. It expires in %d minutes.', $this->code, $this->expires_in );
	}

	protected function summary() : string {
		return sprintf(
			'Use the code below to continue. It expires in %d minutes. Never share it with anyone.',
			$this->expires_in
		);
	}

	protected function tone() : string {
		return 'info';
	}

	protected function variables() : array {
		return array_merge( parent::variables(), [
			'{{otp_code}}' => \escHtml( $this->code ),
		] );
	}

	protected function lead_blocks() : array {
		return [
			[
				'id'        => 'code',
				'type'      => 'detail_card',
				'rows'      => [
					[ 'label' => 'Verification code', 'value' => '{{otp_code}}' ],
				],
				'editable'  => false,
				'removable' => false,
			],
		];
	}

	public function text() : string {
		return sprintf( "Your verification code is %s.\n\n", $this->code ) . parent::text();
	}

	/**
	 * Always enabled.
	 *
	 * Unlike the notice emails, this one carries a code the user cannot
	 * continue without. Disabling it would silently break email sign-in
	 * codes and the enable/disable confirmations for every user.
	 *
	 * @return bool
	 */
	public function is_enabled() : bool {
		return true;
	}

	public function label() : string {
		return 'Email Verification Code';
	}

	public function description() : string {
		return 'Sent with the one-time code used to sign in, or to enable or disable email two-factor authentication.';
	}

	public static function preview() : static {
		[ $ip, $agent, $url ] = static::preview_context();

		return new static( static::preview_user(), 'preview@example.com', '482913', 'Sign-in verification code', 10, $ip, $agent, $url );
	}
}
