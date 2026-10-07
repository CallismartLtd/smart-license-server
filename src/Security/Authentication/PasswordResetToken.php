<?php
/**
 * PasswordResetToken class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication;

use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\SettingsAPI\UserSettings;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

/**
 * Issues and verifies password reset tokens.
 *
 * A token is a signed payload (user id, issue time, random nonce). Only the
 * SHA-256 of the latest token is kept, in the cache under one key per user,
 * expiring with the token. So each user has at most one valid link,
 * requesting a new one replaces it, using it once removes it, and nothing
 * is stored for email addresses that have no account.
 */
final class PasswordResetToken {
	use TokenDeliveryTrait;

	/**
	 * How long a reset link stays valid, in minutes.
	 *
	 * @var int
	 */
	public const TTL_MINUTES = 15;

	/**
	 * @param Cache $cache The application cache.
	 */
	public function __construct(
		private Cache $cache
	) {}

	/**
	 * Create a token for the user, replacing any earlier one.
	 *
	 * @param User $user The user resetting their password.
	 * @return string URL-safe token.
	 */
	public function issue( User $user ): string {
		$payload = \smliser_safe_json_encode(
			[
				'id'        => $user->get_id(),
				'timestamp' => time(),
				'nonce'     => static::generate_secure_token(),
			]
		);

		$signature = static::hmac_hash( $payload, static::derive_key(), 'sha256' );
		$token     = static::base64url_encode( sprintf( '%s.%s', $payload, $signature ) );

		$this->cache->set( $this->key( (int) $user->get_id() ), hash( 'sha256', $token ), self::TTL_MINUTES * 60 );

		return $token;
	}

	/**
	 * Check a token.
	 *
	 * @param string $token Token from the reset link.
	 * @return array{valid: bool, user?: User, reason?: string}
	 */
	public function verify( #[\SensitiveParameter] string $token ): array {
		$decoded = static::base64url_decode( $token );

		if ( ! $decoded || ! str_contains( $decoded, '.' ) ) {
			return [ 'valid' => false, 'reason' => 'Invalid token format' ];
		}

		[ $payload, $signature ] = explode( '.', $decoded, 2 );

		if ( ! hash_equals( static::hmac_hash( $payload, static::derive_key(), 'sha256' ), $signature ) ) {
			return [ 'valid' => false, 'reason' => 'Invalid signature' ];
		}

		$data = json_decode( $payload, true );

		if ( ! is_array( $data ) || empty( $data['id'] ) || ! isset( $data['timestamp'] ) ) {
			return [ 'valid' => false, 'reason' => 'Invalid payload' ];
		}

		if ( (int) $data['timestamp'] + self::TTL_MINUTES * 60 < time() ) {
			return [ 'valid' => false, 'reason' => 'Token expired' ];
		}

		$stored = $this->cache->get( $this->key( (int) $data['id'] ) );

		if ( ! is_string( $stored ) || ! hash_equals( $stored, hash( 'sha256', $token ) ) ) {
			return [ 'valid' => false, 'reason' => 'Token already used or invalidated' ];
		}

		$user = User::get_by_id( (int) $data['id'] );

		if ( ! $user ) {
			return [ 'valid' => false, 'reason' => 'User no longer exists.' ];
		}

		return [ 'valid' => true, 'user' => $user ];
	}

	/**
	 * Invalidate the user's token after a successful reset.
	 *
	 * @param User $user The user.
	 * @return void
	 */
	public function consume( User $user ): void {
		$this->cache->delete( $this->key( (int) $user->get_id() ) );
	}

	/**
	 * Cache key holding a user's token hash.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function key( int $user_id ): string {
		return sprintf( '%s_%d', UserSettings::PWD_RESET_NAME, $user_id );
	}
}