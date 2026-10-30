<?php
/**
 * Auth management class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Auth\ActionHandlers
 */

namespace SmartLicenseServer\Auth\ActionHandlers;

use SmartLicenseServer\Background\Jobs\Accounts\EmailVerificationJob;
use SmartLicenseServer\Background\Jobs\Accounts\PasswordResetJob;
use SmartLicenseServer\Background\Jobs\Accounts\SignupEmailJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Security\Authentication\PasswordResetToken;
use SmartLicenseServer\Security\Authentication\UserAccounts;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * The actions of the sign-in pages (AuthPages): POST routes answering JSON.
 *
 * Every answer is { success, message } and, when the browser should move
 * on, a redirect URL: the page-forms.js script shows the message and
 * follows the redirect, so what happens next is decided here.
 *
 *   POST <login prefix>/form/login            Sign in.
 *   POST <login prefix>/form/signup           Create an account.
 *   POST <login prefix>/form/forgot-password  Email a reset link.
 *   POST <login prefix>/form/reset-password   Choose a new password.
 *   POST <logout prefix>/                     Sign this browser out.
 *   POST <logout prefix>/everywhere           Sign out on every device.
 */
class AuthManagement {
	use QueueAwareTrait;

	/**
	 * Class constructor.
	 */
	public function __construct(
		protected Guard $guard,
		protected PasswordIdentityProviderInterface $id_provider,
		protected URLManager $urlmanager,
		protected Settings $settings,
		protected PasswordResetToken $reset_tokens,
		protected UserAccounts $accounts,
		JobQueue $job_queue
	) {
		$this->job_queue = $job_queue;
	}

	/*
	|--------------------------------------------------
	| LOGIN/LOGOUT
	|--------------------------------------------------
	*/

	/**
	 * Handle login form submission.
	 *
	 * Authenticates user with username/email and password.
	 * Sets authenticated session on success.
	 *
	 * @param Request $request Request object.
	 * @return Response JSON response
	 */
	public function handle_login( Request $request ) : Response {
		$username = (string) $request->post( 'username', '' );
		$password = (string) $request->get( parameter: 'password', default: '', sanitize: false );
		$remember = (bool) $request->post( 'remember', false );

		if ( empty( $username ) || empty( $password ) ) {
			return static::error_response(
				400,
				'missing_credentials',
				'Username and password are required.'
			);
		}

		$principal = $this->id_provider->logon( $username, $password, $remember );

		if ( $principal instanceof RequestException ) {
			return static::error_response(
				401,
				$principal->get_error_code(),
				$principal->get_error_message()
			);
		}

		$redirect_url = $this->redirect_url(
			$request,
			$this->guard->get_principal()?->is( 'system_admin' ) ? $this->urlmanager->admin_url() : $this->urlmanager->client_dashboard_url()
		);

		// Return success with redirect
		return static::success_response(
			200,
			[
				'success'  => true,
				'message'  => sprintf( 'Welcome back, %s.', $principal->get_display_name() ),
				'redirect' => $redirect_url->url()
			]
		);
	}

	/**
	 * POST <logout prefix>/ — sign this browser out, for scripts.
	 *
	 * A link signs out through the signed-out page (AuthPages::signed_out()).
	 *
	 * @param Request $request
	 * @return Response JSON: { success, message }
	 */
	public function handle_logout( Request $request ) : Response {
		$principal = $this->guard->get_principal();

		if ( ! $principal ) {
			return Response::json( [ 'success' => false, 'message' => 'Already logged out' ], 200 );
		}

		$this->id_provider->logout();

		return Response::json( [ 'success' => true, 'message' => sprintf( 'Good bye %s', $principal->get_display_name() ) ], 200 );
	}

