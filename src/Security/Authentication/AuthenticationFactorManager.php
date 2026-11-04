<?php
/**
 * Authentication factor persistence and cryptographic helpers.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Authentication;

use Callismart\DBPrism\Database;
use SmartLicenseServer\Schema\TableName;
use Throwable;

/**
 * Manages persisted second-factor state and short-lived challenges.
 *
 * TOTP seeds are encrypted with an application-secret-derived key. Challenge
 * identifiers are random bearer tokens; only their SHA-256 digests are stored.
 */
final class AuthenticationFactorManager {
	private const CHALLENGE_TTL = 600;
	private const MAX_CHALLENGE_ATTEMPTS = 5;
	private const TOTP_STEP = 30;
	private const TOTP_DIGITS = 6;
	private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	private string $totp_key;
	private string $challenge_key;

	public function __construct(
		private Database $db,
		#[\SensitiveParameter] string $secret,
		#[\SensitiveParameter] string $salt
	) {
		$this->totp_key      = hash_hkdf( 'sha256', $secret, 32, 'authentication_totp', $salt );
		$this->challenge_key = hash_hkdf( 'sha256', $secret, 32, 'authentication_challenges', $salt );
	}

	/**
	 * @return array{
	 * 	email: bool,
	 * 	totp: bool,
	 * 	passkeys: array<int,array<string,mixed>>,
	 * 	recovery_codes: int
	 * }
	 */
	public function status( int $user_id ) : array {
		$row      = $this->security_row( $user_id );
		$passkeys = $this->passkeys( $user_id );
		$codes    = json_decode( (string) ( $row['recovery_code_hashes'] ?? '[]' ), true );

		return [
			'email'         => ! empty( $row['email_enabled_at'] ),
			'totp'          => ! empty( $row['totp_enabled_at'] ) && ! empty( $row['totp_secret'] ),
			'passkeys'      => array_map( static fn ( array $item ) : array => [
				'id'         => (int) $item['id'],
				'label'      => (string) $item['label'],
				'created_at' => (string) $item['created_at'],
				'last_used'  => (string) ( $item['last_used_at'] ?? '' ),
			], $passkeys ),
			'recovery_codes' => is_array( $codes ) ? count( $codes ) : 0,
		];
	}

	/**
	 * Whether a user must pass a second factor after a password. 
	 * 
	 * @return bool
	 */
	public function requires_second_factor( int $user_id ) : bool {
		$status = $this->status( $user_id );
		return $status['email'] || $status['totp'] || ! empty( $status['passkeys'] );
	}

	/**
	 * Enable a factor after the user has proven control of it.
	 */
	public function enable_email( int $user_id ) : void {
		$this->upsert_security( $user_id, [
			'email_enabled_at' => gmdate( 'Y-m-d H:i:s' ),
			'updated_at'       => gmdate( 'Y-m-d H:i:s' ),
		] );
	}

	/**
	 * Save an already verified TOTP secret.
	 */
	public function enable_totp( int $user_id, string $secret, int $verified_counter ) : void {
		$this->upsert_security( $user_id, [
			'totp_secret'      => $this->encrypt_totp_secret( $secret ),
			'totp_enabled_at'  => gmdate( 'Y-m-d H:i:s' ),
			'totp_last_counter'=> $verified_counter,
			'updated_at'       => gmdate( 'Y-m-d H:i:s' ),
		] );
	}

	/** 
	 * Disable a factor and clear its stored value.
	 */
	public function disable_factor( int $user_id, string $factor ) : void {
		$data = [ 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ];
		if ( 'email' === $factor ) {
			$data['email_enabled_at'] = null;
		} elseif ( 'totp' === $factor ) {
			$data['totp_secret']       = null;
			$data['totp_enabled_at']   = null;
			$data['totp_last_counter'] = null;
		} else {
			throw new \InvalidArgumentException( 'Unsupported authentication factor.' );
		}
		$this->upsert_security( $user_id, $data );
	}

