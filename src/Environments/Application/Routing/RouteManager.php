<?php
/**
 * RouteManager class file.
 *
 * @package SmartLicenseServer\Environments\Application\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Routing;

use SmartLicenseServer\Admin\ActionHandlers\AppManagement;
use SmartLicenseServer\Admin\Page\Dispatcher as AdminDispatcher;
use SmartLicenseServer\ClientDashboard\Handlers\AuthController;
use SmartLicenseServer\ClientDashboard\TemplateHandlers\AuthForms;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\DefaultPage;
use SmartLicenseServer\Environments\Application\Middlewares\AdminAccessMiddleware;
use SmartLicenseServer\Routing\Router as CoreRouter;
use SmartLicenseServer\Routing\DispatchStatus;
use SmartLicenseServer\RESTAPI\RESTVersionInterface;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The application environment's route manager, which wraps the core Router and 
 * provides the environment-specific dispatching logic.
 *
 * This class is: registers
 * routes onto the core Router, and is also the thing that resolves a live
 * request all the way through to calling a handler, middleware included.
 */
final class RouteManager {
	/** 
     * The 404 page response callback.
     * 
     * @var callable
     */
	private $notFoundHandler;

	/**
     * The 405 method not allowed page response callback. 
     * 
     * @var callable
     */
	private $methodNotAllowedHandler;

    /**
     * The default home page response callback. 
     * 
     * @var callable
     */
    private $defaultHomeHandler;

	public function __construct(
        protected CoreRouter $router,
        protected Container $container

    ) {}

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
        $this->router->any( '/', $this->defaultHomeHandler );
        
        $urlmanager = $this->container->get( URLManager::class );

        // Authentication routes.
        $this->router->group( $urlmanager->login_url_prefix(),
            function() {                
                $this->router->any(
                    pattern: '/',
                    handler: [AuthForms::class, 'render_login_form_shell'],
                );

                // Forms GET route.
                $this->router->get(
                    pattern: 'form/login',
                    handler: [AuthForms::class, 'render_json_login_form']
                );
                $this->router->get(
                    pattern: 'form/signup',
                    handler: [AuthForms::class, 'render_json_signup_form']
                );
                $this->router->get(
                    pattern: 'form/forgot-password',
                    handler: [AuthForms::class, 'render_json_forgot_password_form']
                );

                // Forms POST route.
                $this->router->post(
                    pattern: 'form/login',
                    handler: [AuthController::class, 'handle_login']
                );
                $this->router->post(
                    pattern: 'form/signup',
                    handler: [AuthController::class, 'handle_signup']
                );
                $this->router->post(
                    pattern: 'form/forgot-password',
                    handler: [AuthController::class, 'handle_forgot_password']
                );
            },
            middleware: []
        );

        $this->router->get(
            pattern: $urlmanager->logout_url_prefix(),
            handler: [AuthController::class, 'handle_logout']
        );

        // The admin dashboard routes.
        $this->router->group( $urlmanager->admin_url_prefix(),
            function() {
                // Main page.
                $this->router->get(
                    pattern: '/',
                    handler: [AdminDispatcher::class, 'render_admin_dashboard']
                );

                // The main page slug.
                $this->router->get(
                    pattern: '/{page:slug}',
                    handler: [AdminDispatcher::class, 'render_admin_dashboard']
                );

                // The submmenu tabs.
                $this->router->get(
                    pattern: '/{page:slug}/{tab:slug}',
                    handler: [AdminDispatcher::class, 'render_admin_dashboard']
                );

                // POST, PUT, PATCH routes for form submissions and button clicks.
                $this->router->group( 'admin-json', function() {
                    // Base tab without form slug or action.
                    $this->router->any( '/', fn () : Response => 
                        Response::json(
                            [
                                'success'   => false,
                                'data'      => [
                                    'message'   => 'Form slug or action path is required.'
                                ]
                            ],
                            400
                        )
                    );

                    $this->router->post(
                        pattern: 'save-app',
                        handler: [AppManagement::class, 'handle_save_app_request'],
                        middleware: []
                    );

                    $this->router->add(
                        pattern: 'upload-app-assets',
                        methods: ['POST', 'PUT'],
                        handler: [AppManagement::class, 'handle_app_asset_upload_request'],
                        middleware: []
                    );

                    $this->router->delete(
                        pattern: 'app-assets',
                        handler: [AppManagement::class, 'handle_app_asset_delete_request'],
                        middleware: []
                    );

                    $this->router->post(
                        pattern: 'app-artifacts',
                        handler: [AppManagement::class, 'handle_app_artifact_upload_request'],
                        middleware: []
                    );
                });
                
            },

            middleware: [
                AdminAccessMiddleware::class
            ]
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
	public function dispatch( Request $request ): Response {
		$result = $this->router->dispatch( $request->method(), $request->path() );

        $request->set_route_param( $result->params );

		$response = match ( $result->status ) {
			DispatchStatus::Found       => MiddlewarePipeline::run(
                $this->container,
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