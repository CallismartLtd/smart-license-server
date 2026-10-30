<?php
/**
 * Web identity provider class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Auth;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;

use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Authentication\Session\UserSessionCutoff;
use SmartLicenseServer\Security\Authentication\UserAccounts;
use SmartLicenseServer\Security\Authentication\UserAuthenticator;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\OwnerSubjects\OwnerSubjectInterface;
use SmartLicenseServer\Security\Permission\Role;

class WebIdentityProvider implements PasswordIdentityProviderInterface {

	/**
	 * Constructor.
	 *
	 * @param SessionManager    $sessions Session manager.
	 * @param Guard             $guard    The security guard.
	 * @param UserSessionCutoff $cutoff   Per-user session cut-off.
	 * @param UserAccounts      $accounts Shared account operations.
	 */
	public function __construct(
		protected SessionManager $sessions,
		protected Guard $guard,
		protected UserSessionCutoff $cutoff,
		protected UserAccounts $accounts
	) {}

	/**
	 * {@inheritdoc}
	 */
	public function authenticate() : ?Principal {
		$session = $this->sessions->resolve();

		if ( null === $session ) {
			return null;
		}

		$user = User::get_by_id( $session->principal_id );

		// A suspended or disabled account loses access on its next request.
		if ( ! $user || ! $user->can_authenticate() ) {
			return null;
		}

		$context = $this->resolve_context( $user, $session->claim( 'owner_id' ) );

		if ( null === $context ) {
			return null;
		}

		[ $owner, $role ] = $context;

		$principal = new Principal( $user, $role, $owner );

		$this->guard->set_principal( $principal );

		return $principal;
	}

	/**
	 * Act for someone else: the user themselves, or one of their organizations.
	 *
	 * Allowed only for a context ContextServiceProvider::get_context_role()
	 * accepts. The session keeps its sign-in time and expiry; only the owner
	 * it acts for changes. An organization gets its resource owner record
	 * here if it has none (ContextServiceProvider::ensure_owner()).
	 *
	 * @param OwnerSubjectInterface $subject The signed-in user, or an organization.
	 * @return RequestException|Principal The new principal, or why the switch was refused.
	 */
	public function switch_context( OwnerSubjectInterface $subject ) : RequestException|Principal {
		$user = $this->guard->get_principal()?->get_actor();

		if ( ! $user instanceof User ) {
			return new RequestException( 'not_signed_in', 'Sign in to switch who you act for.', [ 'status' => 401 ] );
		}

		$role = ContextServiceProvider::get_context_role( $user, $subject );

		if ( ! $role ) {
			return new RequestException( 'context_forbidden', sprintf( 'You cannot act for %s.', $subject->get_display_name() ), [ 'status' => 403 ] );
		}

		try {
			$owner = $subject instanceof User
				? ContextServiceProvider::get_self_owner( $user, $role )
				: ContextServiceProvider::ensure_owner( $subject );

			$this->sessions->update_claims( [ 'owner_id' => $owner?->get_id() ] );
		} catch ( \Throwable $e ) {
			return new RequestException( 'context_switch_failed', 'Switching failed. Please try again.', [ 'status' => 500 ] );
		}

		$principal = new Principal( $user, $role, $owner );

		$this->guard->set_principal( $principal );

		return $principal;
	}

	/**
	 * The owner and role a user acts with, from their session's owner claim.
	 *
	 * The claimed owner is used while the user may still act for it. When
	 * they may not any more (removed from the organization, the organization
	 * suspended or deleted) they act for themselves again, rather than being
	 * signed out.
	 *
	 * @param User  $user     The signed-in user.
	 * @param mixed $owner_id The session's owner_id claim.
	 * @return array{0: Owner|null, 1: Role}|null Null when the user has no role at all.
	 */
	protected function resolve_context( User $user, mixed $owner_id ) : ?array {
		$owner   = null !== $owner_id ? Owner::get_by_id( (int) $owner_id ) : null;
		$subject = $owner ? ContextServiceProvider::get_owner_subject( $owner ) : null;
		$role    = $subject ? ContextServiceProvider::get_context_role( $user, $subject ) : null;

		if ( $owner && $role ) {
			return [ $owner, $role ];
		}

		$role = ContextServiceProvider::get_context_role( $user, $user );

		if ( ! $role ) {
			return null;
		}

		try {
			$owner = ContextServiceProvider::get_self_owner( $user, $role );
		} catch ( \Throwable ) {
			$owner = ContextServiceProvider::get_owner_for( $user );
		}

		return [ $owner, $role ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function logon(
		string $email,
		#[\SensitiveParameter] string $pwd,
		bool $remember = false
	) : RequestException|Principal {

		$auth_result = ( new UserAuthenticator( $email, $pwd ) )->authenticate();

		if ( ! $auth_result->is_authenticated() ) {
			return new RequestException(
				$auth_result->error_code,
				$auth_result->message
			);
		}

		$claims = [];

		if ( $auth_result->owner ) {
			$claims['owner_id'] = $auth_result->owner->get_id();
		}

		$this->sessions->create(
			$auth_result->actor->get_id(),
			$claims,
			$remember
		);

		$principal = new Principal(
			$auth_result->actor,
			$auth_result->role,
			$auth_result->owner
		);

		$this->authenticate();

		return $principal;
	}

	/**
	 * {@inheritdoc}
	 */
	public function signup( Request $request ) : RequestException|Principal {
		$email = trim( (string) $request->get( 'email', '' ) );

		if ( '' === $email || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return new RequestException( 'invalid_email', 'Please provide a valid email address.', [ 'status' => 400 ] );
		}

		if ( ! $this->accounts->acquire_signup_lock( $email ) ) {
			return new RequestException( 'signup_locked', 'Too many signup attempts, please try again later.', [ 'status' => 429 ] );
		}

		try {
			$password = (string) $request->get( 'password_1', '', false );
			$problem  = $this->accounts->validate_password( $password, (string) $request->get( 'password_2', '', false ) );

			if ( null !== $problem ) {
				return $problem;
			}

			if ( User::email_exists( $email ) ) {
				return new RequestException( 'email_exists', 'An account with this email address already exists. Sign in, or reset your password.', [ 'status' => 409 ] );
			}

			$user = $this->accounts->create_user( $email, $password, (string) $request->get( 'full_name', '' ) );

			$this->accounts->assign_signup_role( $user, (string) $request->get( 'account_type', 'viewer' ) );

			// Signed in straight away, like a normal logon.
			$this->sessions->create( $user->get_id() );

			return $this->authenticate()
				?? new RequestException( 'authentication_error', 'Your account was created, but signing in failed. Please sign in.', [ 'status' => 500 ] );

		} catch ( RequestException $e ) {
			return $e;
		} catch ( \Throwable ) {
			return new RequestException( 'signup_error', 'An error occurred during signup, please try again.', [ 'status' => 500 ] );
		} finally {
			$this->accounts->release_signup_lock( $email );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * Signs out this browser only; sessions on other devices stay valid.
	 */
	public function logout() : void {
		$this->sessions->invalidate();
	}

	/**
	 * {@inheritdoc}
	 *
	 * Sets the user's session cut-off, so every session that signed in
	 * until now is rejected on its next request, then removes this
	 * browser's cookie. Does nothing beyond that without a signed-in user.
	 *
	 * @return void
	 */
	public function logout_everywhere() : void {
		$session = $this->sessions->resolve();
		$user    = null !== $session ? User::get_by_id( $session->principal_id ) : null;

		if ( $user instanceof User ) {
			$this->cutoff->revoke_all( $user );
		}

		$this->sessions->invalidate();
	}

	/**
	 * {@inheritdoc}
	 */
	public function reset_password( User $user, #[\SensitiveParameter] string $new_pwd ) : bool {
		$this->accounts->change_password( $user, $new_pwd );

		// Whoever held the old password may hold a session: end them all.
		$this->cutoff->revoke_all( $user );

		return true;
	}
}
