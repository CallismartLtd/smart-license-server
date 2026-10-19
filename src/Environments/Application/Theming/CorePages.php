<?php
/**
 * Core pages class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\RESTAPI\RouteCatalog;

/**
 * Page handlers of the app's own public pages.
 *
 * Each returns a View and leaves the look to the theme. The data each page
 * template receives is listed on its method; it is the contract every
 * theme's page template can rely on.
 */
final class CorePages {

	/**
	 * Constructor.
	 *
	 * @param RESTProviderInterface $rest_provider The REST API, for the documentation page.
	 */
	public function __construct( private RESTProviderInterface $rest_provider ) {}

	/**
	 * GET / — the home page.
	 *
	 * Template 'home'. Data:
	 *   string $app_version The running app version.
	 *   string $php_version The running PHP version.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function home( Request $request ) : View {
		return View::make(
			'home',
			[
				'app_version' => \SMLISER_VER,
				'php_version' => \PHP_VERSION,
			]
		)->set_description( 'A database-agnostic application for privately hosting, licensing, updating, and distributing custom software, WordPress plugins, and themes.' );
	}

	/**
	 * GET /documentation/ and /documentation/{category}/ — the REST API reference.
	 *
	 * Template 'documentation'. Data:
	 *   array<string, string> $categories Every category across the versions:
	 *                                     key => label, sorted by key.
	 *   string|null           $category   The category shown; null for all.
	 *   array<string, array<string, array<int, array>>> $versions
	 *                                     Version namespace => category key =>
	 *                                     route descriptors (RouteCatalog), with
	 *                                     only the shown category when one is.
	 *                                     Versions without routes to show are left out.
	 *
	 * An unknown category is a 404.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function documentation( Request $request ) : View {
		$category = $request->route_param( 'category' );
		$category = is_string( $category ) && '' !== $category ? $category : null;

		$catalogs = [];

		foreach ( $this->rest_provider->version_instances() as $version ) {
			$catalogs[ $version->namespace() ] = new RouteCatalog( $version );
		}

		$categories = [];

		foreach ( $catalogs as $catalog ) {
			foreach ( $catalog->categories() as $key ) {
				$categories[ $key ] = self::category_label( $key );
			}
		}

		ksort( $categories );

		if ( null !== $category && ! isset( $categories[ $category ] ) ) {
			return $this->not_found( $request );
		}

		$versions = [];

		foreach ( $catalogs as $namespace => $catalog ) {
			foreach ( null === $category ? $catalog->categories() : [ $category ] as $key ) {
				$routes = $catalog->list_by_category( $key );

				if ( [] !== $routes ) {
					$versions[ (string) $namespace ][ $key ] = $routes;
				}
			}
		}

		$view = View::make(
			'documentation',
			[
				'categories' => $categories,
				'category'   => $category,
				'versions'   => $versions,
			]
		);

		return null === $category
			? $view->set_title( 'API Documentation' )->set_description( 'Available REST API routes, grouped by version and category.' )
			: $view->set_title( sprintf( 'API Documentation: %s', $categories[ $category ] ) )->set_description( sprintf( 'REST API routes in the "%s" category.', $categories[ $category ] ) );
	}

	/**
	 * No route matches the path.
	 *
	 * Template 'errors.404', status 404. Data:
	 *   string $path The requested path.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function not_found( Request $request ) : View {
		return View::make( 'errors.404', [ 'path' => $request->path() ], 404 )
			->set_title( 'Page not found' );
	}

	/**
	 * A route matches the path, but not the method.
	 *
	 * Template 'errors.405', status 405, with the Allow header. Data:
	 *   string   $method  The request method.
	 *   string[] $allowed The methods the path accepts.
	 *
	 * @param Request  $request
	 * @param string[] $allowed
	 * @return View
	 */
	public function method_not_allowed( Request $request, array $allowed ) : View {
		return View::make( 'errors.405', [ 'method' => $request->method(), 'allowed' => $allowed ], 405 )
			->set_title( 'Method not allowed' )
			->set_header( 'Allow', implode( ', ', $allowed ) );
	}

	/**
	 * A display label for a category key: "bulk-messages" → "Bulk Messages".
	 *
	 * @param string $key
	 * @return string
	 */
	public static function category_label( string $key ) : string {
		return ucwords( str_replace( [ '-', '_' ], ' ', $key ) );
	}
}