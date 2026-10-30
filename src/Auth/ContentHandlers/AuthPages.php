<?php
/**
 * Auth pages class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Auth\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Auth\ContentHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\EmailVerificationToken;
use SmartLicenseServer\Security\Authentication\PasswordResetToken;
use SmartLicenseServer\Security\Authentication\UserAccounts;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * The sign-in pages: one route and one theme template per page.
 *
 *   GET <login prefix>/                        sign_in          'auth.sign-in'
 *   GET <login prefix>/signup/                 sign_up          'auth.sign-up'
 *   GET <login prefix>/forgot-password/        forgot_password  'auth.forgot-password'
 *   GET <login prefix>/reset-password/?key=…   reset_password   'auth.reset-password'
 *   GET <logout prefix>/                       signed_out       'auth.signed-out'
 *
 * The theme renders each form; the form posts to its AuthManagement action
 * through the core script page-forms.js. Pages of a form share these data:
 *   string $action The URL the form posts to.
 * A visitor who is already signed in gets the same page; the theme decides
 * what to show them ($this->principal()).
 */
final class AuthPages {

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
		private PasswordResetToken $reset_tokens,
		private EmailVerificationToken $verify_tokens,
		private UserAccounts $accounts
	) {}

	/**
	 * GET <login prefix>/ — sign in.
	 *
	 * Template 'auth.sign-in'. Data: $action, and
	 *   string $redirect_url The page's ?redirect_url= when on this site; empty otherwise.
	 *
	 * A redirect_url query parameter is posted with the form, so the visitor
	 * returns where they were sent from. The link to sign up carries it on,
	 * so someone without an account (e.g. invited to an organization) gets
	 * back too.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function sign_in( Request $request ) : View {
		return $this->form_page( 'auth.sign-in', 'Sign in', 'form/login' )
			->set( 'redirect_url', $this->redirect_url( $request ) );
	}

	/**
	 * GET <login prefix>/signup/ — create an account.
	 *
	 * Template 'auth.sign-up'. Data: $action, and
	 *   string $email       Address to fill in, from ?email=; empty when absent or invalid.
	 *   string $terms_url   Terms URL; empty when not set.
	 *   string $privacy_url Privacy policy URL; empty when not set.
	 *   string $redirect_url The page's ?redirect_url= when on this site; empty otherwise.
	 *                        The form posts it, and signing up returns there.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function sign_up( Request $request ) : View {
		// ?email= fills the address in, e.g. from the email that delivers a purchased license.
		$email = trim( (string) $request->get( 'email', '' ) );

		return $this->form_page( 'auth.sign-up', 'Create an account', 'form/signup' )
			->set( 'email', false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '' )
			->set( 'terms_url', (string) $this->settings->get( Settings::TERMS_URL, '' ) )
			->set( 'privacy_url', (string) $this->settings->get( Settings::PRIVACY_POLICY_URL, '' ) )
			->set( 'redirect_url', $this->redirect_url( $request ) );
	}

	/**
	 * The page's ?redirect_url= when it is on this site, for the links between
	 * signing in and signing up to carry it on; empty otherwise.
	 *
	 * @param Request $request
	 * @return string
	 */
	private function redirect_url( Request $request ) : string {
		$url = URL::from( (string) $request->get( 'redirect_url', '' ) );

		return $url->is_valid() && $url->get_origin() === $this->urls->url()->get_origin() ? $url->url() : '';
	}

	/**
	 * GET <login prefix>/forgot-password/ — ask for a reset link.
	 *
	 * Template 'auth.forgot-password'. Data: $action.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function forgot_password( Request $request ) : View {
		return $this->form_page( 'auth.forgot-password', 'Reset your password', 'form/forgot-password' );
	}

	/**
	 * GET <login prefix>/reset-password/?key=… — choose a new password.
	 *
	 * The link of the reset email (PasswordResetJob). The key is checked,
	 * never used up, here; the action uses it up.
	 *
	 * Template 'auth.reset-password'. Data: $action, and
	 *   bool   $valid  Whether the key is valid.
	 *   string $token  The key, posted back with the form; empty when invalid.
	 *   string $email  The account's email address; empty when invalid.
	 *   string $reason Why the key cannot be used; empty when valid.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function reset_password( Request $request ) : View {
		$key   = (string) $request->get( 'key', '', false );
		$check = '' === $key ? [ 'valid' => false ] : $this->reset_tokens->verify( $key );
		$valid = (bool) $check['valid'];

		return $this->form_page( 'auth.reset-password', 'Choose a new password', 'form/reset-password' )
			->set( 'valid', $valid )
			->set( 'token', $valid ? $key : '' )
			->set( 'email', $valid ? (string) $check['user']->get_email() : '' )
			->set( 'reason', $valid ? '' : 'This password reset link is invalid or has expired. Ask for a new one.' );
	}

	/**
	 * GET <login prefix>/verify-email?key=… — the link in the verification email.
	 *
	 * Opening a valid link verifies the address and links the licenses issued
	 * to it. Doing so on GET is safe: the link only proves the mail arrived,
	 * opening it twice changes nothing, and it signs nobody in.
	 *
	 * Template 'auth.verify-email'. Data:
	 *   bool   $verified Whether the address is now verified.
	 *   string $email    The verified address; empty on failure.
	 *   int    $linked   Licenses linked to the account by this visit.
	 *   string $reason   Why the link failed; empty on success.
	 *   bool   $resend   Whether the visitor may ask for a new link (signed in, unverified).
	 *   string $action   URL of the resend form.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function verify_email( Request $request ) : View {
		$key      = (string) $request->get( 'key', '', false );
		$check    = '' === $key ? [ 'valid' => false, 'reason' => 'This verification link is not valid.' ] : $this->verify_tokens->verify( $key );
		$verified = false;
		$linked   = 0;
		$reason   = (string) ( $check['reason'] ?? '' );

		if ( $check['valid'] ) {
			try {
				$linked   = $this->accounts->mark_email_verified( $check['user'] );
				$verified = true;
			} catch ( RequestException $e ) {
				$reason = $e->get_error_message();
			}
		}

		$current = $this->guard->get_principal()?->get_actor();

		return $this->form_page( 'auth.verify-email', $verified ? 'Email verified' : 'Verify your email', 'form/resend-verification' )
			->set( 'verified', $verified )
			->set( 'email', $verified ? (string) $check['user']->get_email() : '' )
			->set( 'linked', $linked )
			->set( 'reason', $reason )
			->set( 'resend', ! $verified && $current instanceof User && ! $current->is_email_verified() );
	}

	/**
	 * GET <logout prefix>/ — signs this browser out and says so.
	 *
	 * Template 'auth.signed-out'. Data:
	 *   bool   $ended      Whether a session was ended by this request.
	 *   string $name       Who signed out; empty when $ended is false.
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

	/**
	 * A page with one form posting to an action under the login prefix.
	 *
	 * @param string $template Page template slug.
	 * @param string $title    Page title.
	 * @param string $action   Action path under the login prefix.
	 * @return View
	 */
	private function form_page( string $template, string $title, string $action ) : View {
		return View::make( $template, [ 'action' => $this->urls->login_url( $action )->url() ] )
			->set_title( $title )
			->set_header( 'Cache-Control', 'no-store' )
			->set_header( 'Referrer-Policy', 'no-referrer' )
			->add_script( View::FORMS_SCRIPT );
	}
}
