<?php
/**
 * Stateless Cookie Session Manager.
 *
 * Provides cookie-based session management without PHP's native session
 * subsystem or server-side session storage.
 *
 * Active sessions renew themselves: once a session is past half its
 * lifetime, the next request re-issues the cookie with a fresh expiry, keeping
 * the session id and the sign-in time. An idle session still expires after
 * its lifetime, and no session outlives its maximum lifetime counted from
 * sign-in. Revocation is delegated to an optional SessionRevocationCheck.
 *
 * A "remember me" (persistent) session is different: its cookie outlives the
 * browser, and it lasts the remembered lifetime from sign-in without sliding.
 * Any other session's cookie ends with the browser, as well as at its expiry.
 *
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication\Session;

use InvalidArgumentException;
use RuntimeException;
use SmartLicenseServer\Utils\TokenDeliveryTrait;
use SodiumException;

final class SessionManager {

	use TokenDeliveryTrait;

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
	 * Longest a session may last from sign-in, renewals included, in seconds.
	 *
	 * @var int
	 */
	private int $max_lifetime;

	/**
	 * Optional check for sessions revoked by the application.
	 *
	 * @var SessionRevocationCheck|null
	 */
	private ?SessionRevocationCheck $revocation_check;

	/**
	 * How long a "remember me" session lasts from sign-in, in seconds.
	 *
	 * @var int
	 */
	private int $remember_lifetime;

	/**
	 * The session resolved for this request.
	 *
	 * @var Session|null
	 */
	private ?Session $current = null;

	/**
	 * The cookie value $current was resolved from.
	 *
	 * @var string|null
	 */
	private ?string $current_token = null;

	/**
	 * Create the session manager.
	 *
	 * @param string $secret Application secret the encryption key is derived from.
	 * @param string $salt Application salt used in key derivation.
	 * @param int $lifetime Session lifetime in seconds.
	 * @param string $cookie_name Session cookie name.
	 * @param bool $secure Whether the cookie must use HTTPS.
	 * @param bool $http_only Whether the cookie is inaccessible to JavaScript.
	 * @param string $same_site SameSite policy.
	 * @param string $path Cookie path.
	 * @param int $max_lifetime Longest a session may last from sign-in, renewals included.
	 * @param SessionRevocationCheck|null $revocation_check Optional check for revoked sessions.
	 * @param int $remember_lifetime How long a "remember me" session lasts from sign-in.
	 *
	 * @throws InvalidArgumentException If configuration is invalid.
	 */
	public function __construct(
		#[\SensitiveParameter] string $secret,
		#[\SensitiveParameter] string $salt,
		int $lifetime		= 7200,
		string $cookie_name = '__Host-sid',
		bool $secure		= true,
		bool $http_only		= true,
		string $same_site	= 'Lax',
		string $path		= '/',
		int $max_lifetime	= 604800,
		?SessionRevocationCheck $revocation_check = null,
		int $remember_lifetime	= 2592000
	) {
		if ( '' === trim( $secret ) ) {
			throw new InvalidArgumentException( 'Session secret cannot be empty.' );
		}

		if ( $lifetime < 1 ) {
			throw new InvalidArgumentException( 'Session lifetime must be greater than zero.' );
		}

		if ( $max_lifetime < $lifetime ) {
			throw new InvalidArgumentException( 'Maximum session lifetime cannot be shorter than the session lifetime.' );
		}

		if ( $remember_lifetime < $lifetime ) {
			throw new InvalidArgumentException( 'The remembered session lifetime cannot be shorter than the session lifetime.' );
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
		$this->secret      = $secret;
		$this->salt        = $salt;
		$this->key         = $this->derive_key( 'session' );
		$this->lifetime    = $lifetime;
		$this->secure      = $secure;
		$this->http_only   = $http_only;
		$this->same_site   = $same_site;
		$this->path        = $path;
		$this->max_lifetime     = $max_lifetime;
		$this->revocation_check  = $revocation_check;
		$this->remember_lifetime = $remember_lifetime;
	}

	/**
	 * Create a new session.
	 *
	 * The returned token is also written to the response cookie.
	 *
	 * @param string|int $principal_id Principal represented by the session.
	 * @param array<string,mixed> $claims Additional session claims.
	 * @param bool $persistent Whether the user chose "remember me".
	 *
	 * @return Session
	 *
	 * @throws RuntimeException If the token cannot be generated.
	 */
	public function create(
		string|int $principal_id,
		array $claims = [],
		bool $persistent = false
	): Session {
		$now = time();

		$session = new Session(
			id: $this->generate_session_id(),
			principal_id: $principal_id,
			issued_at: $now,
			expires_at: $now + ( $persistent ? $this->remember_lifetime : $this->lifetime ),
			claims: $claims,
			authenticated_at: $now,
			persistent: $persistent
		);

		$this->issue( $session );

		return $session;
	}

	/**
	 * Resolve the current browser session.
	 *
	 * Returns null when the cookie does not exist, is malformed, fails
	 * cryptographic verification, has expired, or was revoked. A revoked
	 * session's cookie is removed.
	 *
	 * A session past half its lifetime is renewed here (see renew()). The
	 * result is kept for the rest of the request, so calling this repeatedly
	 * decrypts the cookie and asks the revocation check only once.
	 *
	 * @return Session|null
	 */
	public function resolve(): ?Session {
		$token = $_COOKIE[ $this->cookie_name ] ?? null;

		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}

		if ( $token === $this->current_token ) {
			return $this->current;
		}

		try {
			$session = $this->decode( $token );
		} catch ( \Throwable ) {
			return null;
		}

		if ( null !== $this->revocation_check && $this->revocation_check->revoked( $session ) ) {
			$this->invalidate();
			return null;
		}

		$session = $this->renew( $session );

		$this->current       = $session;
		$this->current_token = $_COOKIE[ $this->cookie_name ] ?? null;

		return $session;
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

		$this->current       = null;
		$this->current_token = null;
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
	 * Re-issue a session that is past half its lifetime.
	 *
	 * The new cookie keeps the session id (so CSRF tokens bound to it stay
	 * valid) and the sign-in time, and gets a fresh expiry, capped at the
	 * maximum lifetime counted from sign-in. Nothing happens once headers
	 * are sent, or when the cap leaves nothing to extend.
	 *
	 * Persistent sessions are issued with their full remembered lifetime,
	 * so the cap always leaves them nothing to extend.
	 *
	 * @param Session $session Current session.
	 *
	 * @return Session The renewed session, or the given one.
	 */
	private function renew( Session $session ): Session {
		$now = time();

		if ( $session->persistent || $session->expires_at - $now > intdiv( $this->lifetime, 2 ) || headers_sent() ) {
			return $session;
		}

		$expires_at = min( $now + $this->lifetime, $session->authenticated_at + $this->max_lifetime );

		if ( $expires_at <= $session->expires_at ) {
			return $session;
		}

		$renewed = new Session(
			id: $session->id,
			principal_id: $session->principal_id,
			issued_at: $now,
			expires_at: $expires_at,
			claims: $session->claims,
			authenticated_at: $session->authenticated_at,
			persistent: $session->persistent
		);

		try {
			$this->issue( $renewed );
		} catch ( \Throwable ) {
			return $session;
		}

		return $renewed;
	}

	/**
	 * Encode a session, write its cookie and remember it for this request.
	 *
	 * @param Session $session Session to issue.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the token cannot be created or headers are already sent.
	 */
	private function issue( Session $session ): void {
		$token = $this->encode( $session );

		// 0 makes a browser-session cookie; the token's own expiry still applies.
		$this->set_cookie( $token, $session->persistent ? $session->expires_at : 0 );

		$this->current       = $session;
		$this->current_token = $token;
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
					'v'    => 2,
					'sid'  => $session->id,
					'sub'  => $session->principal_id,
					'iat'  => $session->issued_at,
					'exp'  => $session->expires_at,
					'auth' => $session->authenticated_at,
					'rem'  => $session->persistent,
					'c'    => $session->claims,
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

			return self::base64url_encode( $nonce . $ciphertext );
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
		$binary = self::base64url_decode( $token );

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
			claims: $data['c'] ?? [],
			authenticated_at: $data['auth'] ?? $data['iat'],
			persistent: true === ( $data['rem'] ?? false )
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

		// Version 1 cookies (issued before renewal existed) carry no sign-in time; it is their iat.
		if ( ! in_array( $data['v'] ?? null, [ 1, 2 ], true ) ) {
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

		if (
			2 === $data['v'] &&
			( ! isset( $data['auth'] ) || ! is_int( $data['auth'] ) || $data['auth'] > $data['iat'] )
		) {
			throw new RuntimeException( 'Invalid session sign-in time.' );
		}

		if ( isset( $data['rem'] ) && ! is_bool( $data['rem'] ) ) {
			throw new RuntimeException( 'Invalid session persistence flag.' );
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
	 * @param int $expires Expiration timestamp; 0 for a browser-session cookie.
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
	 * Generate a cryptographically random session identifier.
	 *
	 * @return string
	 */
	private function generate_session_id(): string {
		return self::base64url_encode(
			random_bytes( 32 )
		);
	}
}