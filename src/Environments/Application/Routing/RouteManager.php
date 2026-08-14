<?php
/**
 * RouteManager class file.
 *
 * @package SmartLicenseServer\Environments\Application\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Routing;

use SmartLicenseServer\Admin\Page\Shell;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\DefaultPage;
use SmartLicenseServer\Routing\Router as CoreRouter;
use SmartLicenseServer\Routing\DispatchStatus;
use SmartLicenseServer\RESTAPI\RESTVersionInterface;

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
 *   2. From a provider shaped like RESTVersionInterface (route/methods/handler/
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

    /** @var callable|null */
    private $defaultHomeHandler;

	public function __construct( ?CoreRouter $router = null ) {
		$this->router = $router ?? new CoreRouter();

        $this->notFound( [DefaultPage::class, 'not_found'] );
        $this->methodNotAllowed( [DefaultPage::class, 'method_not_allowed'] );
        $this->homeHandler( [DefaultPage::class, 'home'] );
	}

	/**
	 * Direct access to the underlying core Router — group()/add()/get()/
	 * post()/etc. all work exactly as documented there.
	 */
	public function router(): CoreRouter {
		return $this->router;
	}

	/**
	 * Registers a RESTVersionInterface provider's routes onto the underlying core Router.
     * 
     * @return void
	 */
	public function registerProvider( RESTVersionInterface $provider ): void {
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
        $admin_url_prefix   = smliser_get_admin_url_prefix();

        $this->router->any( '/', $this->defaultHomeHandler );
        $this->router->withMiddleware(
            methods: ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
            pattern: "$admin_url_prefix",
            handler: [Shell::class, 'render'],
            middleware: []
            
        );
        
        $this->router->group( 'documentation', function () {
            $this->router->get( '/', [DefaultPage::class, 'doc_page'] );
            $this->router->get( '{category:slug}', [DefaultPage::class, 'doc_page'] );
        });
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
     * 
     * @param callable( Request ): Response $handler The callback to invoke when no route matches.
     * @return self
	 */
	public function notFound( callable $handler ): self {
		$this->notFoundHandler = $handler;

		return $this;
	}

    /**
     * Sets the callback handler for the homepage.
     * 
     * @param callable( Request ) : Response $callback
     */
    public function homeHandler( callable $callback ) : self {
        $this->defaultHomeHandler = $callback;

        return $this;
    }

	/**
	 * Sets the callback invoked when a route matches the path but not the
	 * method. Receives `($request, string[] $allowedMethods)`.
     * 
     * @param callable( Request, string[] ): Response $handler The callback to invoke when a route matches the path but not the method.
     * @return self
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
	public function dispatch( string $method, string $path, ?Request $request = null ): Response {
		$result = $this->router->dispatch( $method, $path );

        $request->set_route_param( $result->params );

		$response = match ( $result->status ) {
			DispatchStatus::Found       => MiddlewarePipeline::run(
				$result->middleware,
				$result->handler,
				$request,
			),
			DispatchStatus::NotFound    => null !== $this->notFoundHandler
				? ( $this->notFoundHandler )( $request )
				: null,
			DispatchStatus::MethodNotAllowed => null !== $this->methodNotAllowedHandler
				? ( $this->methodNotAllowedHandler )( $request, $result->allowedMethods )
				: null,
		};

        return $this->ensure_response( $response );
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

    /**
     * Normalizes arbitrary route handler return values into a unified Response object.
     *
     * @param mixed $data Raw value returned from a route handler.
     * @return Response Fully populated framework Response object.
     * @throws \RuntimeException If the data type cannot be safely converted to a response.
     */
    private function ensure_response( mixed $data ) : Response {
        // Direct pass-through if it's already a Response instance.
        if ( $data instanceof Response ) {
            return $data;
        }

        // Handle empty/null returns (e.g., HTTP 204 No Content).
        if ( null === $data ) {
            return Response::make( '', 204 );
        }

        // Handle arrays and JsonSerializable objects as JSON responses.
        if ( is_array( $data ) || $data instanceof \JsonSerializable ) {
            $encoded = \smliser_safe_json_encode( $data );
            
            if ( false === $encoded ) {
                throw new \RuntimeException( 'Failed to encode route response data to JSON.' );
            }

            return Response::make( $encoded )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }

        // Handle standard strings.
        if ( is_string( $data ) ) {
            return Response::make( $data )
                ->set_header( 'Content-Type', 'text/html; charset=utf-8' );
        }

        // Handle Stringable objects (PHP 8.0+ or custom __toString implementers).
        if ( is_object( $data ) && ( $data instanceof \Stringable || method_exists( $data, '__toString' ) ) ) {
            return Response::make( (string) $data )
                ->set_header( 'Content-Type', 'text/html; charset=utf-8' );
        }

        // Handle scalar values (booleans, integers, floats).
        if ( is_scalar( $data ) ) {
            $content = is_bool( $data ) ? ( $data ? 'true' : 'false' ) : (string) $data;

            return Response::make( $content )
                ->set_header( 'Content-Type', 'text/html; charset=utf-8' );
        }

        // Handle invokable objects/callables (e.g., dynamic response generators).
        if ( is_callable( $data ) ) {
            if ( $this->returns_response( $data ) ) {
                return $this->ensure_response( $data() );
            }

            return Response::make( $this->capture( $data ) )
                ->set_header( 'Content-Type', 'text/html; charset=utf-8' );
        }

        // Unsupported type safety check.
        $type = is_object( $data ) ? $data::class : gettype( $data );
        throw new \RuntimeException( sprintf( 'Route handler returned unsupported response type: %s', $type ) );
    }

    private function returns_response( callable $callable ) : bool {
        try {
            if ( is_array( $callable ) ) {
                $reflection = new \ReflectionMethod( $callable[0], $callable[1] );
            } elseif ( is_string( $callable ) && str_contains( $callable, '::' ) ) {
                $reflection = new \ReflectionMethod( $callable );
            } else {
                $reflection = new \ReflectionFunction( $callable );
            }

            $returnType = $reflection->getReturnType();

            if ( $returnType instanceof \ReflectionNamedType ) {
                return $returnType->getName() === Response::class;
            }

            if ( $returnType instanceof \ReflectionUnionType ) {
                foreach ( $returnType->getTypes() as $type ) {
                    if ( $type instanceof \ReflectionNamedType && $type->getName() === Response::class ) {
                        return true;
                    }
                }
            }

            return false;
        } catch ( \ReflectionException ) {
            return false;
        }
    }

    private function capture( callable $callable ) : string {
        ob_start();
        try {
            $callable();

            // Check if the callback itself forced a header flush or called flush() during execution
            if ( headers_sent( $file, $line ) ) {
                ob_end_clean();
                throw new \LogicException(
                    sprintf( 'Route callback flushed output prematurely at %s on line %d.', $file, $line )
                );
            }

            return (string) ob_get_clean();
        } catch ( \Throwable $e ) {
            if ( ob_get_level() > 0 ) {
                ob_end_clean();
            }
            throw $e;
        }
    }

}