<?php
/**
 * RouteCollection class file.
 *
 * @package SmartLicenseServer\Environments\WordPress\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

/**
 * Holds every Route registered on a Router instance.
 *
 * Name lookup is a linear scan rather than a maintained index: naming
 * happens after add() returns the Route (`$router->add(...)->name('x')`),
 * so an eagerly-maintained name index would need to be updated retroactively
 * from Route::name() with no back-reference to the collection. A linear scan
 * over what is, in practice, a few dozen routes at most, avoids that
 * coupling for a cost that only matters if url() were called in a hot loop —
 * it isn't; it's called when building a handful of links per request.
 */
final class RouteCollection {

	/** @var Route[] */
	private array $routes = [];

	public function add( Route $route ): void {
		$this->routes[] = $route;
	}

	/**
	 * @return Route[]
	 */
	public function all(): array {
		return $this->routes;
	}

	public function find( string $name ): ?Route {
		foreach ( $this->routes as $route ) {
			if ( $route->getName() === $name ) {
				return $route;
			}
		}

		return null;
	}
}