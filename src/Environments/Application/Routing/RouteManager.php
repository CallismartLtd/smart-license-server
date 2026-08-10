<?php
/**
 * RouteManager class file.
 *
 * @package SmartLicenseServer\Environments\Application\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Routing;

use SmartLicenseServer\Routing\Router as CoreRouter;
use SmartLicenseServer\Routing\DispatchStatus;
use SmartLicenseServer\RESTAPI\RESTInterface;

/**
 * The application environment's route manager, which wraps the core Router and 
 * provides the environment-specific dispatching logic.
 *
 * This class is: registers
 * routes onto the core Router, and is also the thing that resolves a live
 * request all the way through to calling a handler, middleware included.
 *
 * ## Registering routes
 *
 * Two ways in, both ending up on the same core Router:
 *
 *   1. Direct access for routes specific to this environment:
 *
 *          $manager->router()->get( 'health', $healthHandler );
 *
 *   2. From a provider shaped like RESTInterface (route/methods/handler/
 *      guard/args) — the exact shape SmartLicenseServer\RESTAPI\Versions\V1
 *      already returns, `guard`, if present, becomes middleware — run before anything else on
 *      that route, since a guard's whole job is "reject before work starts."
 *      `namespace`, if present, becomes a group prefix.
 *
 * ## Dispatching a request
 *
 *      $manager->notFound( fn( $request ) => /* build a 404 *\/ );
 *      $manager->methodNotAllowed( fn( $request, $allowed ) => /* build a 405 *\/ );
 *
 *      $manager->dispatch( $_SERVER['REQUEST_METHOD'], RouteManager::pathFromServer( $_SERVER ), $request );
 *
 * Assumption worth double-checking against the real Request/Response
 * classes: handlers, guards, and middleware here are all called as
 * `($request, array $params[, callable $next])` — $request is passed
 * through untouched (this class never inspects or builds one), matching
 * the `(Request $request)` shape WordPress' Dispatcher handlers already
 * use, with `$params` added since there's no query-var indirection to pull
 * them from here. If the real calling convention differs, that's isolated
 * to this file and MiddlewarePipeline — nothing in core needs to change.
 */
final class RouteManager {

	private CoreRouter $router;

	/** @var callable|null */
	private $notFoundHandler;

	/** @var callable|null */
	private $methodNotAllowedHandler;

	public function __construct( ?CoreRouter $router = null ) {
		$this->router = $router ?? new CoreRouter();
	}

	/**
	 * Direct access to the underlying core Router — group()/add()/get()/
	 * post()/etc. all work exactly as documented there.
	 */
	public function router(): CoreRouter {
		return $this->router;
	}

	/**
	 * Registers a RESTInterface provider's routes onto the underlying core Router.
     * 
     * @return void
	 */
	public function registerProvider( RESTInterface $provider ): void {
		$config    = $provider->get_routes();
		$namespace = $config['namespace'] ?? '';
		$routes    = $config['routes'] ?? array();

		$register = static function ( CoreRouter $router ) use ( $routes ): void {
			foreach ( $routes as $route ) {
				$middleware = array();

				if ( isset( $route['guard'] ) ) {
					$middleware[] = self::guardAsMiddleware( $route['guard'] );
				}

				$router->add(
					$route['route'],
					$route['methods'] ?? ['GET'],
					$route['handler'],
					true,
					$middleware
				);
			}
		};

		if ( '' !== $namespace ) {
			$this->router->group( $namespace, $register );
			return;
		}

		$register( $this->router );
	}

    /**
     * Registers core routes onto the underlying core Router.
     * 
     * @return void
     */
    public function registerCoreRoutes() : void {
        $this->router->any( '/', 'smliser_dump_url' );

        
    }

	/**
	 * Wraps a guard callable — `($request, array $params): mixed`, WP-style
	 * permission-callback convention — as middleware. A falsy or null result
	 * rejects the request (that result is returned as-is, without ever
	 * calling $next, i.e. without running the handler); anything else
	 * proceeds.
	 */
	private static function guardAsMiddleware( callable $guard ): callable {
		return static function ( $request, array $params, callable $next ) use ( $guard ): mixed {
			$result = $guard( $request, $params );

			if ( false === $result || null === $result ) {
				return $result;
			}

			return $next( $request, $params );
		};
	}

	/**
	 * Sets the callback invoked when no route matches the path at all.
	 * Receives `($request)`.
	 */
	public function notFound( callable $handler ): self {
		$this->notFoundHandler = $handler;

		return $this;
	}

	/**
	 * Sets the callback invoked when a route matches the path but not the
	 * method. Receives `($request, string[] $allowedMethods)`.
	 */
	public function methodNotAllowed( callable $handler ): self {
		$this->methodNotAllowedHandler = $handler;

		return $this;
	}

	/**
	 * Resolves a live (method, path) request against the route table and
	 * dispatches it: on a match, runs the resolved middleware stack and
	 * finally calls the handler; otherwise calls whichever of
	 * notFound()/methodNotAllowed() applies, if set.
	 *
	 * Returns whatever the handler (or fallback) returns — this class makes
	 * no assumption about what that is (a Response object, void, anything),
	 * since that's the handler's decision, not the router's.
	 */
	public function dispatch( string $method, string $path, mixed $request = null ): mixed {
		$result = $this->router->dispatch( $method, $path );

		return match ( $result->status ) {
			DispatchStatus::Found            => MiddlewarePipeline::run(
				$result->middleware,
				$result->handler,
				$request,
				$result->params
			),
			DispatchStatus::NotFound         => null !== $this->notFoundHandler
				? ( $this->notFoundHandler )( $request )
				: null,
			DispatchStatus::MethodNotAllowed => null !== $this->methodNotAllowedHandler
				? ( $this->methodNotAllowedHandler )( $request, $result->allowedMethods )
				: null,
		};
	}

	/**
	 * Small convenience for the common case: derive a route-matchable path
	 * from $_SERVER, stripping the query string. Not required — pass any
	 * path string to dispatch() directly if this doesn't fit.
	 */
	public static function pathFromServer( array $server ): string {
		$uri  = $server['REQUEST_URI'] ?? '/';
		$path = parse_url( $uri, PHP_URL_PATH );

		return trim( (string) ( $path ?? '/' ), '/' );
	}
}