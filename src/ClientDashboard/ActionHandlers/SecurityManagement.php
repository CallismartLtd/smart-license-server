<?php
/**
 * Client account-security actions.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ActionHandlers;

use SmartLicenseServer\Background\Jobs\Accounts\EmailOtpJob;
use SmartLicenseServer\Background\Jobs\Accounts\SecurityNoticeEmailJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\AuthenticationFactorManager;
use SmartLicenseServer\Security\Authentication\PasskeyStore;
use SmartLicenseServer\Security\Authentication\WebAuthnManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Self-service operations for a human user's sign-in security: second factors
 * (email codes, authenticator app) and passkeys. A passkey is not a second
 * factor, it is an alternative to the password.
 */
final class SecurityManagement {
	use QueueAwareTrait;

	public function __construct(
		private Guard $guard,
		private AuthenticationFactorManager $factors,
		private URLManager $urls,
		private WebAuthnManager $webauthn,
		private PasskeyStore $passkeys,
		JobQueue $job_queue
	) {
		$this->job_queue = $job_queue;
	}

	/** Start email-factor enrollment after password reauthentication. */
	public function start_email( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user ) {
			return $this->answer( false, 'Sign in with a user account to manage security settings.', 403 );
		}
		if ( ! $this->password_matches( $user, (string) $request->post( 'password', '', false ) ) ) {
			return $this->answer( false, 'Your current password is incorrect.', 403 );
		}
		if ( ! $user->is_email_verified() ) {
			return $this->answer( false, 'Verify your email address before enabling email sign-in codes.', 400 );
		}
		if ( $this->factors->status( (int) $user->get_id() )['email'] ) {
			return $this->answer( false, 'Email verification is already enabled.', 409 );
		}

		try {
			$challenge = $this->factors->create_email_code_challenge( (int) $user->get_id(), 'enroll_email' );
			$this->send_code( $user, $challenge['code'], 'enroll_email', $request );
		} catch ( \Throwable $e ) {
			if ( isset( $challenge['token'] ) ) {
				$this->factors->consume_challenge( $challenge['token'] );
			}
			\smliser_log_error( sprintf( '[Authentication] Could not send an email enrollment code: %s', $e->getMessage() ) );
			return $this->answer( false, 'The verification code could not be sent. Please try again.', 503 );
		}

