<?php
/**
 * Rest API provider class.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Web;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Middlewares\CallableResolver;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\RESTAPI\RESTVersionInterface;
use SmartLicenseServer\RESTAPI\RESTIndex;
use SmartLicenseServer\Routing\Router as CoreRouter;
use SmartLicenseServer\Security\Authentication\AuthenticationResult;
use SmartLicenseServer\Security\Authentication\ServiceAccountAuthenticator;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\CSRF\CSRF;

/**
 * Registers the REST API versions on the standalone router and enforces the
 * rules every REST route shares.
 *
 * Each version is mounted under PREFIX/<namespace> (e.g. /smliser/v1), and
 * the API describes itself through RESTIndex (shared with every environment):
 *
 *  - GET /smliser                    Every version, with its categories.
 *  - GET /smliser/<ns>               One version's routes; ?category=<name>
 *                                    narrows them to a category.
 *  - OPTIONS on any of the above, and on every route: its description, with
 *    an Allow header. Guards do not run for OPTIONS; it only describes.
 *
 * Every REST route runs inside the same group middleware, outermost first:
 *
 *  1. JSON: whatever the route returns leaves with an application/json
 *     Content-Type, including rejections from the steps below.
 *  2. HTTPS: plain-HTTP requests are rejected (see enforce_https()).
 *  3. Authentication: the principal is resolved by the REST policy (see
 *     authenticate()): a signed-in browser with a valid CSRF header, else a
 *     service account's bearer token, else a guest.
 *
 * A route's own guard then runs as route middleware, before its handler.
 */
class RestAPIProvider implements RESTProviderInterface {

	/**
	 * Namespaces of the registered versions, built on first use.
	 *
	 * @var string[]|null
	 */
	private ?array $namespaces = null;

	/**
	 * Describes the API, its versions and routes.
	 *
	 * @var RESTIndex
	 */
	private RESTIndex $index;

	/**
	 * Class constructor.
	 *
	 * @param RESTVersionInterface[] $versions REST API versions.
	 * @param IdentityService        $identity Resolves the browser session's principal.
	 * @param Guard                  $guard    Holds the principal for the request.
	 * @param CSRF                   $csrf     Verifies the CSRF header of browser requests.
	 * @param URLManager             $urls     The application's URLs.
	 */
	private function __construct(
		protected array $versions,
		protected IdentityService $identity,
		protected Guard $guard,
		protected CSRF $csrf,
		protected URLManager $urls
	) {
		$this->index = new RESTIndex(
			$versions,
			fn( string $path ) : string => $this->urls->url( $path )->get_href()
		);
	}

	/**
	 * Create the provider.
	 *
	 * @param IdentityService      $identity    Resolves the browser session's principal.
	 * @param Guard                $guard       Holds the principal for the request.
	 * @param CSRF                 $csrf        Verifies the CSRF header of browser requests.
	 * @param URLManager           $urls        The application's URLs.
	 * @param RESTVersionInterface ...$versions REST API versions.
	 * @return static
	 */
	public static function init( IdentityService $identity, Guard $guard, CSRF $csrf, URLManager $urls, RESTVersionInterface ...$versions ) : static {
		return new static( $versions, $identity, $guard, $csrf, $urls );
	}

	/*
	|---------------
	| Registration
	|---------------
	*/

	/**
	 * Mount the index routes and every version's routes on the router.
	 *
	 * @param CoreRouter       $router   The core router.
	 * @param CallableResolver $resolver Resolves route guards to callables.
	 * @return void
	 */
	public function register( CoreRouter $router, CallableResolver $resolver ) : void {
		$router->group(
			prefix: self::PREFIX,
			callback: function ( CoreRouter $router ) use ( $resolver ) {
				$router->get( '', fn() : Response => Response::json( $this->index->api() ) );
				$router->add( pattern: '', methods: array( 'OPTIONS' ), handler: fn() : Response => $this->options( 'GET, OPTIONS', $this->index->api() ) );

				foreach ( $this->versions as $version ) {
					$this->register_version( $router, $version, $resolver );
				}
			},
			middleware: array(
				array( $this, 'json_middleware' ),
				array( $this, 'https_middleware' ),
				array( $this, 'authentication_middleware' ),
			)
		);
	}