	/**
	 * Reset all authentication factors and recovery codes for account recovery.
	 */
	public function reset_all_factors( int $user_id ) : void {
		$this->upsert_security( $user_id, [
			'totp_secret'          => null,
			'totp_enabled_at'      => null,
			'totp_last_counter'    => null,
			'email_enabled_at'     => null,
			'recovery_code_hashes' => null,
			'updated_at'           => gmdate( 'Y-m-d H:i:s' ),
		] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function security_row( int $user_id ) : ?array {
		$sql = \smliserQueryBuilder( $this->db->get_driver() )
			->select()->from( TableName::USER_SECURITY->table() )
			->where( 'user_id', '=', $user_id )->limit( 1 );
		return $this->db->get_row( $sql->build(), $sql->get_bindings() );
	}

	/**
	 * Create a typed challenge and return its opaque token.
	 *
	 * @param int|null            $user_id
	 * @param string              $purpose
	 * @param array<string,mixed> $payload
	 * @param int                 $ttl
	 */
	public function create_challenge( ?int $user_id, string $purpose, array $payload, int $ttl = self::CHALLENGE_TTL ) : string {
		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$hash  = hash( 'sha256', $token );
		$now   = time();
		$ok    = $this->db->insert( TableName::AUTH_CHALLENGES->table(), [
			'id'         => $hash,
			'user_id'    => $user_id,
			'purpose'    => $purpose,
			'payload'    => json_encode( $payload, JSON_THROW_ON_ERROR ),
			'attempts'   => 0,
			'expires_at' => gmdate( 'Y-m-d H:i:s', $now + $ttl ),
			'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
		] );

		if ( false === $ok ) {
			throw new \RuntimeException( 'Could not create the authentication challenge.' );
		}

		return $token;
	}

	/** @return array<string,mixed>|null */
	public function get_challenge( string $token, string $purpose ) : ?array {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{40,50}$/', $token ) ) {
			return null;
		}

		$sql = \smliserQueryBuilder( $this->db->get_driver() )
			->select()->from( TableName::AUTH_CHALLENGES->table() )
			->where( 'id', '=', hash( 'sha256', $token ) )
			->where( 'purpose', '=', $purpose )
			->where( 'attempts', '<', self::MAX_CHALLENGE_ATTEMPTS )
			->where( 'expires_at', '>', gmdate( 'Y-m-d H:i:s' ) )
			->limit( 1 );
		$row = $this->db->get_row( $sql->build(), $sql->get_bindings() );

		if ( ! $row ) {
			return null;
		}

		try {
			$row['payload'] = json_decode( (string) $row['payload'], true, 32, JSON_THROW_ON_ERROR );
		} catch ( Throwable ) {
			return null;
		}

		return is_array( $row['payload'] ) ? $row : null;
	}

	/** Create an email-code challenge, storing only a keyed digest of its code. */
	public function create_email_code_challenge( int $user_id, string $purpose, array $payload = [] ) : array {
		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$payload['code_hash'] = hash_hmac( 'sha256', $code, $this->challenge_key );
		$token = $this->create_challenge( $user_id, $purpose, $payload );
		return [ 'token' => $token, 'code' => $code ];
	}

	/** Verify an email code and consume its challenge on success. */
	public function verify_email_code( string $token, string $purpose, string $code ) : ?array {
		$challenge = $this->get_challenge( $token, $purpose );
		if ( ! $challenge ) {
			return null;
		}
		$expected = (string) ( $challenge['payload']['code_hash'] ?? '' );
		$actual   = hash_hmac( 'sha256', trim( $code ), $this->challenge_key );
		if ( '' === $expected || ! hash_equals( $expected, $actual ) ) {
			$this->record_challenge_failure( $token );
			return null;
		}
		return $this->consume_challenge( $token ) ? $challenge : null;
	}

	/** Increment a challenge's failed attempts and delete it at the limit. */
	public function record_challenge_failure( string $token ) : void {
		$id = hash( 'sha256', $token );
		$this->db->execute(
			'UPDATE ' . TableName::AUTH_CHALLENGES->table() . ' SET attempts = attempts + 1 WHERE id = ? AND attempts < ?',
			[ $id, self::MAX_CHALLENGE_ATTEMPTS ]
		);
		$this->db->execute(
			'DELETE FROM ' . TableName::AUTH_CHALLENGES->table() . ' WHERE id = ? AND attempts >= ?',
			[ $id, self::MAX_CHALLENGE_ATTEMPTS ]
		);
	}

	/** Atomically consume a challenge; only one concurrent request can win. */
	public function consume_challenge( string $token ) : bool {
		$deleted = $this->db->execute(
			'DELETE FROM ' . TableName::AUTH_CHALLENGES->table() . ' WHERE id = ? AND expires_at > ?',
			[ hash( 'sha256', $token ), gmdate( 'Y-m-d H:i:s' ) ]
		);
		return 1 === $deleted;
	}

	/** Generate an RFC 4648 base32 TOTP seed. */
	public function new_totp_secret() : string {
		return $this->base32_encode( random_bytes( 20 ) );
	}

	/** Encrypt a TOTP seed for storage. */
	public function encrypt_totp_secret( string $secret ) : string {
		$nonce = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $secret, 'smliser-totp-v1', $nonce, $this->totp_key );
		return base64_encode( $nonce . $ciphertext );
	}

