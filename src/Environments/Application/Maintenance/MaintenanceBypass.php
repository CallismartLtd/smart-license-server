<?php
/**
 * Maintenance bypass class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Maintenance
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Maintenance;

use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;

/**
 * Lets the operator use the site while visitors get the maintenance page.
 *
 * Philosophy:
 *
 *  - A secret, not a role. Maintenance closes the web before sessions or
 *    users are loaded (DowntimeBootstrapper), so the bypass cannot ask who
 *    you are. It asks for a token: whoever holds the bypass link gets in.
 *  - The flag keeps a hash, the browser keeps the token. Opening the link
 *    (any URL with ?maintenance-bypass=<token>) sets an HttpOnly cookie and
 *    redirects to the same URL without the token, so the token does not
 *    stay in the address bar, the history, a sign-in redirect or the logs.
 *    Later requests carry the cookie. A leaked flag file reveals no token.
 *  - One maintenance, one token. Switching maintenance on again, or making
 *    a new link, changes the token, and every earlier link and cookie stops
 *    working. When maintenance ends, the cookie means nothing.
 *  - Operator maintenance only. An update or an installation is never
 *    bypassed: their files or schema may be half-changed.
 *
 * The bypass gets the normal site, sign-in included; it does not sign
 * anyone in.
 */
final class MaintenanceBypass {

	/**
	 * Cookie holding the token.
	 */
	public const COOKIE = 'smliser_maintenance_bypass';

	/**
	 * Query parameter of the bypass link.
	 */
	public const QUERY = 'maintenance-bypass';

	/**
	 * Seconds the cookie lasts; opening the link again renews it.
	 */
	public const COOKIE_LIFETIME = 43200;

	/**
	 * Constructor.
	 *
	 * @param MaintenanceFlag $flag The maintenance flag holding the token's hash.
	 */
	public function __construct( private MaintenanceFlag $flag ) {}

	/**
	 * A new random token.
	 *
	 * @return string 64 hexadecimal characters.
	 */
	public static function new_token() : string {
		return \bin2hex( \random_bytes( 32 ) );
	}

	/**
	 * The hash the flag stores for a token.
	 *
	 * @param string $token
	 * @return string
	 */
	public static function hash( string $token ) : string {
		return \hash( 'sha256', $token );
	}

	/**
	 * The valid token of a bypass link in this request's query, if any.
	 *
	 * The caller answers with the cookie and a redirect to the URL without
	 * the token (see cookie()), so the link only has to be opened once.
	 *
	 * @param array<string, mixed> $query The request's query parameters ($_GET).
	 * @return string|null
	 */
	public function link_token( array $query ) : ?string {
		$token = $query[ self::QUERY ] ?? null;

		return \is_string( $token ) && $this->accepts( $token ) ? $token : null;
	}

	/**
	 * Whether a web request's cookie lets it use the site during operator maintenance.
	 *
	 * @param array<string, mixed> $cookies The request's cookies ($_COOKIE).
	 * @return bool
	 */
	public function granted( array $cookies ) : bool {
		$token = $cookies[ self::COOKIE ] ?? null;

		return \is_string( $token ) && $this->accepts( $token );
	}

	/**
	 * Whether a token matches the current maintenance.
	 *
	 * @param string $token
	 * @return bool
	 */
	public function accepts( string $token ) : bool {
		$hash = $this->flag->bypass_hash();

		return null !== $hash && '' !== $token && \hash_equals( $hash, self::hash( $token ) );
	}

	/**
	 * A Set-Cookie header value that stores a token.
	 *
	 * @param string $token
	 * @param bool   $secure Mark the cookie Secure (HTTPS only).
	 * @return string
	 */
	public static function cookie( string $token, bool $secure ) : string {
		return \sprintf(
			'%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax%s',
			self::COOKIE,
			\rawurlencode( $token ),
			self::COOKIE_LIFETIME,
			$secure ? '; Secure' : ''
		);
	}

	/**
	 * A Set-Cookie header value that removes the cookie.
	 *
	 * @param bool $secure
	 * @return string
	 */
	public static function expired_cookie( bool $secure ) : string {
		return \sprintf( '%s=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax%s', self::COOKIE, $secure ? '; Secure' : '' );
	}
}
