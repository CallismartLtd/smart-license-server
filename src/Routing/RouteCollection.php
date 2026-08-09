<?php
/**
 * RouteCollection class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * Holds every Route registered on a Router instance.
 *
 * Name lookup is a linear scan rather than a maintained index — naming
 * happens after add() returns the Route (`$router->add(...)->name('x')`),
 * so an eagerly-maintained index would need updating retroactively from
 * Route::name() with no back-reference to the collection. A scan over what
 * is, in practice, a modest route table costs nothing that matters; url()
 * is called per link generated, not per request.
 */
final class RouteCollection {

	/** @var Route[] */
	private array $routes = array();

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