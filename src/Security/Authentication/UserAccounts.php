<?php
/**
 * UserAccounts class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication;

use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Exceptions\SecurityException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Account operations shared by every password identity provider.
 *
 * The standalone and WordPress identity providers differ in how they keep
 * a user signed in, not in what a Smart License Server account is. Creating
 * a user, giving a new account its role and changing a password live here
 * once, so the providers only add their own session handling.
 */
final class UserAccounts {

	/**
	 * Shortest password accepted for a new account or a reset.
	 *
	 * @var int
	 */
	public const MIN_PASSWORD_LENGTH = 8;

	/**
	 * Account types a visitor may choose at signup, as role slugs.
	 *
	 * @var string[]
	 */
	public const SIGNUP_ACCOUNT_TYPES = [ 'viewer', 'resource_owner' ];

	/**
	 * How long a signup for one email address blocks another, in seconds.
	 *
	 * @var int
	 */
	private const SIGNUP_LOCK_SECONDS = 300;

	/**
	 * @param Cache $cache The application cache.
	 */
	public function __construct(
		private Cache $cache
	) {}

	/*
	|------------
	| Passwords
	|------------
	*/

	/**
	 * Hash a password for storage.
	 *
	 * Argon2id when PHP was built with it, otherwise PHP's default (bcrypt).
	 * password_verify() accepts either, so existing hashes keep working.
	 *
	 * @param string $password Plain password.
	 * @return string
	 */
	public function hash_password( #[\SensitiveParameter] string $password ): string {
		return password_hash( $password, defined( 'PASSWORD_ARGON2ID' ) ? \PASSWORD_ARGON2ID : \PASSWORD_DEFAULT );
	}

	/**
	 * Check a new password and its confirmation.
	 *
	 * @param string $password Password.
	 * @param string $confirm  Confirmation.
	 * @return RequestException|null The problem, or null when the password is acceptable.
	 */
	public function validate_password( #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $confirm ): ?RequestException {
		if ( '' === $password ) {
			return new RequestException( 'empty_password', 'Password must not be empty.', [ 'status' => 400 ] );
		}

		if ( strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			return new RequestException(
				'weak_password',
				sprintf( 'Password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH ),
				[ 'status' => 400 ]
			);
		}

		if ( $password !== $confirm ) {
			return new RequestException( 'password_mismatch', 'Passwords do not match.', [ 'status' => 400 ] );
		}

		return null;
	}

	/**
	 * Store a new password for a user.
	 *
	 * Does not end the user's sessions; each identity provider does that its own way.
	 *
	 * @param User   $user     The user.
	 * @param string $password New plain password.
	 * @return void
	 *
	 * @throws SecurityException When the password is empty or cannot be saved.
	 */
	public function change_password( User $user, #[\SensitiveParameter] string $password ): void {
		if ( '' === $password ) {
			throw new SecurityException( 'empty_password', 'New password cannot be empty.' );
		}

		if ( ! $user->set_password_hash( $this->hash_password( $password ) )->save() ) {
			throw new SecurityException( 'password_save_error', 'Unable to set new password', [ 'status' => 500 ] );
		}
	}

	/*
	|----------
	| Signup
	|----------
	*/

	/**
	 * Create an active user.
	 *
	 * @param string $email        Email address.
	 * @param string $password     Plain password.
	 * @param string $display_name Display name; the part of the email before "@" when empty.
	 * @return User
	 *
	 * @throws RequestException When the user cannot be saved.
	 */
	public function create_user( string $email, #[\SensitiveParameter] string $password, string $display_name = '' ): User {
		$user = ( new User() )
			->set_display_name( '' !== trim( $display_name ) ? $display_name : strstr( $email, '@', true ) )
			->set_password_hash( $this->hash_password( $password ) )
			->set_status( User::STATUS_ACTIVE )
			->set_email( $email );

		if ( ! $user->save() ) {
			throw new RequestException( 'user_save_error', 'Unable to save user, try again.', [ 'status' => 500 ] );
		}

		return $user;
	}

	/**
	 * Give a new account the role of the account type chosen at signup.
	 *
	 * Anything other than an allowed signup type becomes "viewer".
	 *
	 * @param User   $user         The new user.
	 * @param string $account_type Chosen account type.
	 * @return Role The role given.
	 *
	 * @throws RequestException When the role does not exist.
	 */
	public function assign_signup_role( User $user, string $account_type ): Role {
		if ( ! in_array( $account_type, self::SIGNUP_ACCOUNT_TYPES, true ) ) {
			$account_type = 'viewer';
		}

		$role = Role::get_by_slug( $account_type );

		if ( ! $role || ! $role->exists() ) {
			throw new RequestException( 'invalid_account_type', 'The selected account type is invalid.', [ 'status' => 400 ] );
		}

		ContextServiceProvider::save_actor_role( $user, $role );

		return $role;
	}

	/**
	 * Claim the signup lock for an email address.
	 *
	 * Stops two signups for the same address from running at once (double
	 * submits, scripted retries). Release it with release_signup_lock().
	 *
	 * @param string $email Email address.
	 * @return bool False when another signup for the address holds the lock.
	 */
	public function acquire_signup_lock( string $email ): bool {
		$key = $this->signup_lock_key( $email );

		if ( $this->cache->has( $key ) ) {
			return false;
		}

		$this->cache->set( $key, time(), self::SIGNUP_LOCK_SECONDS );

		return true;
	}

	/**
	 * Release the signup lock for an email address.
	 *
	 * @param string $email Email address.
	 * @return void
	 */
	public function release_signup_lock( string $email ): void {
		$this->cache->delete( $this->signup_lock_key( $email ) );
	}

	/**
	 * Cache key of the signup lock for an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function signup_lock_key( string $email ): string {
		return 'signup_lock_' . md5( strtolower( trim( $email ) ) );
	}
}