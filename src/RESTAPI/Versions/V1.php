<?php
/**
 * REST API version 1 class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 * @version 1
 */

namespace SmartLicenseServer\RESTAPI\Versions;

use SmartLicenseServer\ClientDashboard\ClientDashboardRouter;
use SmartLicenseServer\RESTAPI\Handlers\BulkMessages;
use SmartLicenseServer\RESTAPI\Handlers\HostedApps;
use SmartLicenseServer\RESTAPI\Handlers\Licenses;
use SmartLicenseServer\RESTAPI\Handlers\Plugins;
use SmartLicenseServer\RESTAPI\Handlers\Themes;
use SmartLicenseServer\RESTAPI\RESTVersionInterface;

/**
 * API version 1
 * 
 * Environment-agnostic REST API route definitions.
 * Call get_routes() to retrieve all route configurations for registration.
 *
 * Route patterns use the shared SmartLicenseServer\Routing\RoutePattern DSL
 * ({name}, {name:constraint}, {name:alias}) rather than raw PCRE named-capture
 * regex — the same syntax the WordPress pretty-URL router and the standalone
 * environment's RouteManager both compile. Whichever environment registers
 * these routes is responsible for compiling `route` into whatever its own
 * dispatch mechanism expects (e.g. RESTAPI::register_rest_routes() compiles
 * to PCRE named groups for register_rest_route()); this class carries no
 * knowledge of that translation.
 *
 * Route description (available routes, categories, per-route OPTIONS data)
 * is handled by SmartLicenseServer\RESTAPI\RouteCatalog, which consumes
 * get_routes() rather than this class carrying its own presentation logic.
 * 
 * @version 1.0
 */
class V1 implements RESTVersionInterface {
	/**
	 * App type + slug route pattern.
	 * 
	 * @var string
	 */
	private const APP_ROUTE_PATTERN = '{app_type:slug}/{app_slug:slug}';

	/**
	 * App type route pattern, restricted to the three known app types.
	 */
	private const APP_TYPE_PATTERN = '{app_type:plugin|theme|software}';

	/**
	 * Asset type route pattern, restricted to the four known asset types
	 * (each optionally pluralized).
	 */
	private const ASSET_TYPE_PATTERN = '{asset_type:(?:cover|icon|banner|screenshot)s?}';

	/**
	 * Asset name route pattern.
	 */
	private const ASSET_NAME_PATTERN = '{asset_name:slug}';

	/** 
	 * REST API Route namespace.
	 * 
	 * @var string
	 */
	private const NAMESPACE = 'smliser/v1';

	/** 
	 * Plugin info REST API route.
	 * 
	 * @var string
	 */
	private const PLUGIN_INFO = 'plugin-info';
	
	/** 
	 * Plugin info route.
	 * 
	 * @var string
	 */
	private const THEME_INFO = 'theme-info';

	/** 
	 * License activation route.
	 * 
	 * @var string
	 */
	private const LICENSE_ACTIVATION = 'license-activation/' . self::APP_ROUTE_PATTERN;

	/** 
	 * License deactivation route
	 * 
	 * @var string
	 */
	private const LICENSE_DEACTIVATION = 'license-deactivation';

	/**
	 * License uninstallation route.
	 * 
	 * @var string
	 */
	private const LICENSE_UNINSTALL = 'license-uninstallation';

	/**
	 * License validity route.
	 * 
	 * @var string
	 */
	private const LICENSE_VALIDITY = 'license-validity-test/' . self::APP_ROUTE_PATTERN;

	/** 
	 * Route to query the entire repository.
	 * 
	 * @var string
	 */
	private const REPOSITORY_ROUTE = 'repository';

	/** 
	 * Route to perform CRUD operations on an application.
	 * @example ```GET
	 *  /repository/plugin/woocommerce
	 * ```
	 * 
	 * @var string
	 */
	private const REPOSITORY_APP_TYPE_ROUTE = 'repository/' . self::APP_TYPE_PATTERN;

	/** 
	 * Route to perform CRUD operations on an application.
	 * @example ```GET
	 *  /repository/plugin/woocommerce
	 * ```
	 * 
	 * @var string
	 */
	private const REPOSITORY_APP_ROUTE = 'repository/' . self::APP_ROUTE_PATTERN;