	/**
	 * POST <logout prefix>/everywhere — sign out on every device.
	 *
	 * Ends every session of the current user, this one included. POST
	 * behind the CSRF middleware: a link or GET route would let any page
	 * sign the user out.
	 *
	 * A script gets JSON; a form post from a browser is redirected to the
	 * signed-out page.
	 *
	 * @param Request $request
	 * @return Response JSON { success, message }, or a 303 redirect.
	 */
	public function handle_logout_everywhere( Request $request ) : Response {
		$principal = $this->guard->get_principal();

		if ( ! $principal ) {
			$data = [ 'success' => false, 'message' => 'Already logged out' ];
		} else {
			$this->id_provider->logout_everywhere();

			$data = [ 'success' => true, 'message' => sprintf( 'Good bye %s, you are signed out on every device', $principal->get_display_name() ) ];
		}

		if ( $request->wantsJson() ) {
			return Response::json( $data, 200 );
		}

		return Response::redirect( $this->urlmanager->logout_url( $data['success'] ? [ 'everywhere' => '1' ] : [] ), 303 );
	}

	/**
	 * Where to send the user after signing in or up: the posted redirect_url
	 * when it is on this site, otherwise the fallback.
	 *
	 * @param Request $request
	 * @param URL     $fallback
	 * @return URL
	 */
	private function redirect_url( Request $request, URL $fallback ) : URL {
		$redirect_url = URL::from( (string) $request->post( 'redirect_url', '' ) );

		if ( ! $redirect_url->is_valid() || $redirect_url->get_origin() !== $this->urlmanager->url()->get_origin() ) {
			return $fallback;
		}

		return $redirect_url;
	}

	/*
	|--------------------------------------------------
	| SIGNUP
	|--------------------------------------------------
	*/

	/**
	 * Handle signup form submission.
	 *
	 * Creates new user account with email and password.
	 * Sends verification email.
	 *
	 * @param Request $request Contains: full_name, email, password, password_confirm, agree_terms, _wpnonce_signup
	 * @return Response JSON response
	 */
	public function handle_signup( Request $request ) : Response {
		if ( ! $request->has( 'agree_terms' ) ) {
			return static::error_response(
				400,
				'terms_not_accepted',
				'You must agree to the terms and conditions to create an account.'
			);
		}

		$principal = $this->id_provider->signup( $request );
		
		if ( $principal instanceof RequestException ) {
			$status_code = (int) ( $principal->get_error_data()['status'] ?? 400 );
			return static::error_response(
				$status_code,
				$principal->get_error_code(),
				$principal->get_error_message()
			);
		}

		$account_type = $request->get( 'account_type', 'viewer' );

		if ( 'resource_owner' !== $account_type ) {
			$account_type = 'viewer';
		}
		
		$this->dispatch_job(
			SignupEmailJob::class,
			[
				'user_id'   => $principal->get_id(),
				'recipient' => $principal->get_email()
			]
		);

		$this->dispatch_job( EmailVerificationJob::class, [ 'user_id' => $principal->get_id() ] );

		$this->dispatch_job(
			SignupEmailJob::class,
			[
				'user_id'      => $principal->get_id(),
				'recipient'    => $this->settings->get( Settings::ADMIN_EMAIL ),
				'for_admin'    => true,
				'ip_address'   => $request->ip(),
				'account_type' => $account_type
			]
		);

		// Return success
		return static::success_response(
			200,
			[
				'success'  => true,
				'message'  => 'Account created successfully! Check your email to verify your account.',
				// Back where they were sent from, e.g. an organization invitation.
				'redirect' => $this->redirect_url( $request, $this->urlmanager->client_dashboard_url() )->url()
			]
		);
	}

	/*
	|----------------------
	| EMAIL VERIFICATION
	|----------------------
	*/

	/**
	 * POST <login prefix>/form/resend-verification — send a new link.
	 *
	 * For the signed-in user only; the link goes to the account's current
	 * email address. Links sent earlier stay valid until they expire.
	 *
	 * @param Request $request
	 * @return Response JSON { success, message }
	 */
	public function handle_resend_verification( Request $request ) : Response {
		$user = $this->guard->get_principal()?->get_actor();

		if ( ! ( $user instanceof User ) ) {
			return static::error_response( 401, 'not_signed_in', 'Sign in to verify your email address.' );
		}

		if ( $user->is_email_verified() ) {
			return static::success_response( 200, [ 'success' => true, 'message' => 'Your email address is already verified.' ] );
		}

		if ( ! $this->accounts->acquire_verification_resend( $user ) ) {
			return static::error_response( 429, 'too_soon', 'A link was sent a moment ago. Check your inbox, or try again in a minute.' );
		}

		$this->dispatch_job( EmailVerificationJob::class, [ 'user_id' => $user->get_id() ] );

		return static::success_response(
			200,
			[
				'success' => true,
				'message' => sprintf( 'A new verification link is on its way to %s.', $user->get_email() ),
			]
		);
	}

