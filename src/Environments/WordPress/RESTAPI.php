<?php
/**
 * WordPress REST API configuration class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\WordPress
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environments\Application\Middlewares\CallableResolver;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\RESTAPI\RESTIndex;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\RESTAPI\RESTVersionInterface;
use SmartLicenseServer\Routing\RoutePattern;
use SmartLicenseServer\Security\Authentication\AuthenticationResult;
use SmartLicenseServer\Security\Authentication\IdentityProviders\IdentityProviderInterface;
use SmartLicenseServer\Security\Authentication\ServiceAccountAuthenticator;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\CSRF\CSRF;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function add_action, add_filter;

/**
 * REST API provision for WordPress.
 *
 * Serves the same API as the standalone provider, under the same paths
 * relative to the REST root (/wp-json/smliser/v1/...), with the same rules:
 *
 *  - HTTPS is required (see enforce_https()).
 *  - Authentication follows the REST policy (see authenticate()): a signed-in
 *    browser with a valid CSRF header, else a service account's bearer
 *    token, else a guest. Route guards only authorize.
 *  - GET /smliser, GET /smliser/<ns> (?category=) and OPTIONS on any route
 *    return the RESTIndex descriptions, identical to the standalone ones.
 *  - Responses are JSON.
 */
class RESTAPI implements RESTProviderInterface {
	use SanitizeAwareTrait;

	/**
	 * Our request object for the current request.
	 *
	 * @var Request|null
	 */
	private ?Request $request = null;

	/**
	 * The current REST route, e.g. "/smliser/v1/repository".
	 *
	 * @var string|null
	 */
	private ?string $current_route = null;

	/**
	 * Describes the API, its versions and routes.
	 *
	 * @var RESTIndex
	 */
	private RESTIndex $index;

	/**
	 * Class constructor.
	 *
	 * @param RESTVersionInterface[]    $versions Available REST API versions.
	 * @param Guard                     $guard    Holds the principal for the request.
	 * @param IdentityProviderInterface $identity Resolves the signed-in WordPress user's principal.
	 * @param CSRF                      $csrf     Verifies the CSRF header of browser requests.
	 * @param CallableResolver          $resolver Resolves route handlers and guards to callables.
	 */
	private function __construct(
		private array $versions,
		protected Guard $guard,
		protected IdentityProviderInterface $identity,
		protected CSRF $csrf,
		protected CallableResolver $resolver
	) {
		$this->index = new RESTIndex( $versions, static fn( string $path ) : string => rest_url( $path ) );

		add_filter( 'rest_exposed_cors_headers', array( $this, 'set_current_route' ), 10, 2 );
		add_filter( 'rest_authentication_errors', array( $this, 'authentication_errors' ), 5 );
		add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https' ), 1, 3 );
		add_filter( 'rest_pre_dispatch', array( $this, 'describe' ), 5, 3 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'rest_request_before_callbacks' ), -1, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'filter_response' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register' ), 30 );
	}

	/**
	 * Create the provider and hook it into WordPress.
	 *
	 * @param Guard                     $guard       Holds the principal for the request.
	 * @param IdentityProviderInterface $identity    Resolves the signed-in WordPress user's principal.
	 * @param CSRF                      $csrf        Verifies the CSRF header of browser requests.
	 * @param CallableResolver          $resolver    Resolves route handlers and guards to callables.
	 * @param RESTVersionInterface      ...$versions Available REST API versions.
	 * @return static
	 */
	public static function init( Guard $guard, IdentityProviderInterface $identity, CSRF $csrf, CallableResolver $resolver, RESTVersionInterface ...$versions ) : static {
		return new static( $versions, $guard, $identity, $csrf, $resolver );
	}

	/*
	|---------------
	| Registration
	|---------------
	*/

