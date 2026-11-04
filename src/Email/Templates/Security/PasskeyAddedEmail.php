<?php
/**
 * Passkey added email template.
 *
 * Sent after a passkey has been registered on a user's account.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Security;

class PasskeyAddedEmail extends SecurityEmailTemplate {

	public static function template_key() : string {
		return 'passkey_added';
	}

	protected function action() : string {
		return 'Passkey added';
	}

	protected function summary() : string {
		return 'A new passkey was added to your account. It can now be used to sign in without your password.';
	}

	public function label() : string {
		return 'Passkey Added';
	}

	public function description() : string {
		return 'Sent after a passkey has been added to a user\'s account.';
	}

	public static function preview() : static {
		[ $ip, $agent, $url ] = static::preview_context();

		return new static( static::preview_user(), 'preview@example.com', $ip, $agent, $url );
	}
}