	/*
	|---------------------
	| PASSWORD RECOVERY.
	|---------------------
	*/

	/**
	 * Handle forgot password form submission.
	 *
	 * Only queues the reset job with the submitted address and answers the
	 * same way for every valid address. Whether an account exists is decided
	 * in the job (see PasswordResetJob), so neither the response nor its
	 * timing tells the requester which addresses are registered.
	 *
	 * @param Request $request
	 * @return Response JSON response
	 */
	public function handle_forgot_password( Request $request ) : Response {
		$email = (string) $request->get( 'email', '' );

		// Validate email
		if ( empty( $email ) || ! static::is_valid_email( $email ) ) {
			return static::error_response(
				400,
				'invalid_email',
				'Please provide a valid email address.'
			);
		}

		$this->dispatch_job(
			PasswordResetJob::class,
			[
				'email'      => $email,
				'ip_address' => $request->ip(),
				'user_agent' => $request->userAgent(),
			]
		);

		return static::success_response(
			200,
			[
				'success' => true,
				'message' => 'If an account exists for this email, you will receive a password reset link shortly.',
			]
		);
	}

	/**
	 * Handle password reset request.
	 * 
	 * @param Request $request
	 * @return Response
	 */
	public function handle_reset_password( Request $request ) : Response {
		$check = $this->reset_tokens->verify( (string) $request->get( 'token', '', false ) );

		if ( ! $check['valid'] ) {
			return static::error_response( 401, 'token_error', $check['reason'] );
		}

		$user       = $check['user'];
		$password_1 = (string) $request->get( 'password_1', '', false );
		$problem    = $this->accounts->validate_password( $password_1, (string) $request->get( 'password_2', '', false ) );

		if ( null !== $problem ) {
			return static::error_response( 400, $problem->get_error_code(), $problem->get_error_message() );
		}

		try {
			$reset = $this->id_provider->reset_password( $user, $password_1 );
		} catch ( Exception $e ) {
			return static::error_response(
				400,
				$e->get_error_code(),
				$e->get_error_message()
			);
		}

		if ( ! $reset ) {
			return static::error_response(
				500,
				'reset_failed',
				'The password could not be changed. Please try again.'
			);
		}

		$this->reset_tokens->consume( $user );

		return static::success_response(
			200,
			[
				'success'  => true,
				'message'  => 'Your password has been changed. Sign in with the new one.',
				'redirect' => $this->urlmanager->login_url()->url(),
			]
		);
	}

	/*
	|------------------------
	| HELPERS - VALIDATION
	|------------------------
	*/

	/**
	 * Validate email format.
	 *
	 * @param string $email
	 * @return bool
	 */
	private static function is_valid_email( string $email ) : bool {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/*
	|--------------------------------------------------
	| HELPERS - RESPONSES
	|--------------------------------------------------
	*/

	/**
	 * Return error response.
	 *
	 * @param int $status HTTP status code
	 * @param string $code Error code
	 * @param string $message Error message
	 * @return Response
	 */
	private static function error_response(
		int $status,
		string $code,
		string $message
	) : Response {
		return ( new Response( $status ) )
			->set_body( [
				'success' => false,
				'code'    => $code,
				'message' => $message,
			] )
			->set_header( 'Content-Type', 'application/json; charset=utf-8' );
	}

	/**
	 * Return success response.
	 *
	 * @param int $status HTTP status code
	 * @param array $data Response data
	 * @return Response
	 */
	private static function success_response( int $status, array $data ) : Response {
		return ( new Response( $status ) )
			->set_body( $data )
			->set_header( 'Content-Type', 'application/json; charset=utf-8' );
	}
}
