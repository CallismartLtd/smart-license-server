<?php
/**
 * Router class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * Environment-agnostic router: registers (method, pattern) -> handler routes
 * and dispatches a live (method, path) request against them.
 *
 * This is the standalone-environment counterpart to
 * SmartLicenseServer\Environments\WordPress\Routing\Router. Both compile
 * patterns with the exact same RoutePattern — one DSL, two consumers. The
 * WordPress adapter translates patterns into add_rewrite_rule() calls
 * because WordPress' own hook system does the actual request dispatch; this
 * one does the dispatch itself, because a standalone environment has nothing
 * else that will.
 *
 * ## Example
 *
 *     $router = new Router();
 *
 *     $router->group( 'downloads', function ( Router $router ): void {
 *         $router->get( '{download_type}/license-document-{license_id:int}.txt', $licenseHandler );
 *         $router->get( '{download_type}/{app_slug_filename.ext:zip}', $zipHandler );
 *     } );
 *
 *     $router->post( 'license-activation/{app_type:slug}/{app_slug:slug}', $activateHandler )
 *         ->name( 'license.activate' );
 *
 *     $result = $router->dispatch( $_SERVER['REQUEST_METHOD'], $path );
 *
 *     match ( $result->status ) {
 *         DispatchStatus::Found            => call_user_func( $result->handler, $result->params ),
 *         DispatchStatus::MethodNotAllowed => http_response_code( 405 ),
 *         DispatchStatus::NotFound         => http_response_code( 404 ),
 *     };
 *
 *     $router->url( 'license.activate', array( 'app_type' => 'plugin', 'app_slug' => 'woocommerce' ) );
 */
final class Router {

	private RouteCollection $routes;

	/** @var array<string,string> */
	private array $constraints = array();

	/** @var string[] */
	private array $groupStack = array();

	public function __construct() {
		$this->routes = new RouteCollection();
	}

	/**
	 * Registers a custom constraint alias usable as `{name:alias}`.
	 * Overrides a built-in alias of the same name if one exists.
	 */
	public function constraint( string $alias, string $regex ): self {
		$this->constraints[ $alias ] = $regex;

		return $this;
	}

	/**
	 * Groups routes under a shared path prefix. Nestable.
	 *
	 * @param callable(self): void $callback
	 */
	public function group( string $prefix, callable $callback ): void {
		$this->groupStack[] = trim( $prefix, '/' );

		try {
			$callback( $this );
		} finally {
			array_pop( $this->groupStack );
		}
	}

	/**
	 * Registers a route.
	 *
	 * @param string          $pattern               Pattern, relative to any enclosing group(s).
	 * @param string|string[] $methods               One or more HTTP methods (case-insensitive).
	 * @param mixed           $handler                Whatever the caller wants to invoke on a match —
	 *                                                this router doesn't call it, just returns it.
	 * @param bool            $optionalTrailingSlash Whether the path may end in an optional '/'.
	 * @return Route The registered route — chain ->name() on it if you'll need Router::url() later.
	 * @throws InvalidRouteException On a malformed pattern or invariant violation.
	 */
	public function add(
		string $pattern,
		string|array $methods,
		mixed $handler,
		bool $optionalTrailingSlash = true
	): Route {
		$fullPattern = $this->applyGroupPrefix( $pattern );
		$compiled    = RoutePattern::compile( $fullPattern, $this->constraints );
		$methods     = array_map( 'strtoupper', (array) $methods );

		$route = new Route( $fullPattern, $methods, $handler, $optionalTrailingSlash, $compiled );

		$this->routes->add( $route );

		return $route;
	}

	public function get( string $pattern, mixed $handler, bool $optionalTrailingSlash = true ): Route {
		return $this->add( $pattern, array( 'GET', 'HEAD' ), $handler, $optionalTrailingSlash );
	}

	public function post( string $pattern, mixed $handler, bool $optionalTrailingSlash = true ): Route {
		return $this->add( $pattern, 'POST', $handler, $optionalTrailingSlash );
	}

	public function put( string $pattern, mixed $handler, bool $optionalTrailingSlash = true ): Route {
		return $this->add( $pattern, 'PUT', $handler, $optionalTrailingSlash );
	}

	public function patch( string $pattern, mixed $handler, bool $optionalTrailingSlash = true ): Route {
		return $this->add( $pattern, 'PATCH', $handler, $optionalTrailingSlash );
	}

	public function delete( string $pattern, mixed $handler, bool $optionalTrailingSlash = true ): Route {
		return $this->add( $pattern, 'DELETE', $handler, $optionalTrailingSlash );
	}

	/**
	 * Matches a live (method, path) request against the route table.
	 *
	 * Distinguishes "no route matches this path at all" from "a route
	 * matches this path but not this method" — collapsing those into one
	 * outcome (as a plain match()-style boolean would) loses the
	 * information needed to correctly respond 404 vs 405. Every route whose
	 * *pattern* matches the path contributes its methods to the eventual
	 * 405's allowed-methods list, even though only the first is used to
	 * decide FOUND — so a client asking for a disallowed method still finds
	 * out what would have worked.
	 */
	public function dispatch( string $method, string $path ): DispatchResult {
		$method         = strtoupper( $method );
		$allowedMethods = array();

		foreach ( $this->routes->all() as $route ) {
			$params = $route->match( $path );

			if ( null === $params ) {
				continue;
			}

			if ( in_array( $method, $route->methods, true ) ) {
				return DispatchResult::found( $route, $params );
			}

			array_push( $allowedMethods, ...$route->methods );
		}

		if ( ! empty( $allowedMethods ) ) {
			return DispatchResult::methodNotAllowed( array_values( array_unique( $allowedMethods ) ) );
		}

		return DispatchResult::notFound();
	}

	/**
	 * Builds a relative URL path for a named route.
	 *
	 * @param array<string,scalar> $params Parameter values keyed by name — for a `{foo.ext}`
	 *                                     placeholder, supply both `foo` and `foo_ext`.
	 * @throws InvalidRouteException If the route is unknown or a required parameter is missing.
	 */
	public function url( string $name, array $params = array() ): string {
		$route = $this->routes->find( $name );

		if ( null === $route ) {
			throw new InvalidRouteException( sprintf( 'No route named "%s" is registered.', $name ) );
		}

		return RouteUrlBuilder::build( $route, $params );
	}

	/**
	 * @return Route[]
	 */
	public function getRoutes(): array {
		return $this->routes->all();
	}

	private function applyGroupPrefix( string $pattern ): string {
		$prefix  = implode( '/', array_filter( $this->groupStack, static fn( string $p ) => '' !== $p ) );
		$pattern = trim( $pattern, '/' );

		if ( '' === $prefix ) {
			return $pattern;
		}

		return '' === $pattern ? $prefix : $prefix . '/' . $pattern;
	}
}