	/** 
	 * Route to  operations on an applications' assets.
	 * @example ```POST
	 *  /repository/plugin/smart-woo-pro/assets/
	 * ```
	 * 
	 * @var string
	 */
	const APP_ASSETS_ROUTE_BASE = 'repository/' . self::APP_ROUTE_PATTERN . '/assets';
	/** 
	 * Route to  operations on an applications' assets.
	 * @example ```POST
	 *  /repository/plugin/smart-woo-pro/assets/banners
	 * ```
	 * 
	 * @var string
	 */
	const APP_ASSETS_TYPE_ROUTE = self::APP_ASSETS_ROUTE_BASE . '/' . self::ASSET_TYPE_PATTERN;

	/**
	 * Route to a single application asset file.
	 * 
	 * @example `PUT`
	 * /respository/theme/astra/assets/screenshots/screenshot
	 */
	const APP_ASSET_TYPE_ROUTE  = self::APP_ASSETS_TYPE_ROUTE . '/' . self::ASSET_NAME_PATTERN;


	/**
	 * Download token regeneration REST API route
	 * 
	 * @var string
	 */
	private const DOWNLOAD_TOKEN_REAUTH = 'download-token-reauthentication/' . self::APP_ROUTE_PATTERN;

	/**
	 * REST endpoint for bulk messages fetching.
	 * 
	 * @var string
	 */
	private const BULK_MESSAGES_ROUTE = 'bulk-messages';

	/**
	 * Client dashboard content route.
	 * Slug is a path segment: /dashboard/{slug}
	 */
	private const CLIENT_DASHBOARD_ROUTE = 'client-dashboard/{dashboard_slug:slug}';
	
	/**
	 * Client dashboard content route.
	 * Slug is a path segment: /dashboard/{slug}
	 */
	private const CLIENT_POST_ROUTE = 'client-dashboard/{post_action:slug}';

