<?php
/**
 * CSRF class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\CSRF
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\CSRF;

use InvalidArgumentException;
use RuntimeException;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

/**
 * Stateless CSRF tokens.
 *
 * A token is an HMAC of something only the user's browser holds, so it is
 * recomputed to verify it and nothing is stored on the server:
 *
 *   - Signed in: the session id from the encrypted session cookie. The token
 *     lives exactly as long as the session; it survives cookie renewals
 *     (the id is kept) and dies on logout or a new sign-in.
 *   - Guest (login, signup, password reset): a random value in a separate
 *     cookie, set the first time a guest token is needed (double-submit).
 *
 * Tokens are not single-use, so any number of tabs and repeated fetch()
 * calls on one page keep working. An optional action narrows a token to one
 * form or endpoint: a token made for "delete-account" verifies only for
 * "delete-account".
 *
 * Usage:
 *
 *   echo $csrf->field();                       // in a form
 *   echo $csrf->meta();                        // in <head>, for fetch()
 *   $csrf->verify_request( $request );         // in middleware or a handler
 *   $csrf->same_origin( $request, $app_url );  // origin check, before the token check
 */
final class CSRF {
	use TokenDeliveryTrait;

	/**
	 * Form field and JSON body key carrying the token.
	 *
	 * @var string
	 */
	public const FIELD = '_token';

	/**
	 * Request header carrying the token.
	 *
	 * @var string
	 */
	public const HEADER = 'X-CSRF-Token';

	/**
	 * Default query parameter carrying a URL token (see url()).
	 *
	 * @var string
	 */
	public const PARAM = '_csrf';

	/**
	 * HMAC key, derived from the application secret and salt for this purpose only.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * @param string         $secret       Application secret.
	 * @param string         $salt         Application salt.
	 * @param SessionManager $sessions     The application session manager.
	 * @param bool           $secure       Whether the guest cookie requires HTTPS.
	 * @param string         $same_site    SameSite policy of the guest cookie.
	 * @param string         $guest_cookie Name of the guest cookie.
	 *
	 * @throws InvalidArgumentException If configuration is invalid.
	 */
	public function __construct(
		#[\SensitiveParameter] string $secret,
		#[\SensitiveParameter] string $salt,
		private SessionManager $sessions,
		private bool $secure = true,
		private string $same_site = 'Lax',
		private string $guest_cookie = '__Host-csrf'
	) {
		if ( ! in_array( $same_site, [ 'Strict', 'Lax' ], true ) ) {
			throw new InvalidArgumentException( 'Invalid SameSite policy for the CSRF cookie. Expected Strict or Lax.' );
		}

		if ( str_starts_with( $guest_cookie, '__Host-' ) && ! $secure ) {
			throw new InvalidArgumentException( '__Host- cookies must use the Secure attribute.' );
		}

		$this->secret = $secret;
		$this->salt   = $salt;

		// Its own HKDF context, so this key reveals nothing about keys derived for other purposes.
		$this->key = $this->derive_key( 'csrf' );
	}

	/*
	|-----------
	| Tokens
	|-----------
	*/

	/**
	 * The token for the current browser.
	 *
	 * For a guest, this sets the guest cookie when it is missing, so call it
	 * before output starts.
	 *
	 * @param string $action Optional action the token is limited to.
	 * @return string
	 *
	 * @throws RuntimeException When a guest cookie is needed but headers are already sent.
	 */
	public function token( string $action = '' ): string {
		return $this->sign( (string) $this->binding( true ), $action );
	}

	/**
	 * Whether a token is valid for the current browser.
	 *
	 * @param string|null $token  Token from the request.
	 * @param string      $action Action the token must have been made for.
	 * @return bool
	 */
	public function verify( ?string $token, string $action = '' ): bool {
		$binding = $this->binding( false );

		if ( null === $binding || null === $token || '' === $token ) {
			return false;
		}

		return hash_equals( $this->sign( $binding, $action ), $token );
	}

	/**
	 * The token sent with a request: the X-CSRF-Token header, else the
	 * _token field of a form or JSON body.
	 *
	 * The query string is not read: URLs end up in logs and Referer headers.
	 *
	 * @param Request $request The request.
	 * @return string|null
	 */
	public function token_from( Request $request ): ?string {
		$header = $request->get_header( self::HEADER );

		if ( '' !== $header ) {
			return $header;
		}

		$token = $request->post( self::FIELD );

		if ( ! is_string( $token ) || '' === $token ) {
			$token = $request->json( self::FIELD );
		}

		return is_string( $token ) && '' !== $token ? $token : null;
	}

	/**
	 * Whether the request carries a valid token.
	 *
	 * @param Request $request The request.
	 * @param string  $action  Action the token must have been made for.
	 * @return bool
	 */
	public function verify_request( Request $request, string $action = '' ): bool {
		return $this->verify( $this->token_from( $request ), $action );
	}

	/*
	|-------------
	| URL tokens
	|-------------
	*/

	/**
	 * Add a token for an action to a URL, for links that perform that action.
	 *
	 * URLs end up in server logs, browser history and Referer headers, so a
	 * URL token is always limited to one action: a leaked logout link can
	 * sign the user out and do nothing else. Without an explicit action the
	 * URL's path is the action, so the token is valid for that path only.
	 * The general token that forms share is never put in a URL.
	 *
	 * @param URL|string $url    The link.
	 * @param string     $action Action the link performs, e.g. "logout"; "" binds the token to the URL's path.
	 * @param string     $param  Query parameter that carries the token.
	 * @return URL A new URL with the token added; the given URL is unchanged.
	 *
	 * @throws RuntimeException When a guest cookie is needed but headers are already sent.
	 */
	public function url( URL|string $url, string $action = '', string $param = self::PARAM ): URL {
		$url = is_string( $url ) ? URL::from( $url ) : $url;

		return $url->add_query_param( $param, $this->token( $this->url_action( $action, (string) $url->get_path() ) ) );
	}

