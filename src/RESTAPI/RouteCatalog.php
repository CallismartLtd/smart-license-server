<?php
/**
 * REST route catalog class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 */

namespace SmartLicenseServer\RESTAPI;

/**
 * Builds a canonical, environment-agnostic description of the routes exposed
 * by a single RESTVersionInterface implementation.
 *
 * This is the single source of truth for anything that needs to *describe*
 * a REST API version rather than dispatch it: REST index endpoints, HTML
 * documentation pages, and HTTP OPTIONS responses. It never exposes the
 * `handler`/`guard` callables from a route's raw configuration — only data
 * that is safe to serialize back to a client.
 *
 * @version 1.0
 */
class RouteCatalog {

	/**
	 * The REST API version this catalog describes.
	 *
	 * @var RESTVersionInterface
	 */
	private RESTVersionInterface $version;

	/**
	 * Routes grouped by their raw DSL pattern, keyed by that pattern.
	 *
	 * Built once in the constructor from RESTVersionInterface::get_routes()
	 * and reused by every query method below.
	 *
	 * @var array<string, array{
	 *     route: string,
	 *     humanized_route: string,
	 *     category: string|null,
	 *     methods: array<string, array{
	 *         name: string,
	 *         args: array<int, array{
	 *             name: string,
	 *             type: string,
	 *             required: bool,
	 *             description: string,
	 *             default: mixed
	 *         }>
	 *     }>
	 * }>
	 */
	private array $grouped;

	/**
	 * Constructor.
	 *
	 * @param RESTVersionInterface $version The REST API version to describe.
	 */
	public function __construct( RESTVersionInterface $version ) {
		$this->version = $version;
		$this->grouped = $this->build_grouped_routes();
	}

	/**
	 * List every available route, grouped by route pattern.
	 *
	 * Intended for REST index endpoints and HTML documentation pages — one
	 * entry per distinct route, with all of its supported HTTP methods
	 * nested underneath.
	 *
	 * @return array<int, array> Canonical route descriptors. See $grouped.
	 */
	public function list_routes() : array {
		return array_values( $this->grouped );
	}

	/**
	 * List routes belonging to a single category.
	 *
	 * @param string $category The category to filter by (e.g. 'license', 'repository').
	 * @return array<int, array> Canonical route descriptors for that category.
	 */
	public function list_by_category( string $category ) : array {
		return array_values(
			array_filter(
				$this->grouped,
				static fn( array $route ) => $route['category'] === $category
			)
		);
	}

	/**
	 * Categories actually present across this version's routes.
	 *
	 * Derived directly from route configuration rather than maintained as a
	 * separate hardcoded list, so it can never drift out of sync with what
	 * the routes actually declare.
	 *
	 * @return string[] Sorted, de-duplicated category keys.
	 */
	public function categories() : array {
		$categories = array_unique( array_filter( array_column( $this->grouped, 'category' ) ) );

		sort( $categories );

		return array_values( $categories );
	}

	/**
	 * Describe a single route for an HTTP OPTIONS response.
	 *
	 * @param string $route The route's raw DSL pattern, exactly as it appears
	 *                       in RESTVersionInterface::get_routes() — i.e. the
	 *                       identifier the environment's dispatcher matched
	 *                       against to reach this route in the first place.
	 * @return array{
	 *     route: string,
	 *     humanized_route: string,
	 *     category: string|null,
	 *     allowed_methods: string[],
	 *     methods: array<string, array{name: string, args: array}>
	 * }|null Null when no route matches the given pattern.
	 */
	public function options_for_route( string $route ) : ?array {
		if ( ! isset( $this->grouped[ $route ] ) ) {
			return null;
		}

		$descriptor = $this->grouped[ $route ];

		return array(
			'route'           => $descriptor['route'],
			'humanized_route' => $descriptor['humanized_route'],
			'category'        => $descriptor['category'],
			'allowed_methods' => array_keys( $descriptor['methods'] ),
			'methods'         => $descriptor['methods'],
		);
	}

	/**
	 * Build the `Allow` header value for a route, for direct use in an HTTP
	 * OPTIONS or 405 Method Not Allowed response.
	 *
	 * @param string $route The route's raw DSL pattern.
	 * @return string|null Comma-separated method list, or null if the route is unknown.
	 */
	public function allow_header( string $route ) : ?string {
		$options = $this->options_for_route( $route );

		return null === $options ? null : implode( ', ', $options['allowed_methods'] );
	}

	/**
	 * Build the grouped-by-route descriptor array from raw route configuration.
	 *
	 * @return array Grouped routes, keyed by raw route pattern.
	 */
	private function build_grouped_routes() : array {
		$api_config = $this->version::get_routes();
		$namespace  = $api_config['namespace'];
		$grouped    = array();

		foreach ( $api_config['routes'] as $route_config ) {
			$route    = $route_config['route'];
			$methods  = is_array( $route_config['methods'] ) ? $route_config['methods'] : array( $route_config['methods'] );
			$args     = $this->format_args( $route_config['args'] ?? array() );
			$name     = $route_config['name'] ?? 'Unnamed Route';
			$category = $route_config['category'] ?? null;

			if ( ! isset( $grouped[ $route ] ) ) {
				$grouped[ $route ] = array(
					'route'           => $route,
					'humanized_route' => self::humanize_route( $namespace . '/' . $route ),
					'category'        => $category,
					'methods'         => array(),
				);
			}

			foreach ( $methods as $method ) {
				$grouped[ $route ]['methods'][ strtoupper( trim( $method ) ) ] = array(
					'name' => $name,
					'args' => $args,
				);
			}
		}

		return $grouped;
	}

	/**
	 * Normalize a route's raw `args` configuration into a flat, serializable list.
	 *
	 * @param array $args Raw args configuration keyed by argument name.
	 * @return array<int, array{name: string, type: string, required: bool, description: string, default: mixed}>
	 */
	private function format_args( array $args ) : array {
		$formatted = array();

		foreach ( $args as $arg_name => $arg_config ) {
			$formatted[] = array(
				'name'        => $arg_name,
				'type'        => $arg_config['type'] ?? 'mixed',
				'required'    => ! empty( $arg_config['required'] ),
				'description' => $arg_config['description'] ?? 'No description',
				'default'     => $arg_config['default'] ?? null,
			);
		}

		return $formatted;
	}

	/**
	 * Strip the DSL's `.ext`/`?`/`:constraint` decoration from every
	 * placeholder in a route string for display, and swap underscores for
	 * hyphens so param names read as "{app-type}" rather than "{app_type}".
	 *
	 * @param string $route
	 * @return string
	 */
	private static function humanize_route( string $route ) : string {
		return (string) preg_replace_callback(
			'/\{([a-zA-Z_][a-zA-Z0-9_]*)(?:\.ext)?\??(?::[^}]+)?\}/',
			static fn( array $m ) => '{' . str_replace( '_', '-', $m[1] ) . '}',
			$route
		);
	}
}