	/** Decrypt a stored TOTP seed. */
	public function decrypt_totp_secret( string $encrypted ) : ?string {
		$binary = base64_decode( $encrypted, true );
		$size   = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
		if ( false === $binary || strlen( $binary ) <= $size ) {
			return null;
		}
		$secret = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			substr( $binary, $size ), 'smliser-totp-v1', substr( $binary, 0, $size ), $this->totp_key
		);
		return false === $secret ? null : $secret;
	}

	/** Check a six-digit TOTP with one 30-second step of clock tolerance. */
	public function verify_totp( string $secret, string $code, ?int $now = null ) : bool {
		return null !== $this->totp_counter( $secret, $code, $now );
	}

	/** Return the accepted TOTP time counter, or null when the code is invalid. */
	public function totp_counter( string $secret, string $code, ?int $now = null ) : ?int {
		if ( 1 !== preg_match( '/^[0-9]{6}$/', $code ) ) {
			return null;
		}
		$key = $this->base32_decode( $secret );
		if ( '' === $key ) {
			return null;
		}
		$step = intdiv( $now ?? time(), self::TOTP_STEP );
		for ( $offset = -1; $offset <= 1; $offset++ ) {
			$counter = $step + $offset;
			$binary  = pack( 'N2', intdiv( $counter, 0x100000000 ), $counter % 0x100000000 );
			$hash    = hash_hmac( 'sha1', $binary, $key, true );
			$index   = ord( $hash[ strlen( $hash ) - 1 ] ) & 0x0f;
			$value   = ( ( ord( $hash[ $index ] ) & 0x7f ) << 24 )
				| ( ( ord( $hash[ $index + 1 ] ) & 0xff ) << 16 )
				| ( ( ord( $hash[ $index + 2 ] ) & 0xff ) << 8 )
				| ( ord( $hash[ $index + 3 ] ) & 0xff );
			$expected = str_pad( (string) ( $value % 1_000_000 ), self::TOTP_DIGITS, '0', STR_PAD_LEFT );
			if ( hash_equals( $expected, $code ) ) {
				return $counter;
			}
		}
		return null;
	}

	/** Validate and atomically consume a TOTP time counter to prevent replay. */
	public function consume_totp( int $user_id, string $code ) : bool {
		$row = $this->security_row( $user_id );
		if ( ! $row || empty( $row['totp_enabled_at'] ) || empty( $row['totp_secret'] ) ) {
			return false;
		}
		$secret = $this->decrypt_totp_secret( (string) $row['totp_secret'] );
		$counter = null === $secret ? null : $this->totp_counter( $secret, $code );
		if ( null === $counter ) {
			return false;
		}
		return 1 === $this->db->execute(
			'UPDATE ' . TableName::USER_SECURITY->table() . ' SET totp_last_counter = ?, updated_at = ? WHERE user_id = ? AND totp_enabled_at IS NOT NULL AND (totp_last_counter IS NULL OR totp_last_counter < ?)',
			[ $counter, gmdate( 'Y-m-d H:i:s' ), $user_id, $counter ]
		);
	}

	/** Store the generated recovery-code hashes and return the codes once. */
	public function regenerate_recovery_codes( int $user_id ) : array {
		$codes = [];
		$hashes = [];
		for ( $i = 0; $i < 10; $i++ ) {
			$code     = strtoupper( bin2hex( random_bytes( 5 ) ) );
			$codes[]  = $code;
			$hashes[] = hash_hmac( 'sha256', $code, $this->challenge_key );
		}
		$this->upsert_security( $user_id, [
			'recovery_code_hashes' => json_encode( $hashes, JSON_THROW_ON_ERROR ),
			'updated_at'           => gmdate( 'Y-m-d H:i:s' ),
		] );
		return $codes;
	}

	/** Validate and remove a one-time recovery code. */
	public function consume_recovery_code( int $user_id, string $code ) : bool {
		$row = $this->security_row( $user_id );
		$hashes = json_decode( (string) ( $row['recovery_code_hashes'] ?? '[]' ), true );
		if ( ! is_array( $hashes ) ) {
			return false;
		}
		$needle = hash_hmac( 'sha256', strtoupper( trim( $code ) ), $this->challenge_key );
		foreach ( $hashes as $i => $hash ) {
			if ( ! is_string( $hash ) || ! hash_equals( $hash, $needle ) ) {
				continue;
			}
			unset( $hashes[ $i ] );
			$before = (string) $row['recovery_code_hashes'];
			$after  = json_encode( array_values( $hashes ), JSON_THROW_ON_ERROR );
			$sql    = \smliserQueryBuilder( $this->db->get_driver() )
				->update( TableName::USER_SECURITY->table() )
				->set( [ 'recovery_code_hashes' => $after, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ] )
				->where( 'user_id', '=', $user_id )
				->where( 'recovery_code_hashes', '=', $before );
			return 1 === $this->db->execute( $sql->build(), $sql->get_bindings() );
		}
		return false;
	}

	/** @param array<string,mixed> $data */
	public function upsert_security( int $user_id, array $data ) : void {
		$values = array_merge( [ 'user_id' => $user_id ], $data );
		$sql = \smliserQueryBuilder( $this->db->get_driver() )
			->upsert( TableName::USER_SECURITY->table() )
			->values( $values )->on_conflict( 'user_id' );
		if ( count( $values ) > 1 ) {
			$sql->update_columns( ...array_values( array_diff( array_keys( $values ), [ 'user_id' ] ) ) );
		}
		$this->db->execute( $sql->build(), $sql->get_bindings() );
	}

	/** @return array<int,array<string,mixed>> */
	public function passkeys( int $user_id ) : array {
		$sql = \smliserQueryBuilder( $this->db->get_driver() )
			->select()->from( TableName::USER_PASSKEYS->table() )
			->where( 'user_id', '=', $user_id )->where_null( 'revoked_at' );
		return $this->db->get_results( $sql->build(), $sql->get_bindings() ) ?: [];
	}

	/** Store an attested WebAuthn credential record. */
	public function save_passkey( int $user_id, string $credential_id, string $credential_data, string $label ) : bool {
		return false !== $this->db->insert( TableName::USER_PASSKEYS->table(), [
			'user_id'             => $user_id,
			'credential_id_hash'  => hash( 'sha256', $credential_id ),
			'credential_data'     => $credential_data,
			'label'               => mb_substr( trim( $label ) ?: 'Passkey', 0, 120 ),
			'created_at'          => gmdate( 'Y-m-d H:i:s' ),
		] );
	}

	/** Get one active credential by the hash of its credential ID. */
	public function passkey_by_credential_id( string $credential_id ) : ?array {
		$sql = \smliserQueryBuilder( $this->db->get_driver() )
			->select()->from( TableName::USER_PASSKEYS->table() )
			->where( 'credential_id_hash', '=', hash( 'sha256', $credential_id ) )
			->where_null( 'revoked_at' )->limit( 1 );
		return $this->db->get_row( $sql->build(), $sql->get_bindings() );
	}

	/** Persist a credential's updated authenticator counter after a valid assertion. */
	public function update_passkey( int $id, string $credential_data ) : void {
		$this->db->update( TableName::USER_PASSKEYS->table(), [
			'credential_data' => $credential_data,
			'last_used_at'    => gmdate( 'Y-m-d H:i:s' ),
		], [ 'id' => $id ] );
	}

	/** Revoke one of a user's active passkeys. */
	public function revoke_passkey( int $user_id, int $id ) : bool {
		return 1 === $this->db->update( TableName::USER_PASSKEYS->table(), [
			'revoked_at' => gmdate( 'Y-m-d H:i:s' ),
		], [ 'id' => $id, 'user_id' => $user_id ] );
	}

	/** Remove expired challenges opportunistically. */
	public function prune_challenges() : void {
		$this->db->execute(
			'DELETE FROM ' . TableName::AUTH_CHALLENGES->table() . ' WHERE expires_at <= ?',
			[ gmdate( 'Y-m-d H:i:s' ) ]
		);
	}

	private function base32_encode( string $data ) : string {
		$bits = '';
		foreach ( str_split( $data ) as $char ) {
			$bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}
		$out = '';
		foreach ( str_split( $bits, 5 ) as $chunk ) {
			if ( strlen( $chunk ) < 5 ) {
				$chunk = str_pad( $chunk, 5, '0' );
			}
			$out .= self::BASE32[ bindec( $chunk ) ];
		}
		return $out;
	}

	private function base32_decode( string $data ) : string {
		$data = strtoupper( rtrim( $data, '=' ) );
		if ( '' === $data || 1 !== preg_match( '/^[A-Z2-7]+$/', $data ) ) {
			return '';
		}
		$bits = '';
		foreach ( str_split( $data ) as $char ) {
			$bits .= str_pad( decbin( strpos( self::BASE32, $char ) ), 5, '0', STR_PAD_LEFT );
		}
		$bytes = '';
		foreach ( str_split( $bits, 8 ) as $chunk ) {
			if ( 8 === strlen( $chunk ) ) {
				$bytes .= chr( bindec( $chunk ) );
			}
		}
		return $bytes;
	}
}