	/**
	 * Get all route definitions for the API.
	 *
	 * Returns a structured array of route configurations that can be consumed
	 * and registered by any supported environment implementation
	 * (WordPress, Laravel, custom PHP router, etc.).
	 *
	 * Each route definition contains the following keys:
	 *
	 * - namespace (string)
	 *      The base namespace under which all routes are grouped.
	 *      Example: "smart-license-server/v1"
	 *
	 * - routes (array)
	 *      A list of individual route configuration arrays.
	 *
	 * Each route configuration supports the following keys:
	 *
	 * - route (string)
	 *      The URI pattern relative to the namespace, written in the shared
	 *      RoutePattern DSL: {name}, {name:constraint}, {name:alias}.
	 *      Example: "activate" or "repository/{id:int}"
	 *
	 * - methods (string|array)
	 *      Allowed HTTP methods for the route.
	 *      Can be a string (e.g. "GET") or an array of methods
	 *      (e.g. ["POST", "PUT"]).
	 *
	 * - handler (callable)
	 *      The main route handler callback that processes the request
	 *      and returns a Response object or compatible result.
	 *      Receives an instance of SmartLicenseServer\Core\Request.
	 *
	 * - guard (callable)
	 *      Authorization callback executed before the handler.
	 *      Determines whether the request is permitted to proceed.
	 *      Must return true to allow execution of the handler,
	 *      or an error result to reject the request.
	 *
	 * - args (array)
	 *      Definition of expected route parameters including type,
	 *      validation rules, and sanitization callbacks.
	 *      Used by environment implementations to validate input.
	 *
	 * - category (string)
	 *      Logical grouping identifier for the route, such as
	 *      "license", "repository", or "testing".
	 *      Used for internal organization and documentation purposes.
	 *
	 * - name (string)
	 *      Human-readable descriptive name for the route.
	 *      Useful for logging, debugging, or UI display.
	 *
	 * @return array{
	 *     namespace: string,
	 *     routes: array<int, array{
	 *         route: string,
	 *         methods: string|array<int, string>,
	 *         handler: callable,
	 *         guard?: callable,
	 *         args?: array<string, array{
	 *             required?: bool,
	 *             type?: string,
	 *             description?: string,
	 *             default?: mixed
	 *         }>,
	 *         category?: string,
	 *         name?: string
	 *     }>
	 * }
	 */
	public static function get_routes() : array {
		return array(
			'namespace' => self::NAMESPACE,
			'routes'    => array(
				// License Activation Route
				array(
					'route'         => self::LICENSE_ACTIVATION,
					'methods'       => ['POST'],
					'handler'       => array( Licenses::class, 'activation_response' ),
					'guard'         => array( Licenses::class, 'activation_permission_callback' ),
					'args'          => self::get_license_activation_args(),
					'category'      => 'license',
					'name'          => 'License Activation',
				),

				// License Deactivation Route
				array(
					'route'         => self::LICENSE_DEACTIVATION,
					'methods'       => array( 'POST' ),
					'handler'       => array( Licenses::class, 'deactivation_response' ),
					'guard'         => array( Licenses::class, 'deactivation_permission' ),
					'args'          => self::get_license_deactivation_args(),
					'category'      => 'license',
					'name'          => 'License Deactivation',
				),

				// License Uninstallation Route.
				array(
					'route'         => self::LICENSE_UNINSTALL,
					'methods'       => array( 'POST' ),
					'handler'       => array( Licenses::class, 'uninstallation_response' ),
					'guard'         => array( Licenses::class, 'uninstallation_permission' ),
					'args'          => self::get_license_uninstallation_args(),
					'category'      => 'license',
					'name'          => 'License Uninstallation',
				),

				// License Validity Test Route.
				array(
					'route'         => self::LICENSE_VALIDITY,
					'methods'       => ['POST'],
					'handler'       => array( Licenses::class, 'validity_test_response' ),
					'guard'         => array( Licenses::class, 'validity_test_permission' ),
					'args'          => self::get_license_validity_args(),
					'category'      => 'license',
					'name'          => 'License Validity Test',
				),

				// Plugin Info Route.
				array(
					'route'         => self::PLUGIN_INFO,
					'methods'       => ['GET'],
					'handler'       => array( Plugins::class, 'plugin_info_response' ),
					'guard'         => array( Plugins::class, 'info_permission_callback' ),
					'args'          => self::get_app_info_args( 'plugin' ),
					'category'      => 'repository',
					'name'          => 'Plugin Information',
				),

				// Theme Info Route.
				array(
					'route'         => self::THEME_INFO,
					'methods'       => ['GET'],
					'handler'       => array( Themes::class, 'theme_info_response' ),
					'guard'         => array( Themes::class, 'info_permission_callback' ),
					'args'          => self::get_app_info_args( 'theme' ),
					'category'      => 'repository',
					'name'          => 'Theme Information',
				),

				// Repository Route.
				array(
					'route'         => self::REPOSITORY_ROUTE,
					'methods'       => ['GET'],
					'handler'       => array( HostedApps::class, 'repository_response' ),
					'guard'         => array( HostedApps::class, 'repository_get_guard' ),
					'args'          => self::get_repository_args(),
					'category'      => 'repository',
					'name'          => 'Repository Query',
				),

				// Repository App Route (CRUD).
				array(
					'route'         => self::REPOSITORY_APP_ROUTE,
					'methods'       => ['GET'],
					'handler'       => array( HostedApps::class, 'single_app_get' ),
					'guard'         => array( HostedApps::class, 'repository_get_guard' ),
					'args'          => self::get_repository_app_args(),
					'category'      => 'repository',
					'name'          => 'Get Single Application',
				),
				
				array(
					'route'         => self::REPOSITORY_APP_TYPE_ROUTE,
					'methods'       => ['POST'],
					'handler'       => array( HostedApps::class, 'create_app' ),
					'guard'         => array( HostedApps::class, 'repository_unsafe_method_guard' ),
					'args'          => self::get_app_write_args( 'POST' ),
					'category'      => 'repository',
					'name'          => 'Create a New Application',
				),

				array(
					'route'         => self::REPOSITORY_APP_ROUTE,
					'methods'       => array( 'PUT', 'PATCH' ),
					'handler'       => array( HostedApps::class, 'update_app' ),
					'guard'         => array( HostedApps::class, 'repository_unsafe_method_guard' ),
					'args'          => self::get_app_write_args( 'PUT', 'PATCH' ),
					'category'      => 'repository',
					'name'          => 'Update an Existing Application',
				),

				array(
					'route'         => self::REPOSITORY_APP_ROUTE,
					'methods'       => array( 'DELETE' ),
					'handler'       => array( HostedApps::class, 'delete_app' ),
					'guard'         => array( HostedApps::class, 'repository_unsafe_method_guard' ),
					'args'          => self::get_app_delete_args(),
					'category'      => 'repository',
					'name'          => 'Delete Existing Application'
				),

				// App asset routes.
				array(
					'route'         => self::APP_ASSETS_TYPE_ROUTE,
					'methods'       => array( 'POST' ),
					'handler'       => array( HostedApps::class, 'upload_app_assets' ),
					'guard'         => array( HostedApps::class, 'assets_management_guard' ),
					'args'          => [],
					'category'      => 'repository',
					'name'          => 'App Asset Bulk Upload'
				),

				array(
					'route'         => self::APP_ASSET_TYPE_ROUTE,
					'methods'       => array( 'PUT' ),
					'handler'       => array( HostedApps::class, 'update_app_asset' ),
					'guard'         => array( HostedApps::class, 'assets_management_guard' ),
					'args'          => [],
					'category'      => 'repository',
					'name'          => 'Create or Replace a Single App Asset'
				),

				array(
					'route'         => self::APP_ASSETS_ROUTE_BASE . '/' . self::ASSET_NAME_PATTERN,
					'methods'       => array( 'DELETE' ),
					'handler'       => array( HostedApps::class, 'delete_app_asset' ),
					'guard'         => array( HostedApps::class, 'assets_management_guard' ),
					'args'          => [],
					'category'      => 'repository',
					'name'          => 'Create or Replace App Asset'
				),

				// Download Token Reauthentication Route
				array(
					'route'         => self::DOWNLOAD_TOKEN_REAUTH,
					'methods'       => ['POST'],
					'handler'       => array( Licenses::class, 'app_download_reauth' ),
					'guard'         => array( Licenses::class, 'download_reauth_permission' ),
					'args'          => self::get_download_reauth_args(),
					'category'      => 'license',
					'name'          => 'Download Token Reauthentication',
				),

				// Mock Inbox Route (for testing)
				array(
					'route'         => 'mock-inbox',
					'methods'       => ['GET'],
					'handler'       => array( BulkMessages::class, 'mock_dispatch' ),
					'guard'         => [__CLASS__, 'return_true'],
					'args'          => array(),
					'category'      => 'testing',
					'name'          => 'Mock Inbox (Testing)',
				),

				// Bulk Messages Route
				array(
					'route'         => self::BULK_MESSAGES_ROUTE,
					'methods'       => ['GET'],
					'handler'       => array( BulkMessages::class, 'dispatch_response' ),
					'guard'         => array( BulkMessages::class, 'permission_callback' ),
					'args'          => self::get_bulk_messages_args(),
					'category'      => 'bulk-messages',
					'name'          => 'Bulk Messages',
				),
				array(
					'route'    => self::CLIENT_DASHBOARD_ROUTE,
					'methods'  => [ 'GET' ],
					'handler'  => [ ClientDashboardRouter::class, 'dispatch' ],
					'guard'    => [ ClientDashboardRouter::class, 'guard' ],
					'args'     => [],
					'category' => 'client-dashboard',
					'name'     => 'Client Dashboard Content',
				),
				
				array(
					'route'    => self::CLIENT_POST_ROUTE,
					'methods'  => [ 'POST' ],
					'handler'  => [ ClientDashboardRouter::class, 'post_dispatch' ],
					'guard'    => [ ClientDashboardRouter::class, 'post_guard' ],
					'args'     => [],
					'category' => 'client-dashboard',
					'name'     => 'Client Dashboard Post',
				),
			),
		);
	}

