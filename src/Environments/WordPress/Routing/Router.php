<?php
/**
 * Router class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

/**
 * Fluent façade over WordPress' rewrite API.
 *
 * A route is described once, as a pattern string; Router derives the regex,
 * the `$matches[n]` -> query var mapping, and the query_vars list, compiling
 * each route exactly once at add()-time. register() and query_vars() are
 * then cheap iterations over already-compiled data — no parsing happens on
 * every request.
 *
 * This is an abstraction over WordPress' rewrite rules, not a replacement for
 * them: matching an incoming request against a rule, and dispatching to a
 * handler based on the resulting query vars, is still WordPress' job via its
 * `parse_request` / `template_include` pipeline. A separate Dispatcher class
 * was deliberately not added here — WordPress already fulfills that role for
 * every route registered through this class, and duplicating it would fight
 * the platform rather than sit on top of it.
 *
 * ## Placeholder syntax
 *
 *   {name}                  Segment capture, defaults to `[^/]+`.
 *   {name:regex}             Segment capture using a literal regex fragment.
 *   {name:alias}             Segment capture using a constraint alias
 *                           (built in: int, slug, uuid, path; see constraint()).
 *   {name?}                  Optional segment — must be the last segment, or
 *                           followed only by other optional segments.
 *   {name.ext}                Filename + extension, captured as `name` and `name_ext`.
 *   {name.ext:png|jpg|zip}    As above, extension restricted to a whitelist or alias.
 *
 * ## Example
 *
 *     $router = new Router();
 *
 *     $router->group( 'repository', function ( Router $router ): void {
 *         $router->add( '', 'smliser-repository' );
 *         $router->add( '{app_type}', 'smliser-repository' );
 *         $router->add( '{app_type}/{app_slug}', 'smliser-repository' );
 *         $router
 *             ->add(
 *                 '{app_type}/{app_slug}/assets/{asset_name.ext:png|jpg|jpeg|gif|svg|zip}',
 *                 'smliser-repository-assets'
 *             )
 *             ->name( 'repository.assets' );
 *     } );
 *
 *     $router->add( 'smliser-auth/v1/authorize', '', array( 'smliser_auth' => '1' ) );
 *
 *     add_action( 'init', array( $router, 'register' ) );
 *     add_filter( 'query_vars', array( $router, 'query_vars' ) );
 *
 *     $router->url( 'repository.assets', array(
 *         'app_type'       => 'plugins',
 *         'app_slug'       => 'smart-license-server',
 *         'asset_name'     => 'screenshot-1',
 *         'asset_name_ext' => 'png',
 *     ) );
 *     // => 'repository/plugins/smart-license-server/assets/screenshot-1.png'
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
	 * Groups routes under a shared path prefix. Nestable — nested calls
	 * accumulate their prefixes in order.
	 *
	 * @param callable(self): void $callback Receives this Router so nested
	 *                                       add()/group() calls read naturally.
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
	 * @param string               $pattern               Pattern, relative to any enclosing group(s).
	 * @param string               $pagename              Value for the `pagename` query var, or '' to
	 *                                                     omit it (e.g. for non-page endpoints like OAuth).
	 * @param array<string,string> $extraVars             Fixed query vars not derived from the pattern.
	 * @param string               $priority              'top' or 'bottom'.
	 * @param bool                 $optionalTrailingSlash Whether the URL may end in an optional '/'.
	 *                                                     Default true; pass false for an exact match.
	 * @return Route The registered route — chain ->name() on it if you'll need Router::url() later.
	 * @throws InvalidRouteException On a malformed pattern or invariant violation.
	 */
	public function add(
		string $pattern,
		string $pagename = '',
		array $extraVars = array(),
		string $priority = 'top',
		bool $optionalTrailingSlash = true
	): Route {
		$this->assertNoReservedCollision( $extraVars );

		$fullPattern = $this->applyGroupPrefix( $pattern );
		$compiled    = RoutePattern::compile( $fullPattern, $this->constraints );

		$route = Route::compiled(
			$fullPattern,
			$pagename,
			$extraVars,
			RoutePriority::fromString( $priority ),
			$optionalTrailingSlash,
			$compiled
		);

		$this->routes->add( $route );

		return $route;
	}

	/**
	 * Escape hatch for a rule the placeholder DSL cannot express — registers a
	 * hand-written regex/query pair verbatim, exactly as add_rewrite_rule()
	 * would, but still tracked alongside compiled routes (visible via
	 * getCompiledRules(), included in query_vars()).
	 *
	 * Reach for this only when a rule genuinely needs something the DSL
	 * doesn't support — e.g. a capturing group whose optional literal suffix
	 * (like an optional ".zip") must be excluded from the captured value
	 * itself. That's a different thing from an optional *segment*: `{name?}`
	 * omits the whole segment, it can't leave a required capture in place
	 * while making only a trailing suffix of it optional and uncaptured.
	 * Faking that through `{name.ext}` would either capture the suffix into
	 * the value (changing behavior) or drop the disambiguating regex you
	 * actually need — so express it directly instead of forcing a fit.
	 *
	 * @param string   $regex         Full anchored regex, e.g. '^foo/([^/]+)$'.
	 * @param string   $query         Full query string, e.g. 'index.php?foo=$matches[1]'.
	 * @param string   $priority      'top' or 'bottom'.
	 * @param string[] $queryVarNames Query var names this rule populates, so query_vars()
	 *                                still registers them with WordPress.
	 * @return Route The registered route. Note: Route::name() is not supported on raw
	 *               routes, since there's no pattern template to render a URL from.
	 */
	public function raw( string $regex, string $query, string $priority = 'top', array $queryVarNames = array() ): Route {
		$route = Route::raw( $regex, $query, RoutePriority::fromString( $priority ), $queryVarNames );

		$this->routes->add( $route );

		return $route;
	}

	/**
	 * Registers all compiled routes with WordPress. Hook to `init`.
	 */
	public function register(): void {
		foreach ( $this->routes->all() as $route ) {
			$rule = $route->toRewriteRule();

			add_rewrite_rule( $rule['regex'], $rule['query'], $rule['priority'] );
		}
	}

	/**
	 * Returns the query vars every registered route needs, merged with
	 * WordPress' existing list. Hook to the `query_vars` filter.
	 *
	 * @param string[] $vars
	 * @return string[]
	 */
	public function query_vars( array $vars = [] ): array {
		foreach ( $this->routes->all() as $route ) {
			$vars = array_merge( $vars, $route->queryVarNames() );
		}

		return array_values( array_unique( $vars ) );
	}

	/**
	 * Builds a relative URL path for a named route.
	 *
	 * @param string               $name   Name given via Route::name().
	 * @param array<string,scalar> $params Parameter values keyed by name — for a `{foo.ext}`
	 *                                     placeholder, supply both `foo` and `foo_ext`.
	 * @return string Relative URL path (no leading slash, no site URL prepended).
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
	 * Mainly for debugging/inspection — e.g. a WP-CLI command that dumps
	 * every compiled rule, since they're generated rather than hand-visible.
	 *
	 * @return array<int, array{regex: string, query: string, priority: string}>
	 */
	public function getCompiledRules(): array {
		return array_map( static fn( Route $route ) => $route->toRewriteRule(), $this->routes->all() );
	}

	private function applyGroupPrefix( string $pattern ): string {
		$prefix  = implode( '/', array_filter( $this->groupStack, static fn( string $p ) => '' !== $p ) );
		$pattern = trim( $pattern, '/' );

		if ( '' === $prefix ) {
			return $pattern;
		}

		return '' === $pattern ? $prefix : $prefix . '/' . $pattern;
	}

	/**
	 * @param array<string,string> $extraVars
	 * @throws InvalidRouteException
	 */
	private function assertNoReservedCollision( array $extraVars ): void {
		foreach ( array_keys( $extraVars ) as $name ) {
			if ( in_array( $name, RoutePattern::reservedQueryVars(), true ) ) {
				throw new InvalidRouteException(
					sprintf( '"%s" is a reserved query variable and cannot be set via $extraVars.', $name )
				);
			}
		}
	}
}