	/**
	 * Register every version's routes with WordPress.
	 *
	 * @return void
	 */
	public function register() : void {
		foreach ( $this->versions as $version ) {
			$namespace = $this->rest_namespace( $version->namespace() );

			foreach ( $version::get_routes()['routes'] as $route_config ) {
				$handler = $route_config['handler'];
				$guard   = $route_config['guard'] ?? null;

				register_rest_route(
					$namespace,
					'/' . RoutePattern::compile( $route_config['route'] )->namedRegex() . '/?',
					array(
						'methods'             => (array) $route_config['methods'],
						'callback'            => fn( WP_REST_Request $wp_request ) => $this->main_dispatcher( $wp_request, $handler ),
						'permission_callback' => null === $guard
							? '__return_true'
							: fn( WP_REST_Request $wp_request ) => $this->permission_dispatcher( $wp_request, $guard ),
						'args'                => $this->prepare_rest_args( $route_config['args'] ?? array() ),
					)
				);
			}
		}
	}

	/**
	 * Run a route guard with our request object.
	 *
	 * @param WP_REST_Request $wp_request The WordPress REST request.
	 * @param mixed           $guard      Guard definition, resolved by the CallableResolver.
	 * @return WP_Error|bool
	 */
	public function permission_dispatcher( WP_REST_Request $wp_request, mixed $guard ) : WP_Error|bool {
		/** @var RequestException|bool $result */
		$result = $this->resolver->resolveMiddleware( $guard )( $this->convert_wp_request( $wp_request ) );

		if ( is_smliser_error( $result ) ) {
			return method_exists( $result, 'to_wp_error' )
				? $result->to_wp_error()
				: new WP_Error( $result->get_error_code(), $result->get_error_message() );
		}

		// A guard allows with true and nothing else; a WP_Error passes through.
		return $result instanceof WP_Error ? $result : true === $result;
	}

	/**
	 * Run a route handler with our request object.
	 *
	 * @param WP_REST_Request $wp_request The WordPress REST request.
	 * @param mixed           $handler    Handler definition, resolved by the CallableResolver.
	 * @return mixed
	 */
	public function main_dispatcher( WP_REST_Request $wp_request, mixed $handler ) : mixed {
		return $this->resolver->resolve( $handler )( $this->convert_wp_request( $wp_request ) );
	}

	/**
	 * Our request object, carrying the WordPress request's parameters,
	 * headers and route parameters.
	 *
	 * @param WP_REST_Request $wp_request The WordPress REST request.
	 * @return Request
	 */
	public function convert_wp_request( WP_REST_Request $wp_request ) : Request {
		return $this->get_request()
			->merge( $wp_request->get_params() )
			->set_headers( $wp_request->get_headers() )
			->set_route_param( $wp_request->get_url_params() );
	}

	/**
	 * Our request object for the current request.
	 *
	 * @return Request
	 */
	public function get_request() : Request {
		return $this->request ??= Request::createFromGlobals();
	}

	/*
	|-----------------
	| Request filters
	|-----------------
	*/

	/**
	 * Remember the route before WordPress checks authentication.
	 *
	 * `rest_exposed_cors_headers` is the earliest filter that receives the
	 * WP_REST_Request, and it runs before `rest_authentication_errors`.
	 *
	 * @param string[]        $headers    Exposed CORS headers.
	 * @param WP_REST_Request $wp_request The request.
	 * @return string[]
	 */
	public function set_current_route( $headers, $wp_request ) {
		$this->current_route = $wp_request->get_route();

		return $headers;
	}