	/**
	 * Mount one version: its index, its routes, and an OPTIONS route for each.
	 *
	 * @param CoreRouter           $router   The core router, inside the PREFIX group.
	 * @param RESTVersionInterface $version  The version.
	 * @param CallableResolver     $resolver Resolves route guards to callables.
	 * @return void
	 */
	private function register_version( CoreRouter $router, RESTVersionInterface $version, CallableResolver $resolver ) : void {
		$config    = $version::get_routes();
		$namespace = $version->namespace();
		$catalog   = $this->index->catalog( $namespace );

		$router->group(
			prefix: $namespace,
			callback: function ( CoreRouter $router ) use ( $config, $namespace, $catalog, $resolver ) {
				$router->get( '', fn( Request $request ) : Response => $this->version_index( $namespace, $request ) );
				$router->add( pattern: '', methods: array( 'OPTIONS' ), handler: fn() : Response => $this->options( $this->index->allow( $namespace ), $this->index->version( $namespace ) ) );

				foreach ( $config['routes'] as $route ) {
					$middleware = array();

					if ( isset( $route['guard'] ) ) {
						$middleware[] = $this->guard_middleware( $route['guard'], $resolver );
					}

					$router->add(
						pattern: $route['route'],
						methods: $route['methods'],
						handler: $route['handler'],
						middleware: $middleware
					);
				}

				// One OPTIONS route per distinct pattern, unless the version handles OPTIONS itself.
				foreach ( $catalog->list_routes() as $descriptor ) {
					if ( isset( $descriptor['methods']['OPTIONS'] ) ) {
						continue;
					}

					$pattern = $descriptor['route'];

					$router->add(
						pattern: $pattern,
						methods: array( 'OPTIONS' ),
						handler: fn() : Response => $this->options(
							$this->index->allow( $namespace, $pattern ),
							$this->index->route( $namespace, $pattern )
						)
					);
				}
			}
		);
	}

	/*
	|----------------
	| Index handlers
	|----------------
	*/

	/**
	 * GET /smliser/<ns>: one version's routes, optionally one category's.
	 *
	 * @param string  $namespace Version namespace.
	 * @param Request $request   The request; ?category=<name> narrows the routes.
	 * @return Response
	 */
	public function version_index( string $namespace, Request $request ) : Response {
		$category    = (string) $request->query( 'category', '' );
		$description = $this->index->version( $namespace, $category );

		if ( null === $description ) {
			return Response::json(
				array(
					'code'       => 'rest_unknown_category',
					'message'    => sprintf( 'There is no "%s" category in %s.', $category, $namespace ),
					'categories' => $this->index->catalog( $namespace )->categories(),
				),
				404
			);
		}

		return Response::json( $description );
	}

	/**
	 * An OPTIONS response.
	 *
	 * @param string $allow The Allow header, OPTIONS included.
	 * @param array  $body  Description of the resource.
	 * @return Response
	 */
	private function options( string $allow, array $body ) : Response {
		return Response::json( $body )->set_header( 'Allow', $allow );
	}

	/*
	|-------------
	| Middleware
	|-------------
	*/

	/**
	 * Give every REST response an application/json Content-Type.
	 *
	 * A Response keeps its body and status; only the header is set. Any other
	 * value a handler returns is JSON-encoded into a Response. Nothing
	 * (null) is left alone, so it still becomes a bodiless 204.
	 *
	 * @param Request  $request The request.
	 * @param callable $next    The rest of the pipeline.
	 * @return mixed
	 * @throws \RuntimeException When a returned value cannot be JSON-encoded.
	 */
	public function json_middleware( Request $request, callable $next ) : mixed {
		$result = $next( $request );

		if ( null === $result ) {
			return null;
		}

		if ( $result instanceof Response ) {
			return $result->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}

		$encoded = \smliser_safe_json_encode( $result );

		if ( false === $encoded ) {
			throw new \RuntimeException( 'Failed to encode REST response data to JSON.' );
		}

		return Response::json( $encoded );
	}

	/**
	 * Reject REST requests that did not arrive over HTTPS.
	 *
	 * @param Request  $request The request.
	 * @param callable $next    The rest of the pipeline.
	 * @return mixed
	 */
	public function https_middleware( Request $request, callable $next ) : mixed {
		$result = $this->enforce_https( $request );

		if ( true !== $result ) {
			return Response::error( $result, 403 );
		}

		return $next( $request );
	}

