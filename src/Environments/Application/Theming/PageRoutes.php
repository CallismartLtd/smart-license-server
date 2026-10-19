<?php
/**
 * Page routes class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\Routing\Route;
use SmartLicenseServer\Routing\Router;

/**
 * Registers themed pages on the router.
 *
 * Philosophy:
 *
 *  - Themes render pages, never routes. Only routes registered here are
 *    themed: the public pages of the site. Admin pages, REST endpoints,
 *    file and asset endpoints, and routes an extension registers on the
 *    router itself return their own Response, and the theme layer is never
 *    built for them: no theme code runs and no theme asset loads.
 *  - Declared, not guessed. A page is registered as a page, so the
 *    dispatcher knows before calling the handler that it must return a View.
 *  - A page is something viewed: GET (and HEAD). Forms post to action
 *    routes, which return a Response (a redirect, or JSON).
 *
 * The routes go into the same router, so groups, prefixes and middleware
 * apply as usual; call get() inside Router::group() like Router::get().
 */
final class PageRoutes {

	/**
	 * Constructor.
	 *
	 * @param Router $router The application router.
	 */
	public function __construct( private Router $router ) {}

	/**
	 * Register a page.
	 *
	 * The handler receives the Request and returns a View. Middleware runs
	 * as on any route and may answer with a Response instead (a redirect to
	 * sign in, say); on a page route, $next() returns the View.
	 *
	 * @param string           $pattern    Pattern, relative to any enclosing group.
	 * @param mixed            $handler    Page handler, in any form CallableResolver accepts.
	 * @param array<int,mixed> $middleware Route middleware.
	 * @return Route
	 */
	public function get( string $pattern, mixed $handler, array $middleware = [] ) : Route {
		return $this->router->get( $pattern, new PageHandler( $handler ), true, $middleware );
	}

	/**
	 * Whether a route is a page.
	 *
	 * @param Route $route
	 * @return bool
	 */
	public static function is_page( Route $route ) : bool {
		return $route->handler instanceof PageHandler;
	}
}