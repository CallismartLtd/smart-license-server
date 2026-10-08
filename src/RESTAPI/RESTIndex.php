<?php
/**
 * REST index class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 */

declare( strict_types=1 );

namespace SmartLicenseServer\RESTAPI;

/**
 * Describes the REST API: the whole API, each version, each category and
 * each route (for OPTIONS), from the versions' RouteCatalogs.
 *
 * Environment-agnostic, so every REST provider serves the same descriptions;
 * a provider only supplies how a URL is built in its environment.
 */
final class RESTIndex {

	/**
	 * Versions, keyed by namespace.
	 *
	 * @var array<string, RESTVersionInterface>
	 */
	private array $versions = array();

	/**
	 * Route catalogs, keyed by namespace, built on first use.
	 *
	 * @var array<string, RouteCatalog>
	 */
	private array $catalogs = array();

	/**
	 * Constructor.
	 *
	 * @param RESTVersionInterface[]   $versions REST API versions.
	 * @param callable( string ): string $url    Absolute URL of a path under the API root, e.g. "smliser/v1".
	 */
	public function __construct( array $versions, private $url ) {
		foreach ( $versions as $version ) {
			$this->versions[ $version->namespace() ] = $version;
		}
	}

	/**
	 * The catalog of a version.
	 *
	 * @param string $namespace Version namespace, e.g. "v1".
	 * @return RouteCatalog|null Null for an unknown namespace.
	 */
	public function catalog( string $namespace ) : ?RouteCatalog {
		if ( ! isset( $this->versions[ $namespace ] ) ) {
			return null;
		}

		return $this->catalogs[ $namespace ] ??= new RouteCatalog( $this->versions[ $namespace ] );
	}

	/**
	 * Description of the whole API: every version, with its categories.
	 *
	 * @return array{name: string, url: string, namespaces: string[], versions: array<int, array{namespace: string, url: string, categories: string[]}>}
	 */
	public function api() : array {
		$versions = array();

		foreach ( array_keys( $this->versions ) as $namespace ) {
			$versions[] = array(
				'namespace'  => $namespace,
				'url'        => $this->url( $namespace ),
				'categories' => $this->catalog( $namespace )->categories(),
			);
		}

		return array(
			'name'       => \SMLISER_APP_NAME,
			'url'        => ( $this->url )( RESTProviderInterface::PREFIX ),
			'namespaces' => array_keys( $this->versions ),
			'versions'   => $versions,
		);
	}

	/**
	 * Description of one version: its routes, or one category's.
	 *
	 * @param string $namespace Version namespace.
	 * @param string $category  Category to narrow to; "" for every route.
	 * @return array|null Null for an unknown namespace or category.
	 */
	public function version( string $namespace, string $category = '' ) : ?array {
		$catalog = $this->catalog( $namespace );

		if ( null === $catalog ) {
			return null;
		}

		if ( '' === $category ) {
			return array(
				'namespace'  => $namespace,
				'url'        => $this->url( $namespace ),
				'categories' => $catalog->categories(),
				'routes'     => $catalog->list_routes(),
			);
		}

		if ( ! in_array( $category, $catalog->categories(), true ) ) {
			return null;
		}

		return array(
			'namespace' => $namespace,
			'category'  => $category,
			'routes'    => $catalog->list_by_category( $category ),
		);
	}

	/**
	 * Description of one route, for an OPTIONS response.
	 *
	 * @param string $namespace Version namespace.
	 * @param string $pattern   The route's raw DSL pattern.
	 * @return array|null Null for an unknown namespace or route.
	 */
	public function route( string $namespace, string $pattern ) : ?array {
		$options = $this->catalog( $namespace )?->options_for_route( $pattern );

		return null === $options ? null : array( 'namespace' => $namespace ) + $options;
	}

	/**
	 * The Allow header of a route, OPTIONS included.
	 *
	 * @param string $namespace Version namespace.
	 * @param string $pattern   The route's raw DSL pattern; "" for an index (GET only).
	 * @return string
	 */
	public function allow( string $namespace, string $pattern = '' ) : string {
		$methods = '' === $pattern ? 'GET' : (string) $this->catalog( $namespace )?->allow_header( $pattern );

		return '' === $methods ? 'OPTIONS' : $methods . ', OPTIONS';
	}

	/**
	 * Namespaces of the described versions.
	 *
	 * @return string[]
	 */
	public function namespaces() : array {
		return array_keys( $this->versions );
	}

	/**
	 * Absolute URL of a version's index.
	 *
	 * @param string $namespace Version namespace.
	 * @return string
	 */
	private function url( string $namespace ) : string {
		return ( $this->url )( RESTProviderInterface::PREFIX . '/' . $namespace );
	}
}