		return $this->answer( true, 'A verification code was sent to your email address.', 200, $this->urls->client_dashboard_url( 'security', [ 'email_challenge' => $challenge['token'] ] )->url() );
	}

	/** Verify the email enrollment code and enable the factor. */
	public function verify_email( Request $request ) : Response {
		$user = $this->current_user();
		$token = trim( (string) $request->post( 'challenge', '' ) );
		if ( ! $user ) {
			return $this->answer( false, 'Sign in with a user account to manage security settings.', 403 );
		}
		$existing = $this->factors->get_challenge( $token, 'enroll_email' );
		if ( ! $existing || (int) $existing['user_id'] !== (int) $user->get_id() ) {
			return $this->answer( false, 'This verification request has expired. Start again.', 400 );
		}
		$challenge = $this->factors->verify_email_code( $token, 'enroll_email', (string) $request->post( 'code', '' ) );
		if ( ! $challenge ) {
			return $this->answer( false, 'The verification code is incorrect or expired.', 400 );
		}
		$this->factors->enable_email( (int) $user->get_id() );
		$this->notify( $user, 'two_factor_enabled', $request, 'email' );
		return $this->answer( true, 'Email two-factor authentication is enabled.', 200, $this->urls->client_dashboard_url( 'security' )->url() );
	}

	/** Begin TOTP enrollment; the seed remains pending until a valid code is entered. */
	public function start_totp( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user ) {
			return $this->answer( false, 'Sign in with a user account to manage security settings.', 403 );
		}
		if ( ! $this->password_matches( $user, (string) $request->post( 'password', '', false ) ) ) {
			return $this->answer( false, 'Your current password is incorrect.', 403 );
		}
		if ( $this->factors->status( (int) $user->get_id() )['totp'] ) {
			return $this->answer( false, 'An authenticator app is already enabled.', 409 );
		}

		try {
			$secret = $this->factors->new_totp_secret();
			$token  = $this->factors->create_challenge(
				(int) $user->get_id(),
				'enroll_totp',
				[ 'secret' => $this->factors->encrypt_totp_secret( $secret ) ]
			);
		} catch ( \Throwable $e ) {
			\smliser_log_error( sprintf( '[Authentication] Could not start authenticator enrollment: %s', $e->getMessage() ) );
			return $this->answer( false, 'Authenticator enrollment could not be started. Please try again.', 503 );
		}

		return $this->answer( true, 'Scan the secret in your authenticator app, then enter its current code.', 200, $this->urls->client_dashboard_url( 'security', [ 'totp_challenge' => $token ] )->url() );
	}

	/** Verify the pending authenticator and activate it, returning recovery codes once. */
	public function verify_totp( Request $request ) : Response {
		$user = $this->current_user();
		$token = trim( (string) $request->post( 'challenge', '' ) );
		if ( ! $user ) {
			return $this->answer( false, 'Sign in with a user account to manage security settings.', 403 );
		}
		$challenge = $this->factors->get_challenge( $token, 'enroll_totp' );
		if ( ! $challenge || (int) $challenge['user_id'] !== (int) $user->get_id() ) {
			return $this->answer( false, 'This authenticator setup has expired. Start again.', 400 );
		}
		$secret  = $this->factors->decrypt_totp_secret( (string) ( $challenge['payload']['secret'] ?? '' ) );
		$counter = null === $secret ? null : $this->factors->totp_counter( $secret, (string) $request->post( 'code', '' ) );
		if ( null === $counter ) {
			$this->factors->record_challenge_failure( $token );
			return $this->answer( false, 'The authenticator code is incorrect.', 400 );
		}
		if ( ! $this->factors->consume_challenge( $token ) ) {
			return $this->answer( false, 'This authenticator setup has already been used.', 409 );
		}
		$this->factors->enable_totp( (int) $user->get_id(), $secret, $counter );
		$codes = $this->factors->regenerate_recovery_codes( (int) $user->get_id() );
		$this->notify( $user, 'two_factor_enabled', $request, 'totp' );
		return $this->answer(
			true,
			'Authenticator app enabled. Save these one-time recovery codes now: ' . implode( '  ', $codes ),
			200
		);
	}

	/** Start passkey registration after password reauthentication. The passkey is used to sign in instead of the password. */
	public function start_passkey( Request $request ) : Response {
		$user = $this->current_user();
		if ( ! $user || ! $this->password_matches( $user, (string) $request->post( 'password', '', false ) ) ) {
			return $this->answer( false, 'Your current password is incorrect.', 403 );
		}
		try {
			$setup = $this->webauthn->begin_registration( $user, (string) $request->post( 'label', 'Passkey' ) );
			return Response::json( [ 'success' => true, 'challenge' => $setup['challenge'], 'options' => json_decode( $setup['options'], true, 32, JSON_THROW_ON_ERROR ) ] );
		} catch ( \Throwable $e ) {
			\smliser_log_error( '[Authentication] Passkey registration could not start: ' . $e->getMessage() );
			return $this->answer( false, 'Passkey registration could not be started.', 503 );
		}
	}

	/** Complete a browser passkey registration. */
	public function finish_passkey( Request $request ) : Response {
		$user = $this->current_user();
		$credential = (string) $request->post( 'credential', '' );
		if ( ! $user || ! $this->webauthn->finish_registration( $user, (string) $request->post( 'challenge', '' ), $credential ) ) {
			return $this->answer( false, 'Passkey registration failed or expired. Please try again.', 400 );
		}
		$this->notify( $user, 'passkey_added', $request );
		return $this->answer( true, 'Passkey registered successfully.', 200, $this->urls->client_dashboard_url( 'security' )->url() );
	}

	/** Revoke a passkey after password reauthentication. */
	public function revoke_passkey( Request $request ) : Response {
		$user = $this->current_user();
		$id = (int) $request->post( 'id', 0 );
		if ( ! $user || ! $this->password_matches( $user, (string) $request->post( 'password', '', false ) ) ) {
			return $this->answer( false, 'Your current password is incorrect.', 403 );
		}
		if ( $id < 1 || ! $this->passkeys->revoke( (int) $user->get_id(), $id ) ) {
			return $this->answer( false, 'That passkey could not be found.', 404 );
		}
		$this->notify( $user, 'passkey_removed', $request );
		return $this->answer( true, 'Passkey removed.', 200, $this->urls->client_dashboard_url( 'security' )->url() );
	}

	/** Disable email or TOTP after password and current-factor confirmation. */
	public function disable( Request $request ) : Response {
		$user   = $this->current_user();
		$factor = (string) $request->post( 'factor', '' );
		$code   = trim( (string) $request->post( 'code', '' ) );
		if ( ! $user || ! in_array( $factor, [ 'email', 'totp' ], true ) ) {
			return $this->answer( false, 'Choose a supported security factor.', 400 );
		}
		if ( ! $this->password_matches( $user, (string) $request->post( 'password', '', false ) ) ) {
			return $this->answer( false, 'Your current password is incorrect.', 403 );
		}

		$status = $this->factors->status( (int) $user->get_id() );
		if ( ! $status[ $factor ] ) {
			return $this->answer( false, 'That factor is not enabled.', 409 );
		}
		$valid = false;
		if ( 'email' === $factor ) {
			$challenge_token = trim( (string) $request->post( 'challenge', '' ) );
			if ( '' === $challenge_token ) {
				try {
					$challenge = $this->factors->create_email_code_challenge( (int) $user->get_id(), 'disable_email' );
					$this->send_code( $user, $challenge['code'], 'disable_email', $request );
				} catch ( \Throwable $e ) {
					if ( isset( $challenge['token'] ) ) {
						$this->factors->consume_challenge( $challenge['token'] );
					}
					\smliser_log_error( sprintf( '[Authentication] Could not send an email factor removal code: %s', $e->getMessage() ) );
					return $this->answer( false, 'A confirmation code could not be sent. Please try again.', 503 );
				}
				return $this->answer( true, 'A confirmation code was sent to your verified email address.', 200, $this->urls->client_dashboard_url( 'security', [ 'email_disable_challenge' => $challenge['token'] ] )->url() );
			}
			$pending = $this->factors->get_challenge( $challenge_token, 'disable_email' );
			if ( $pending && (int) $pending['user_id'] === (int) $user->get_id() ) {
				$valid = null !== $this->factors->verify_email_code( $challenge_token, 'disable_email', $code );
			}
		} else {
			$valid = $this->factors->consume_totp( (int) $user->get_id(), $code )
				|| $this->factors->consume_recovery_code( (int) $user->get_id(), $code );
		}
		if ( ! $valid ) {
			return $this->answer( false, 'The confirmation code is incorrect or expired.', 400 );
		}

		$this->factors->disable_factor( (int) $user->get_id(), $factor );
		$this->notify( $user, 'two_factor_disabled', $request, $factor );
		return $this->answer( true, ucfirst( $factor ) . ' two-factor authentication is disabled.', 200, $this->urls->client_dashboard_url( 'security' )->url() );
	}

	private function current_user() : ?User {
		$actor = $this->guard->get_principal()?->get_actor();
		return $actor instanceof User ? $actor : null;
	}

	private function password_matches( User $user, string $password ) : bool {
		return '' !== $password && password_verify( $password, $user->get_password_hash() );
	}

	/**
	 * Queue a one-time code email.
	 *
	 * The code is sealed because job payloads are stored as readable JSON.
	 * Throws when the job cannot be queued, so callers can discard the challenge.
	 *
	 * @param string $purpose Challenge purpose: enroll_email | disable_email.
	 */
	private function send_code( User $user, string $code, string $purpose, Request $request ) : void {
		$this->dispatch_job( EmailOtpJob::class, [
			'user_id'     => $user->get_id(),
			'code'        => $this->factors->seal_code( $code ),
			'purpose'     => $purpose,
			'ip_address'  => $request->ip(),
			'user_agent'  => $request->userAgent(),
			'occurred_at' => time(),
		] );
	}

	/**
	 * Queue a security notice for a change that has already been made.
	 *
	 * A failure to queue is logged and swallowed: the change stands, and it
	 * must not be reported to the user as failed.
	 *
	 * @param string $notice One of the SecurityNoticeEmailJob notice keys.
	 * @param string $factor 'email' or 'totp', for two-factor notices.
	 */
	private function notify( User $user, string $notice, Request $request, string $factor = '' ) : void {
		try {
			$this->dispatch_job( SecurityNoticeEmailJob::class, [
				'user_id'     => $user->get_id(),
				'notice'      => $notice,
				'factor'      => $factor,
				'ip_address'  => $request->ip(),
				'user_agent'  => $request->userAgent(),
				'occurred_at' => time(),
			] );
		} catch ( \Throwable $e ) {
			\smliser_log_error( sprintf( '[Authentication] Could not queue the "%s" security notice: %s', $notice, $e->getMessage() ) );
		}
	}

	private function answer( bool $success, string $message, int $status, string $redirect = '' ) : Response {
		$body = [ 'success' => $success, 'message' => $message ];
		if ( '' !== $redirect ) {
			$body['redirect'] = $redirect;
		}
		return Response::json( $body, $status );
	}
}