	/**
	 * Apply the REST policy to requests for our API (`rest_authentication_errors`).
	 *
	 * Runs before WordPress's own cookie check (priority 100). Returning true
	 * or an error ends authentication there; null leaves WordPress's check to
	 * run on a request that, by then, has no signed-in user.
	 *
	 * @param WP_Error|true|null $result Result of earlier authentication filters.
	 * @return WP_Error|true|null
	 */
	public function authentication_errors( $result ) {
		if ( ! empty( $result ) || ! $this->in_api( $this->guess_route() ) ) {
			return $result;
		}

		$principal = $this->authenticate( $this->get_request() );

		if ( $principal instanceof RequestException ) {
			return $principal->to_wp_error();
		}

		return null === $principal ? null : true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * The REST policy, first match wins, as in the standalone environment:
	 *
	 *  1. Browser: a valid CSRF header (CSRF::HEADER) and a signed-in
	 *     WordPress user. The cookie alone is never enough.
	 *  2. Service account: an `Authorization: Bearer <api key>` header.
	 *  3. Guest: no principal; each route's guard decides.
	 *
	 * The WordPress current user is set to nobody unless step 1 accepts the
	 * cookie, so handlers that check WordPress capabilities see the same
	 * decision as our guards.
	 *
	 * @param Request|null $request The request; ours for this request when omitted.
	 * @return Principal|RequestException|null
	 */
	public function authenticate( ?Request $request = null ) : Principal|RequestException|null {
		$request ??= $this->get_request();

		$this->guard->clear_principal();

		// 1. Browser session, only with a valid CSRF header.
		$csrf_token = $request->get_header( CSRF::HEADER );

		if ( '' !== $csrf_token && is_user_logged_in() && $this->csrf->verify( $csrf_token ) ) {
			$principal = $this->identity->authenticate();

			if ( null !== $principal ) {
				$this->guard->set_principal( $principal );
				return $principal;
			}

			$this->guard->clear_principal();
		}

		wp_set_current_user( 0 );

		// 2. Service account.
		$api_key = (string) $request->bearerToken();

		if ( '' === $api_key ) {
			return null;
		}

		$result = ( new ServiceAccountAuthenticator( $api_key ) )->authenticate();

		if ( ! $result->is_authenticated() ) {
			return new RequestException(
				$result->error_code ?? 'rest_authentication_failed',
				$result->message ?? 'The API key could not be verified.',
				array( 'status' => AuthenticationResult::STATUS_UNAUTHORIZED === $result->status ? 403 : 401 )
			);
		}

		$principal = new Principal( $result->actor, $result->role, $result->owner );

		$this->guard->set_principal( $principal );

		return $principal;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Hooked to `rest_pre_dispatch`. Rejects plain-HTTP requests for our API,
	 * except on a site whose environment type is "local" (set by the site's
	 * own configuration, never by the request).
	 *
	 * @param mixed ...$params The `rest_pre_dispatch` arguments: $result, $server, $request.
	 * @return mixed The given result, or a WP_Error.
	 */
	public function enforce_https( mixed ...$params ) : mixed {
		[ $result, , $wp_request ] = $params + array( null, null, null );

		if ( null !== $result || ! $wp_request instanceof WP_REST_Request || ! $this->in_api( $wp_request->get_route() ) ) {
			return $result;
		}

		if ( is_ssl() || 'local' === wp_get_environment_type() ) {
			return $result;
		}

		return new WP_Error( 'rest_https_required', 'The REST API requires a secure (HTTPS) connection.', array( 'status' => 403 ) );
	}

	/**
	 * Serve the API descriptions (`rest_pre_dispatch`).
	 *
	 * GET on the API root or a version root returns the RESTIndex
	 * description instead of WordPress's namespace index; OPTIONS on any of
	 * our routes returns the route's description and Allow header instead of
	 * WordPress's own OPTIONS response.
	 *
	 * @param mixed           $result     Result of earlier filters; null to continue.
	 * @param WP_REST_Server  $server     The REST server.
	 * @param WP_REST_Request $wp_request The request.
	 * @return mixed
	 */
	public function describe( $result, $server, $wp_request ) : mixed {
		if ( null !== $result || ! $this->in_api( $wp_request->get_route() ) ) {
			return $result;
		}

		$method = $wp_request->get_method();
		$path   = trim( $wp_request->get_route(), '/' );

		if ( 'GET' !== $method && 'OPTIONS' !== $method ) {
			return $result;
		}

		// API root.
		if ( self::PREFIX === $path ) {
			return $this->description( $this->index->api(), 'GET, OPTIONS' );
		}

		foreach ( $this->index->namespaces() as $namespace ) {
			$root = self::PREFIX . '/' . $namespace;

			// Version root, optionally one category.
			if ( $root === $path ) {
				$category    = 'GET' === $method ? (string) $wp_request->get_param( 'category' ) : '';
				$description = $this->index->version( $namespace, $category );

				return null === $description
					? new WP_Error(
						'rest_unknown_category',
						sprintf( 'There is no "%s" category in %s.', $category, $namespace ),
						array( 'status' => 404, 'categories' => $this->index->catalog( $namespace )->categories() )
					)
					: $this->description( $description, $this->index->allow( $namespace ) );
			}

			// OPTIONS on a route: matched the way WordPress matches it.
			if ( 'OPTIONS' === $method && str_starts_with( $path, $root . '/' ) ) {
				foreach ( $this->index->catalog( $namespace )->list_routes() as $descriptor ) {
					$regex = '@^/' . $root . '/' . RoutePattern::compile( $descriptor['route'] )->namedRegex() . '/?$@i';

					if ( 1 === preg_match( $regex, '/' . $path ) ) {
						return $this->description(
							$this->index->route( $namespace, $descriptor['route'] ),
							$this->index->allow( $namespace, $descriptor['route'] )
						);
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Skip WordPress's Allow-header pass after a rejected permission check.
	 *
	 * @param mixed           $response   Result so far; an error when a check failed.
	 * @param array           $handler    Route handler.
	 * @param WP_REST_Request $wp_request The request.
	 * @return mixed
	 */
	public function rest_request_before_callbacks( $response, $handler, $wp_request ) : mixed {
		if ( $this->in_api( $wp_request->get_route() ) && is_smliser_error( $response ) ) {
			// Prevent WordPress calling the permission callbacks again.
			remove_filter( 'rest_post_dispatch', 'rest_send_allow_header' );
		}

		return $response;
	}

	/**
	 * Turn our Response objects into the WordPress response, as JSON.
	 *
	 * @param WP_REST_Response $response   The REST API response.
	 * @param WP_REST_Server   $server     The REST server.
	 * @param WP_REST_Request  $wp_request The request.
	 * @return WP_REST_Response
	 */
	public function filter_response( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $wp_request ) : WP_REST_Response {
		$route = $wp_request->get_route();

		if ( ! $this->in_api( $route ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( $data instanceof Response ) {
			$data->remove_header( 'Content-Length' ); // Let WordPress calculate it.

			foreach ( $data->get_headers( true ) as $key => $value ) {
				$response->header( $key, $value );
			}

			$response->set_status( $data->get_status_code() );

			$data = $data->has_errors() ? $data->get_exception()->to_wp_error() : $data->get_body();

			$response->set_data( $data );
		}

		// WordPress serves REST as JSON; a handler's own Content-Type must not say otherwise.
		$response->header( 'Content-Type', 'application/json; charset=' . get_option( 'blog_charset' ) );
		$response->header( 'X-App-Name', \SMLISER_APP_NAME );

		$namespace = $this->namespace_of( $route );

		if ( null !== $namespace ) {
			$response->header( 'X-API-Version', $namespace );
		}

		if ( is_array( $data ) ) {
			$response->set_data( array( 'success' => ! $response->is_error() ) + $data );
		}

		return $response;
	}

	/*
	|--------------------
	| Provider contract
	|--------------------
	*/

	/**
	 * {@inheritdoc}
	 */
	public function namespaces() : array {
		return $this->index->namespaces();
	}

	/**
	 * {@inheritdoc}
	 */
	public function version_instances() : array {
		return $this->versions;
	}

	/*
	|-------------------
	| Argument helpers
	|-------------------
	*/

	/**
	 * Add WordPress sanitization and validation callbacks to route arguments.
	 *
	 * @param array $args Raw route arguments.
	 * @return array
	 */
	private function prepare_rest_args( array $args ) : array {
		foreach ( $args as $key => &$arg ) {
			$type = $arg['type'] ?? null;

			if ( 'string' === $type ) {
				if ( 'domain' === $key ) {
					$arg['sanitize_callback'] = array( __CLASS__, 'sanitize_url' );
					$arg['validate_callback'] = array( __CLASS__, 'is_url' );
				} else {
					$arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
					$arg['validate_callback'] = array( __CLASS__, 'not_empty' );
				}
			} elseif ( 'integer' === $type ) {
				$arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
			} elseif ( 'array' === $type ) {
				$arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
				$arg['validate_callback'] ??= '__return_true';
			}
		}

		return $args;
	}

	/**
	 * Sanitize a REST parameter.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	public static function sanitize( $value ) : mixed {
		if ( is_string( $value ) ) {
			$value = static::sanitize_text( $value );
		} elseif ( is_array( $value ) ) {
			$value = static::sanitize_deep( $value );
		} elseif ( is_int( $value ) ) {
			$value = static::sanitize_int( $value );
		} elseif ( is_float( $value ) ) {
			$value = static::sanitize_float( $value );
		}

		return static::sanitize_auto( $value );
	}

	/**
	 * Sanitize a URL parameter.
	 *
	 * @param string $url The URL to sanitize.
	 * @return string
	 */
	public static function sanitize_url( $url ) : string {
		return URL::from( $url )->sanitize()->url();
	}

	/**
	 * Reject an empty parameter.
	 *
	 * @param string $value The value to check.
	 * @return WP_Error|bool
	 */
	public static function not_empty( $value ) : WP_Error|bool {
		if ( empty( $value ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'The value cannot be empty.', 'smliser' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Validate an HTTPS URL parameter.
	 *
	 * @param string          $url        The URL to validate.
	 * @param WP_REST_Request $wp_request The request.
	 * @param string          $param      The parameter name.
	 * @return true|WP_Error
	 */
	public static function is_url( $url, $wp_request, $param ) : bool|WP_Error {
		if ( empty( $url ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'The domain parameter is required.', 'smliser' ), array( 'status' => 400 ) );
		}

		$url = URL::from( $url );

		if ( ! $url->has_scheme() ) {
			return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
		}

		if ( ! $url->is_ssl() ) {
			return new WP_Error( 'rest_invalid_param', __( 'Only HTTPS URLs are allowed.', 'smliser' ), array( 'status' => 400 ) );
		}

		if ( ! $url->validate() ) {
			return new WP_Error( 'rest_invalid_param', __( 'The URL does not resolve to a valid host.', 'smliser' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Validate an integer parameter.
	 *
	 * @param mixed           $value      The value to validate.
	 * @param WP_REST_Request $wp_request The request.
	 * @param string          $key        The parameter name.
	 * @return true|WP_Error
	 */
	public static function is_int( $value, $wp_request, $key ) : bool|WP_Error {
		if ( ! is_numeric( $value ) || intval( $value ) != $value ) {
			return new WP_Error( 'rest_invalid_param', __( 'The value must be an integer.', 'smliser' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/*
	|---------
	| Routing
	|---------
	*/

	/**
	 * Whether a REST route belongs to our API: the API root or any version.
	 *
	 * @param string $route REST route, e.g. "/smliser/v1/repository".
	 * @return bool
	 */
	public function in_api( string $route ) : bool {
		$route = trim( $route, '/' );

		return self::PREFIX === $route || null !== $this->namespace_of( $route );
	}

	/**
	 * The version namespace a REST route belongs to.
	 *
	 * @param string $route REST route.
	 * @return string|null Null when the route is outside every version.
	 */
	private function namespace_of( string $route ) : ?string {
		$route = trim( $route, '/' );

		foreach ( $this->index->namespaces() as $namespace ) {
			$root = self::PREFIX . '/' . $namespace;

			if ( $root === $route || str_starts_with( $route, $root . '/' ) ) {
				return $namespace;
			}
		}

		return null;
	}

	/**
	 * The REST namespace a version registers under.
	 *
	 * @param string $namespace Version namespace, e.g. "v1".
	 * @return string E.g. "smliser/v1".
	 */
	private function rest_namespace( string $namespace ) : string {
		return self::PREFIX . '/' . $namespace;
	}

	/**
	 * The current REST route, also before WordPress has parsed the request.
	 *
	 * @return string
	 */
	public function guess_route() : string {
		if ( null !== $this->current_route ) {
			return $this->current_route;
		}

		// Pretty permalinks: <site path>/wp-json/<route>; plain: ?rest_route=<route>.
		$path   = '/' . trim( $this->get_request()->path(), '/' ) . '/';
		$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';
		$at     = strpos( $path, $prefix );
		$route  = false !== $at
			? substr( $path, $at + strlen( $prefix ) )
			: (string) $this->get_request()->query( 'rest_route', '' );

		return $this->current_route = '/' . trim( $route, '/' );
	}

	/**
	 * A description response with its Allow header.
	 *
	 * @param array  $data  Description.
	 * @param string $allow The Allow header.
	 * @return WP_REST_Response
	 */
	private function description( array $data, string $allow ) : WP_REST_Response {
		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'Allow', $allow );

		return $response;
	}
}