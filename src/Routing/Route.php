<?php
/**
 * Route class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * A single registered route: pattern, HTTP method(s), and a handler.
 *
 * This is the environment-agnostic counterpart to
 * SmartLicenseServer\Environments\WordPress\Routing\Route, which carries
 * `pagename`/`extraVars`/`priority` — WordPress-rewrite-rule concepts this
 * class has no reason to know about. This one exists for environments (like
 * a standalone PHP app) that dispatch by (method, path) directly rather than
 * through WordPress' query-var indirection.
 */
final class Route {

	private ?string $name = null;

	/**
	 * @param string[] $methods Uppercase HTTP methods this route responds to.
	 */
	public function __construct(
		public readonly string $pattern,
		public readonly array $methods,
		public readonly mixed $handler,
		public readonly bool $optionalTrailingSlash,
		public readonly CompiledPattern $compiled
	) {
	}

	/**
	 * Attaches a name to this route for later lookup via Router::url().
	 *
	 * @throws InvalidRouteException If the route is already named.
	 */
	public function name( string $name ): self {
		if ( null !== $this->name ) {
			throw new InvalidRouteException(
				sprintf( 'Route already named "%s"; cannot rename to "%s".', $this->name, $name )
			);
		}

		$this->name = $name;

		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	/**
	 * Tests a request path against this route's compiled regex (via its
	 * named-group form, since dispatch reads params by name) and returns the
	 * named params if it matches, regardless of HTTP method — method
	 * matching is Router::dispatch()'s job, not this method's, so that a
	 * path match with a method mismatch can be reported as 405 rather than
	 * a plain miss.
	 *
	 * @return array<string,string>|null Named params, or null if the path doesn't match.
	 */
	public function match( string $path ): ?array {
		$regex = $this->compiled->namedRegex() . ( $this->optionalTrailingSlash ? '/?' : '' );

		if ( ! preg_match( '#^' . $regex . '$#', trim( $path, '/' ), $matches ) ) {
			return null;
		}

		$params = array();

		foreach ( $this->compiled->paramNames as $paramName ) {
			if ( isset( $matches[ $paramName ] ) ) {
				$params[ $paramName ] = $matches[ $paramName ];
			}
		}

		return $params;
	}
}