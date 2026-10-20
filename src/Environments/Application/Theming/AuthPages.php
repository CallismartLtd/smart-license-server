<?php
/**
 * Auth pages class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Security\Authentication\PasswordResetToken;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Page handlers of the sign-in and signed-out pages.
 *
 * Pages return HTML and actions return JSON; no HTML travels inside JSON.
 * The theme renders every form; the forms post to the AuthController
 * actions, and the core script sign-in.js runs them through the markup
 * contract it documents.
 */
final class AuthPages {

	/**
	 * Core script that runs the sign-in forms (AssetsManager handle).
	 */
	public const SIGN_IN_SCRIPT = 'sign-in';

	/**
	 * Constructor.
	 *
	 * @param Guard                             $guard        The signed-in principal, if any.
	 * @param PasswordIdentityProviderInterface $id_provider  Signs the browser out.
	 * @param URLManager                        $urls         URL API.
	 * @param Settings                          $settings     Terms and privacy URLs.
	 * @param PasswordResetToken                $reset_tokens Checks the key of a reset link.
	 */
	public function __construct(
		private Guard $guard,
		private PasswordIdentityProviderInterface $id_provider,
		private URLManager $urls,
		private Settings $settings,
		private PasswordResetToken $reset_tokens
	) {}

	/**
	 * GET <login prefix>/ — sign in, sign up, forgot and reset password.
	 *
	 * Template 'auth.sign-in'. Data:
	 *   array<string, string> $actions       Form name => action URL, for
	 *                                        login, signup, forgot-password
	 *                                        and reset-password.
	 *   string                $default_panel The panel shown without a hash:
	 *                                        "reset-password" when the URL has a
	 *                                        reset key, otherwise "login".
	 *   array|null            $reset         Null without a reset key; otherwise
	 *                                        ['valid' => true, 'token' => string,
	 *                                        'email' => string] or ['valid' => false,
	 *                                        'reason' => string].
	 *   string                $terms_url     Terms URL; empty when not set.
	 *   string                $privacy_url   Privacy policy URL; empty when not set.
	 *
	 * The page asks for the sign-in.js script. A signed-in visitor gets the
	 * same page; the theme decides what to show them ($this->principal()).
	 *
	 * @param Request $request
	 * @return View
	 */
	public function sign_in( Request $request ) : View {
		$key   = (string) $request->get( 'key', '', false );
		$reset = null;

		if ( '' !== $key ) {
			$check = $this->reset_tokens->verify( $key );
			$reset = $check['valid']
				? [ 'valid' => true, 'token' => $key, 'email' => $check['user']->get_email() ]
				: [ 'valid' => false, 'reason' => 'This password reset link is invalid or has expired. Ask for a new one.' ];
		}

		return View::make(
			'auth.sign-in',
			[
				'actions'       => [
					'login'           => $this->urls->login_url( 'form/login' )->url(),
					'signup'          => $this->urls->login_url( 'form/signup' )->url(),
					'forgot-password' => $this->urls->login_url( 'form/forgot-password' )->url(),
					'reset-password'  => $this->urls->login_url( 'form/reset-password' )->url(),
				],
				'default_panel' => null === $reset ? 'login' : 'reset-password',
				'reset'         => $reset,
				'terms_url'     => (string) $this->settings->get( Settings::TERMS_URL, '' ),
				'privacy_url'   => (string) $this->settings->get( Settings::PRIVACY_POLICY_URL, '' ),
			]
		)
			->set_title( 'Sign in' )
			->set_header( 'Cache-Control', 'no-store' )
			->set_header( 'Referrer-Policy', 'no-referrer' )
			->add_script( self::SIGN_IN_SCRIPT );
	}

	/**
	 * GET <logout prefix>/ — signs this browser out and says so.
	 *
	 * Template 'auth.signed-out'. Data:
	 *   bool   $ended      Whether a session was ended by this request.
	 *   string $name       Display name of who signed out; empty when $ended is false.
	 *   bool   $everywhere Whether the visitor arrives from "sign out everywhere"
	 *                      (?everywhere=1, set by that action's redirect).
	 *
	 * @param Request $request
	 * @return View
	 */
	public function signed_out( Request $request ) : View {
		$principal = $this->guard->get_principal();
		$name      = '';

		if ( null !== $principal ) {
			$name = (string) $principal->get_display_name();
			$this->id_provider->logout();
		}

		return View::make(
			'auth.signed-out',
			[
				'ended'      => null !== $principal,
				'name'       => $name,
				'everywhere' => null === $principal && '1' === (string) $request->get( 'everywhere', '' ),
			]
		)
			->set_title( 'Signed out' )
			->set_header( 'Cache-Control', 'no-store' );
	}
}