	/**
	 * Whether the request URL carries a valid token for an action.
	 *
	 * The counterpart of url(), with the same action and parameter. Used by
	 * URLCSRFMiddleware; CSRFMiddleware does not check GET requests.
	 *
	 * @param Request $request The request.
	 * @param string  $action  Action the token must have been made for; "" means the request path.
	 * @param string  $param   Query parameter that carries the token.
	 * @return bool
	 */
	public function verify_url( Request $request, string $action = '', string $param = self::PARAM ): bool {
		$token = $request->query( $param );

		return is_string( $token ) && $this->verify( $token, $this->url_action( $action, $request->path() ) );
	}

	/**
	 * The action a URL token is limited to.
	 *
	 * Path-bound actions get their own prefix, so they never equal a named action.
	 *
	 * @param string $action Named action, or "".
	 * @param string $path   URL path, used when no action is named.
	 * @return string
	 */
	private function url_action( string $action, string $path ): string {
		return '' !== $action ? $action : 'path:' . '/' . trim( $path, '/' );
	}

	/*
	|----------------
	| Origin check
	|----------------
	*/

	/**
	 * Whether the browser says the request comes from the application itself.
	 *
	 * Uses Sec-Fetch-Site when the browser sends it, else Origin, else the
	 * Referer. A request with none of them (old clients, some privacy
	 * settings) passes; the token check still applies.
	 *
	 * @param Request $request The request.
	 * @param string  $app_url The canonical application URL (SMLISER_APP_URL).
	 * @return bool
	 */
	public function same_origin( Request $request, string $app_url ): bool {
		$site = strtolower( $request->get_header( 'Sec-Fetch-Site' ) );

		if ( '' !== $site ) {
			// "same-site" includes sibling subdomains, which are not the application.
			return 'same-origin' === $site || 'none' === $site;
		}

		$expected = $this->origin_of( $app_url );

		foreach ( [ 'Origin', 'Referer' ] as $header ) {
			$value = $request->get_header( $header );

			if ( '' !== $value ) {
				return null !== $expected && $this->origin_of( $value ) === $expected;
			}
		}

		return true;
	}

	/*
	|----------
	| Output
	|----------
	*/

	/**
	 * A hidden form field with the token.
	 *
	 * @param string $action Optional action the token is limited to.
	 * @return string
	 */
	public function field( string $action = '' ): string {
		return sprintf(
			'<input type="hidden" name="%s" value="%s">',
			self::FIELD,
			htmlspecialchars( $this->token( $action ), ENT_QUOTES, 'UTF-8' )
		);
	}

	/**
	 * A meta tag with the token, for scripts that send it in the X-CSRF-Token header.
	 *
	 * Scripts read it with document.querySelector( 'meta[name="csrf-token"]' ).content.
	 *
	 * @param string $action Optional action the token is limited to.
	 * @return string
	 */
	public function meta( string $action = '' ): string {
		return sprintf(
			'<meta name="csrf-token" content="%s">',
			htmlspecialchars( $this->token( $action ), ENT_QUOTES, 'UTF-8' )
		);
	}

	/*
	|------------
	| Internals
	|------------
	*/

	/**
	 * What the token is bound to: the session id, else the guest cookie value.
	 *
	 * @param bool $create Whether to set the guest cookie when there is none.
	 * @return string|null Null when there is nothing to bind to and $create is false.
	 *
	 * @throws RuntimeException When the guest cookie must be set but headers are already sent.
	 */
	private function binding( bool $create ): ?string {
		$session = $this->sessions->resolve();

		if ( null !== $session ) {
			return 'session:' . $session->id;
		}

		$value = $_COOKIE[ $this->guest_cookie ] ?? null;

		if ( is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ) {
			return 'guest:' . $value;
		}

		if ( ! $create ) {
			return null;
		}

		if ( headers_sent() ) {
			throw new RuntimeException( 'Cannot set the CSRF cookie because headers have already been sent.' );
		}

		$value = bin2hex( random_bytes( 32 ) );

		setcookie(
			$this->guest_cookie,
			$value,
			[
				'expires'  => 0,
				'path'     => '/',
				'secure'   => $this->secure,
				'httponly' => true,
				'samesite' => $this->same_site,
			]
		);

		$_COOKIE[ $this->guest_cookie ] = $value;

		return 'guest:' . $value;
	}

	/**
	 * The token for a binding and action.
	 *
	 * @param string $binding Value from binding().
	 * @param string $action  Action, or "".
	 * @return string Hex HMAC-SHA256.
	 */
	private function sign( string $binding, string $action ): string {
		return static::hmac_hash( $binding . "\n" . $action, $this->key );
	}

	/**
	 * Origin of a URL for comparison: URL::get_origin(), lower-cased, with the
	 * scheme's default port dropped (browsers never send it).
	 *
	 * @param string $url URL or origin.
	 * @return string|null Null when the URL has no host, including the literal "null" origin.
	 */
	private function origin_of( string $url ): ?string {
		$url = URL::from( $url );

		if ( null === $url->get_host() || ! $url->has_scheme() ) {
			return null;
		}

		$default = [ 'http' => 80, 'https' => 443 ][ strtolower( (string) $url->get_scheme() ) ] ?? null;

		if ( null !== $default && $default === $url->get_port() ) {
			$url = $url->remove_port();
		}

		return strtolower( (string) $url->get_origin() );
	}
}