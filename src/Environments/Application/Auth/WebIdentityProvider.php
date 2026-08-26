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
use SmartLicenseServer\Security\Authentication\UserAuthenticator;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Owner;

class WebIdentityProvider implements PasswordIdentityProviderInterface {

	/**
	 * Constructor.
	 *
	 * @param SessionManager $sessions Session manager.
	 */
	public function __construct(
		protected SessionManager $sessions,
		protected Guard $guard
		
	) {}

	/**
	 * {@inheritdoc}
	 */
	public function authenticate(): ?Principal {
		$session = $this->sessions->resolve();

		if ( null === $session ) {
			return null;
		}

		$user = User::get_by_id( $session->principal_id );

		if ( ! $user ) {
			return null;
		}

		$owner_id = $session->claim( 'owner_id' );

		$owner = null;

		if ( null !== $owner_id ) {
			$owner = Owner::get_by_id( (int) $owner_id );
		}

		$owner_subject = $owner
			? ContextServiceProvider::get_owner_subject( $owner )
			: null;

		$role = ContextServiceProvider::get_principal_role(
			$user,
			$owner_subject
		);

		if ( ! $role ) {
			return null;
		}

		$principal	= new Principal( $user, $role, $owner );

		$this->guard->set_principal( $principal );

		return $principal;
	}

	/**
	 * {@inheritdoc}
	 */
	public function logon(
		string $email,
		#[\SensitiveParameter] string $pwd,
		bool $remember = false
	): RequestException|Principal {

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
			$claims
		);

		return new Principal(
			$auth_result->actor,
			$auth_result->role,
			$auth_result->owner
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function signup( Request $request ): RequestException|Principal {
		throw new \Exception( 'Not implemented' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function logout(): void {
		$this->sessions->invalidate();
	}

	/**
	 * {@inheritdoc}
	 */
	public function reset_password(
		User $user,
		string $new_pwd
	): bool {
		throw new \Exception( 'Not implemented' );
	}
}