	/**
	 * Resolve the principal before the route's guard runs.
	 *
	 * Rejects only presented credentials that fail: a bearer token that is
	 * invalid gets 401 (403 when the account may not act), never a silent
	 * downgrade to guest. Requests without credentials continue as guests.
	 *
	 * @param Request  $request The request.
	 * @param callable $next    The rest of the pipeline.
	 * @return mixed
	 */
	public function authentication_middleware( Request $request, callable $next ) : mixed {
		$result = $this->authenticate( $request );

		if ( $result instanceof RequestException ) {
			$status   = (int) ( $result->get_error_data()['status'] ?? 401 );
			$response = Response::error( $result, $status );

			return 401 === $status ? $response->set_header( 'WWW-Authenticate', 'Bearer' ) : $response;
		}

		return $next( $request );
	}

	/**
	 * Wrap a route guard as route middleware.
	 *
	 * The guard receives the request and returns true to allow it, or a
	 * RequestException to reject it.
	 *
	 * @param mixed            $guard    Guard definition, e.g. [ Licenses::class, 'activation_permission_callback' ].
	 * @param CallableResolver $resolver Resolves the guard to a callable.
	 * @return callable( Request, callable ): mixed
	 */
	protected function guard_middleware( mixed $guard, CallableResolver $resolver ) : callable {
		return static function ( Request $request, callable $next ) use ( $guard, $resolver ) : mixed {
			/** @var true|RequestException $result */
			$result = $resolver->resolveMiddleware( $guard )( $request );

			if ( true !== $result ) {
				return Response::error( $result );
			}

			return $next( $request );
		};
	}

	/*
	|--------------------
	| Provider contract
	|--------------------
	*/

	/**
	 * {@inheritdoc}
	 *
	 * Allows requests that arrived over HTTPS, directly or as reported by a
	 * proxy in X-Forwarded-Proto. The one exception is an installation whose
	 * configured address is plain HTTP on localhost: a local development
	 * copy. That comes from the installation's own configuration, never from
	 * the request, so a client cannot claim it.
	 *
	 * @param mixed ...$params The Request as the first parameter.
	 * @return true|RequestException
	 */
	public function enforce_https( mixed ...$params ) : mixed {
		$request = $params[0] ?? null;

		if ( $request instanceof Request && $request->isSecure() ) {
			return true;
		}

		$app_url = $this->urls->url();

		if ( ! $app_url->is_ssl() && $app_url->is_localhost() ) {
			return true;
		}

		return new RequestException( 'rest_https_required', 'The REST API requires a secure (HTTPS) connection.', array( 'status' => 403 ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * The REST policy, first match wins:
	 *
	 *  1. Browser: a valid CSRF header (CSRF::HEADER) and a signed-in session
	 *     cookie. A cookie alone is never enough, so another site cannot make
	 *     a signed-in browser call the API.
	 *  2. Service account: an `Authorization: Bearer <api key>` header.
	 *  3. Guest: no principal; each route's guard decides what guests may do.
	 *
	 * The Guard is cleared first, so a principal resolved earlier in the
	 * request from the session cookie never reaches a REST guard unless
	 * step 1 accepts it.
	 *
	 * @param Request|null $request The request; read from the globals when omitted.
	 * @return Principal|RequestException|null The principal, a rejection of the presented credentials, or null for a guest.
	 */
	public function authenticate( ?Request $request = null ) : Principal|RequestException|null {
		$request ??= Request::createFromGlobals();

		$this->guard->clear_principal();

		// 1. Browser session, only with a valid CSRF header.
		$csrf_token = $request->get_header( CSRF::HEADER );

		if ( '' !== $csrf_token && $this->csrf->verify( $csrf_token ) ) {
			$principal = $this->identity->authenticate();

			if ( null !== $principal ) {
				$this->guard->set_principal( $principal );
				return $principal;
			}

			$this->guard->clear_principal();
		}

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
	 */
	public function namespaces() : array {
		if ( null === $this->namespaces ) {
			$this->namespaces = array_map(
				static fn( RESTVersionInterface $version ) : string => $version->namespace(),
				$this->versions
			);
		}

		return $this->namespaces;
	}

	/**
	 * {@inheritdoc}
	 */
	public function version_instances() : array {
		return $this->versions;
	}
}