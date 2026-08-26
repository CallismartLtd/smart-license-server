<?php
/**
 * Stateless Cookie Session Manager.
 *
 * Provides cookie-based session management without PHP's native session
 * subsystem or server-side session storage.
 *
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication\Session;

use InvalidArgumentException;
use RuntimeException;
use SodiumException;

final class SessionManager {

	/**
	 * Session cookie name.
	 *
	 * @var string
	 */
	private string $cookie_name;

	/**
	 * Cryptographic key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Session lifetime in seconds.
	 *
	 * @var int
	 */
	private int $lifetime;

	/**
	 * Whether the cookie should only be transmitted over HTTPS.
	 *
	 * @var bool
	 */
	private bool $secure;

	/**
	 * Whether JavaScript should be prevented from accessing the cookie.
	 *
	 * @var bool
	 */
	private bool $http_only;

	/**
	 * SameSite cookie policy.
	 *
	 * @var string
	 */
	private string $same_site;

	/**
	 * Cookie path.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Create the session manager.
	 *
	 * @param string $secret Application secret used to derive the encryption key.
	 * @param int $lifetime Session lifetime in seconds.
	 * @param string $cookie_name Session cookie name.
	 * @param bool $secure Whether the cookie must use HTTPS.
	 * @param bool $http_only Whether the cookie is inaccessible to JavaScript.
	 * @param string $same_site SameSite policy.
	 * @param string $path Cookie path.
	 *
	 * @throws InvalidArgumentException If configuration is invalid.
	 */
	public function __construct(
		string $secret,
		int $lifetime		= 7200,
		string $cookie_name = '__Host-sid',
		bool $secure		= true,
		bool $http_only		= true,
		string $same_site	= 'Lax',
		string $path		= '/'
	) {
		if ( '' === trim( $secret ) ) {
			throw new InvalidArgumentException( 'Session secret cannot be empty.' );
		}

		if ( $lifetime < 1 ) {
			throw new InvalidArgumentException( 'Session lifetime must be greater than zero.' );
		}

		if ( ! in_array( $same_site, [ 'Strict', 'Lax', 'None' ], true ) ) {
			throw new InvalidArgumentException(
				'Invalid SameSite policy. Expected Strict, Lax, or None.'
			);
		}

		if ( 'None' === $same_site && ! $secure ) {
			throw new InvalidArgumentException(
				'SameSite=None cookies must use the Secure attribute.'
			);
		}

		if ( str_starts_with( $cookie_name, '__Host-' ) ) {
			if ( '/' !== $path ) {
				throw new InvalidArgumentException(
					'__Host- cookies must use the root path.'
				);
			}

			if ( ! $secure ) {
				throw new InvalidArgumentException(
					'__Host- cookies must use the Secure attribute.'
				);
			}
		}

		$this->cookie_name = $cookie_name;
		$this->key         = $this->derive_key( $secret );
		$this->lifetime    = $lifetime;
		$this->secure      = $secure;
		$this->http_only   = $http_only;
		$this->same_site   = $same_site;
		$this->path        = $path;
	}

	/**
	 * Create a new session.
	 *
	 * The returned token is also written to the response cookie.
	 *
	 * @param string|int $principal_id Principal represented by the session.
	 * @param array<string,mixed> $claims Additional session claims.
	 *
	 * @return Session
	 *
	 * @throws RuntimeException If the token cannot be generated.
	 */
	public function create(
		string|int $principal_id,
		array $claims = []
	): Session {
		$now = time();

		$session = new Session(
			id: $this->generate_session_id(),
			principal_id: $principal_id,
			issued_at: $now,
			expires_at: $now + $this->lifetime,
			claims: $claims
		);

		$token = $this->encode( $session );

		$this->set_cookie( $token, $session->expires_at );

		return $session;
	}

	/**
	 * Resolve the current browser session.
	 *
	 * Returns null when the cookie does not exist, is malformed,
	 * fails cryptographic verification, or has expired.
	 *
	 * @return Session|null
	 */
	public function resolve(): ?Session {
		if ( ! isset( $_COOKIE[ $this->cookie_name ] ) ) {
			return null;
		}

		$token = $_COOKIE[ $this->cookie_name ];

		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}

		try {
			return $this->decode( $token );
		} catch ( \Throwable ) {
			return null;
		}
	}

	/**
	 * Resolve and require a valid session.
	 *
	 * @return Session
	 *
	 * @throws RuntimeException When no valid session exists.
	 */
	public function require(): Session {
		$session = $this->resolve();

		if ( null === $session ) {
			throw new RuntimeException( 'No valid session exists.' );
		}

		return $session;
	}

	/**
	 * Invalidate the current browser session.
	 *
	 * Since this implementation is completely stateless, invalidation means
	 * removing the browser's credential. The server does not retain a
	 * revocation record.
	 *
	 * @return void
	 */
	public function invalidate(): void {
		if ( ! headers_sent() ) {
			setcookie(
				$this->cookie_name,
				'',
				[
					'expires'  => time() - 3600,
					'path'     => $this->path,
					'secure'   => $this->secure,
					'httponly' => $this->http_only,
					'samesite' => $this->same_site,
				]
			);
		}

		unset( $_COOKIE[ $this->cookie_name ] );
	}

	/**
	 * Determine whether a valid session currently exists.
	 *
	 * @return bool
	 */
	public function authenticated(): bool {
		return null !== $this->resolve();
	}

	/**
	 * Get the configured cookie name.
	 *
	 * @return string
	 */
	public function cookie_name(): string {
		return $this->cookie_name;
	}

	/**
	 * Encode a session into an opaque authenticated token.
	 *
	 * @param Session $session Session to encode.
	 *
	 * @return string
	 *
	 * @throws RuntimeException If encryption fails.
	 */
	private function encode( Session $session ): string {
		try {
			$payload = json_encode(
				[
					'v'  => 1,
					'sid' => $session->id,
					'sub' => $session->principal_id,
					'iat' => $session->issued_at,
					'exp' => $session->expires_at,
					'c'  => $session->claims,
				],
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
			);

			$nonce = random_bytes(
				SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
			);

			$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
				$payload,
				'',
				$nonce,
				$this->key
			);

			return $this->base64url_encode( $nonce . $ciphertext );
		} catch ( SodiumException | \JsonException | \Throwable $e ) {
			throw new RuntimeException(
				'Unable to create session token.',
				0,
				$e
			);
		}
	}

	/**
	 * Decode and validate a session token.
	 *
	 * @param string $token Session token.
	 *
	 * @return Session
	 *
	 * @throws RuntimeException If the token is invalid.
	 */
	private function decode( string $token ): Session {
		$binary = $this->base64url_decode( $token );

		$nonce_length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
		$tag_length   = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

		if ( strlen( $binary ) <= $nonce_length + $tag_length ) {
			throw new RuntimeException( 'Invalid session token.' );
		}

		$nonce      = substr( $binary, 0, $nonce_length );
		$ciphertext = substr( $binary, $nonce_length );

		try {
			$payload = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
				$ciphertext,
				'',
				$nonce,
				$this->key
			);

			if ( false === $payload ) {
				throw new RuntimeException( 'Invalid session authentication.' );
			}

			$data = json_decode(
				$payload,
				true,
				512,
				JSON_THROW_ON_ERROR
			);
		} catch ( SodiumException | \JsonException $e ) {
			throw new RuntimeException(
				'Invalid session token.',
				0,
				$e
			);
		}

		$this->validate_payload( $data );

		return new Session(
			id: $data['sid'],
			principal_id: $data['sub'],
			issued_at: $data['iat'],
			expires_at: $data['exp'],
			claims: $data['c']
		);
	}

	/**
	 * Validate decoded session payload.
	 *
	 * @param mixed $data Decoded payload.
	 *
	 * @return void
	 */
	private function validate_payload( mixed $data ): void {
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Invalid session payload.' );
		}

		if ( 1 !== ( $data['v'] ?? null ) ) {
			throw new RuntimeException( 'Unsupported session version.' );
		}

		if (
			! isset( $data['sid'] ) ||
			! is_string( $data['sid'] ) ||
			'' === $data['sid']
		) {
			throw new RuntimeException( 'Invalid session identifier.' );
		}

		if (
			! isset( $data['sub'] ) ||
			(
				! is_string( $data['sub'] ) &&
				! is_int( $data['sub'] )
			)
		) {
			throw new RuntimeException( 'Invalid session principal.' );
		}

		if (
			! isset( $data['iat'], $data['exp'] ) ||
			! is_int( $data['iat'] ) ||
			! is_int( $data['exp'] )
		) {
			throw new RuntimeException( 'Invalid session timestamps.' );
		}

		if ( $data['exp'] <= $data['iat'] ) {
			throw new RuntimeException( 'Invalid session lifetime.' );
		}

		if ( $data['exp'] <= time() ) {
			throw new RuntimeException( 'Session has expired.' );
		}

		if (
			isset( $data['c'] ) &&
			! is_array( $data['c'] )
		) {
			throw new RuntimeException( 'Invalid session claims.' );
		}
	}

	/**
	 * Write the session cookie.
	 *
	 * @param string $token Session token.
	 * @param int $expires Expiration timestamp.
	 *
	 * @return void
	 */
	private function set_cookie(
		string $token,
		int $expires
	): void {
		if ( headers_sent() ) {
			throw new RuntimeException(
				'Cannot create session cookie because headers have already been sent.'
			);
		}

		setcookie(
			$this->cookie_name,
			$token,
			[
				'expires'  => $expires,
				'path'     => $this->path,
				'secure'   => $this->secure,
				'httponly' => $this->http_only,
				'samesite' => $this->same_site,
			]
		);

		$_COOKIE[ $this->cookie_name ] = $token;
	}

	/**
	 * Derive a fixed-length encryption key from the application secret.
	 *
	 * @param string $secret Application secret.
	 *
	 * @return string
	 */
	private function derive_key( string $secret ): string {
		return hash(
			'sha256',
			'session:' . $secret,
			true
		);
	}

	/**
	 * Generate a cryptographically random session identifier.
	 *
	 * @return string
	 */
	private function generate_session_id(): string {
		return $this->base64url_encode(
			random_bytes( 32 )
		);
	}

	/**
	 * Encode binary data using URL-safe Base64.
	 *
	 * @param string $value Binary data.
	 *
	 * @return string
	 */
	private function base64url_encode( string $value ): string {
		return rtrim(
			strtr(
				base64_encode( $value ),
				'+/',
				'-_'
			),
			'='
		);
	}

	/**
	 * Decode URL-safe Base64.
	 *
	 * @param string $value Encoded value.
	 *
	 * @return string
	 *
	 * @throws RuntimeException If decoding fails.
	 */
	private function base64url_decode( string $value ): string {
		$value = strtr( $value, '-_', '+/' );

		$padding = strlen( $value ) % 4;

		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		$decoded = base64_decode( $value, true );

		if ( false === $decoded ) {
			throw new RuntimeException( 'Invalid session encoding.' );
		}

		return $decoded;
	}
}