	public function namespace() : string {
		return static::NAMESPACE;
	}

	/**
	 * Get license activation route arguments.
	 * 
	 * @return array
	 */
	private static function get_license_activation_args() {
		return array(
			'service_id' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The service id associated with the license key',
			),
			'license_key' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The license key to verify',
			),
			'domain' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The URL of the website where the license is being activated.',
			),
		);
	}

	/**
	 * Get license deactivation route arguments.
	 * 
	 * @return array
	 */
	private static function get_license_deactivation_args() {
		return array(
			'license_key' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The license key to deactivate.',
			),
			'service_id' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The service ID associated with the license.',
			),
			'domain' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The URL of the website where the license is currently activated.',
			),
		);
	}

	/**
	 * Get license uninstallation route arguments.
	 * 
	 * @return array
	 */
	private static function get_license_uninstallation_args() {
		return array(
			'license_key' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The license key to uninstall.',
			),
			'service_id' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The service ID associated with the license.',
			),
			'domain' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The URL of the website where the license is currently activated.',
			),
		);
	}

	/**
	 * Get license validity test route arguments.
	 * 
	 * @return array
	 */
	private static function get_license_validity_args() {
		return array(
			'license_key' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The license key to validate.',
			),
			'service_id' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The service ID associated with the license.',
			),
			'domain' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The URL of the website where the license is currently activated.',
			),
		);
	}

	/**
	 * Get plugin and theme info route arguments.
	 * 
	 * @param string $type
	 * @return array
	 */
	private static function get_app_info_args( string $type ) {
		$eg_slug    = match ( $type ) {
			'plugin'    => 'smart-woo-service-invoicing',
			'theme'     => 'astra',
			default     => 'software-slug'
		};
		return array(
			'id' => array(
				'required'    => false,
				'type'        => 'integer',
				'description' => sprintf( 'The %1$s ID. Optional when %1$s slug is provided.', $type ),
			),
			'slug' => array(
				'required'    => false,
				'type'        => 'string',
				'description' => sprintf( 'The %1$s slug eg. %2$s. Optional when %1$s ID is provided.', $type, $eg_slug ),
			),
		);
	}

	/**
	 * Get repository route arguments.
	 * 
	 * @return array
	 */
	private static function get_repository_args() {
		return array(
			'search' => array(
				'required'    => false,
				'type'        => 'string',
				'description' => 'The search term',
			),
			'page' => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 1,
				'description' => 'Current pagination number.',
			),
			'limit' => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 10,
				'description' => 'Maximum number of apps per page.',
			),
			'app_slugs' => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'An array of app slugs to filter by (eg., [\'smart-woo-service-invoicing\', \'woocommerce\', \'astra\'].',
			),
			'app_types' => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'An array of app types to filter by (e.g., [\'plugin\', \'theme\']).',
			),
		);
	}

	/**
	 * Repository app route arguments.
	 * 
	 * @return array
	 */
	private static function get_repository_app_args() : array {
		return array();
	}

	/**
	 * Request arguments for `Non-Safe Methods` on single app route.
	 * 
	 * @param string[] $method HTTP request method.
	 * @return array
	 */
	private static function get_app_write_args( string ...$method ) : array {
		return [
			'app_name'  => array(
				'required'  => in_array( 'POST', $method ),
				'type'      => 'string',
				'description'   => 'The application name. Required when uploading new app.'
			),
			'app_author'    => array(
				'required'      => in_array( 'POST', $method ),
				'type'          => 'string',
				'description'   => 'The full name of the application author.'
			),
			'app_author_url'    => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'The author URL.'
			),
			'app_version'   => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'The application version. For Wordpress plugins and themes, the version will be extracted from the readme.txt and style.css respectively, custom apps should define the app version in the app.json file.'
			),

			'app_download_url'  => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'Optional, leave empty to serve app zip file from this server or specify alternative download URL.'
			),
			'app_support_url'   => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'Optional, provide support URL for the application if applicable.'
			),
			'app_homepage_url'  => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'Optional, provide alternative homepage URL for the application.'
			),
			'app_preview_url'   => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'Optional, provide preview URL for WordPress themes.'
			),
			'app_documentation_url' => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'Optional, provide the application documentation URL.'
			),
			'app_external_repository_url'   => array(
				'required'      => false,
				'type'          => 'string',
				'description'   => 'Optional, provide external repository URL for WordPress themes.'
			
			),
			'app_zip_file'  => array(
				'required'      => false,
				'type'          => 'binary',
				'description'   => 'Submit a zip file for the app, keyed in with this argument in a multipart/form-data request. This is required for new applications.'
			
			),
			'app_json_file' => array(
				'required'      => false,
				'type'          => 'binary',
				'description'   => 'Submit an app.json file, keyed in with this argument in a multipart/form-data request. The content of this file will be served in the "manifest" property of REST response for the application.'
			
			),
		];
	}

	/**
	 * Request arguments for deleting an application
	 */
	private static function get_app_delete_args() {
		return [];
	}
	/**
	 * Get download reauthentication route arguments.
	 * 
	 * @return array
	 */
	private static function get_download_reauth_args() {
		return array(
			'domain' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The domain where the plugin is installed.',
			),
			'license_key' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The license key to reauthenticate.',
			),
			'download_token' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The base64 encoded download token issued during license activation or the last reauthentication token.',
			),
			'service_id' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The service ID associated with the license.',
			),
		);
	}

	/**
	 * Get bulk messages route arguments.
	 * 
	 * @return array
	 */
	private static function get_bulk_messages_args() {
		return array(
			'page' => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 1,
				'description' => 'Page number for pagination.',
			),
			'limit' => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 10,
				'description' => 'Number of messages per page.',
			),
			'app_slugs' => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'An array of app slugs to filter by.',
			),
			'app_types' => array(
				'required'    => false,
				'type'        => 'array',
				'description' => 'An array of app types to filter by (e.g., plugin, theme).',
			),
		);
	}

	/**
	 * Helper method to return true
	 * 
	 * @return true
	 */
	public static function return_true() : true {
		return true;
	}
}