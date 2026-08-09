<?php
/**
 * Router class file.
 *
 * @package SmartLicenseServer\Environments\WordPress\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

use SmartLicenseServer\Routing\CompiledPattern;
use SmartLicenseServer\Routing\InvalidRouteException;
use SmartLicenseServer\Routing\RoutePattern;

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
	 * @param mixed                $handler               Optional handler for this specific route —
	 *                                                     see Router::match()'s docblock for why this
	 *                                                     matters even though pagename still does the
	 *                                                     actual WordPress-level rewrite-rule registration.
	 * @return Route The registered route — chain ->name() on it if you'll need Router::url() later.
	 * @throws InvalidRouteException On a malformed pattern or invariant violation.
	 */
	public function add(
		string $pattern,
		string $pagename = '',
		array $extraVars = array(),
		string $priority = 'top',
		bool $optionalTrailingSlash = true,
		mixed $handler = null
	): Route {
		$fullPattern = $this->applyGroupPrefix( $pattern );
		$compiled    = RoutePattern::compile( $fullPattern, $this->constraints );

		$this->assertNoReservedCollision( $extraVars, $compiled );

		$route = Route::compiled(
			$fullPattern,
			$pagename,
			$extraVars,
			RoutePriority::fromString( $priority ),
			$optionalTrailingSlash,
			$compiled,
			$handler
		);

		$this->routes->add( $route );

		return $route;
	}

	/**
	 * Resolves a live request path directly to whichever registered route
	 * actually matches it, with named params — without going through
	 * WordPress' query vars at all.
	 *
	 * Multiple routes commonly share one `pagename` on purpose (several URL
	 * shapes rendering "the same page," refined further by which query vars
	 * happen to be present) — that's a legitimate WordPress convention and
	 * this doesn't change it. But when routes registered under one pagename
	 * are actually *distinct cases* — a license-document download, a zip
	 * download, an artifact download — the pagename alone can't tell them
	 * apart, so something ends up re-inspecting query vars at runtime to
	 * figure out which case fired. match() skips that: it re-runs each
	 * route's own compiled regex — the same one used to build its rewrite
	 * rule — directly against the path, so the route that matches IS the
	 * answer to "which case is this," with its $handler (if one was given
	 * to add()) and named params returned together. No downstream
	 * re-inspection needed for routes that were given a distinct handler.
	 *
	 * SAFETY: do not call this as your first/only check for "does this
	 * request belong to me." It re-parses the raw path against whatever
	 * patterns *this PHP request* currently has compiled — not against
	 * WordPress' actual live rewrite rules, which can legitimately lag
	 * behind (stale rewrite cache after a prefix change, permalinks not yet
	 * flushed, another plugin's rule winning first). get_query_var('pagename')
	 * reflects what WordPress itself actually resolved with its actual
	 * active rules, inheriting all of WordPress' own collision handling
	 * against real pages/posts; match() has none of that. Always confirm
	 * `pagename` first — see matchForPagename(), which enforces this by
	 * construction rather than relying on the caller to remember it.
	 *
	 * @return array{route: Route, handler: mixed, params: array<string,string>}|null
	 */
	public function match( string $path ): ?array {
		foreach ( $this->routes->all() as $route ) {
			$params = $route->match( $path );

			if ( null !== $params ) {
				return array( 'route' => $route, 'handler' => $route->handler, 'params' => $params );
			}
		}

		return null;
	}

	/**
	 * The safe way to call match(): only ever searches routes registered
	 * under the given pagename, so it can only be used to disambiguate
	 * *among* routes WordPress already agreed belong to you — never to
	 * decide, on its own, whether a request belongs to you at all. Call
	 * this only after confirming get_query_var('pagename') already equals
	 * $pagename; see match()'s docblock for why that order matters.
	 *
	 * @return array{route: Route, handler: mixed, params: array<string,string>}|null
	 */
	public function matchForPagename( string $pagename, string $path ): ?array {
		foreach ( $this->routes->all() as $route ) {
			if ( $route->pagename !== $pagename ) {
				continue;
			}

			$params = $route->match( $path );

			if ( null !== $params ) {
				return array( 'route' => $route, 'handler' => $route->handler, 'params' => $params );
			}
		}

		return null;
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
	public function query_vars( array $vars ): array {
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
	 * WordPress core query variables a route parameter or extra var must not
	 * shadow. This lived inside the core RoutePattern compiler before — moved
	 * here because "reserved" is only true relative to WordPress specifically;
	 * a standalone environment has no reason to reject a param literally named
	 * `page`, and forcing that restriction into the environment-agnostic
	 * compiler meant every environment paid for a WordPress-only concern.
	 *
	 * @var string[]
	 */
	private const RESERVED_QUERY_VARS = array(
		'page',
		'paged',
		'p',
		'name',
		'author',
		'category_name',
		'tag',
		'feed',
		'attachment_id',
		'preview',
		's',
		'pagename',
	);

	/**
	 * @param array<string,string> $extraVars
	 * @throws InvalidRouteException
	 */
	private function assertNoReservedCollision( array $extraVars, CompiledPattern $compiled ): void {
		$candidates = array_merge( array_keys( $extraVars ), $compiled->paramNames );

		foreach ( $candidates as $name ) {
			if ( in_array( $name, self::RESERVED_QUERY_VARS, true ) ) {
				throw new InvalidRouteException(
					sprintf( '"%s" is a reserved WordPress query variable and cannot be used as a route parameter or extra var.', $name )
				);
			}
		}
	}
}