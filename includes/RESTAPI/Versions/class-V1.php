<?php
/**
 * REST API version 1 class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 * @version 1
 */

namespace SmartLicenseServer\RESTAPI\Versions;

use SmartLicenseServer\RESTAPI\RESTInterface;

use function defined;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * API version 1
 * 
 * Environment-agnostic REST API route definitions.
 * Call get_routes() to retrieve all route configurations for registration.
 * 
 * @version 1.0
 */
class V1 implements RESTInterface {
    /**
     * App route regex.
     * 
     * @var string
     */
    const APP_ROUTE_REGEX   = '(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';
    
    /**
     * Asset type regex.
     */
    const ASSET_TYPE_REGEX = '(?P<asset_type>(?:cover|icon|banner|screenshot)s?)';

    /**
     * Asset name regex.
     */
    const ASSET_NAME_REGEX = '(?P<asset_name>[a-zA-Z0-9_-]+)';


    /** 
     * REST API Route namespace.
     * 
     * @var string
     */
    private static $namespace = 'smliser/v1';

    /** 
     * Plugin info REST API route.
     * 
     * @var string
     */
    private static $plugin_info = '/plugin-info/';
    
    /** 
     * Plugin info REST API route.
     * 
     * @var string
     */
    private static $theme_info = '/theme-info/';

    /** 
     * License activation REST API route.
     * 
     * @var string
     */
    private static $activation_route = '/license-activation/' . self::APP_ROUTE_REGEX;

    /** 
     * License deactivation REST API route
     * 
     * @var string
     */
    private static $deactivation_route = '/license-deactivation/';

    /**
     * License uninstallation route.
     * 
     * @var string
     */
    private static $license_uninstallation_route = '/license-uninstallation/';

    /**
     * License validity route.
     * 
     * @var string
     */
    private static $license_validity_route = '/license-validity-test/'. self::APP_ROUTE_REGEX;

    /** 
     * Route to query the entire repository.
     * 
     * @var string
     */
    private static $repository_route = '/repository/';

    /** 
     * Route to perform CRUD operations on an application.
     * @example ```GET
     *  /repository/plugin/woocommerce
     * ```
     * 
     * @var string
     */
    private static $repository_app_route = '/repository/' . self::APP_ROUTE_REGEX;

    /** 
     * Route to  operations on an applications' assets.
     * @example ```POST
     *  /repository/plugin/smart-woo-pro/assets/
     * ```
     * 
     * @var string
     */
    const APP_ASSETS_ROUTE_BASE = '/repository/' . self::APP_ROUTE_REGEX . '/assets/';
    /** 
     * Route to  operations on an applications' assets.
     * @example ```POST
     *  /repository/plugin/smart-woo-pro/assets/banners
     * ```
     * 
     * @var string
     */
    const APP_ASSETS_TYPE_ROUTE = self::APP_ASSETS_ROUTE_BASE . self::ASSET_TYPE_REGEX;

    /**
     * Route to a single application asset file.
     * 
     * @example `PUT`
     * /respository/theme/astra/assets/screenshots/screenshot
     */
    const APP_ASSET_TYPE_ROUTE  = self::APP_ASSETS_TYPE_ROUTE . '/' . self::ASSET_NAME_REGEX;


    /**
     * Download token regeneration REST API route
     * 
     * @var string
     */
    private static $download_reauth = '/download-token-reauthentication/' . self::APP_ROUTE_REGEX;

    /**
     * REST endpoint for bulk messages fetching.
     * 
     * @var string
     */
    private static $bulk_messages_route = '/bulk-messages/';

    /**
     * Get the API namespace.
     * 
     * @return string
     */
    public static function get_namespace() : string {
        return self::$namespace;
    }

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
     *      The URI pattern relative to the namespace.
     *      Example: "/activate" or "/repository/{id}"
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
            'namespace' => self::$namespace,
            'routes'    => array(
                // License Activation Route
                array(
                    'route'         => self::$activation_route,
                    'methods'       => ['POST'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'activation_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'activation_permission_callback' ),
                    'args'          => self::get_license_activation_args(),
                    'category'      => 'license',
                    'name'          => 'License Activation',
                ),

                // License Deactivation Route
                array(
                    'route'         => self::$deactivation_route,
                    'methods'       => array( 'PUT', 'POST', 'PATCH' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'deactivation_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'deactivation_permission' ),
                    'args'          => self::get_license_deactivation_args(),
                    'category'      => 'license',
                    'name'          => 'License Deactivation',
                ),

                // License Uninstallation Route.
                array(
                    'route'         => self::$license_uninstallation_route,
                    'methods'       => array( 'PUT', 'POST', 'PATCH' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'uninstallation_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'uninstallation_permission' ),
                    'args'          => self::get_license_uninstallation_args(),
                    'category'      => 'license',
                    'name'          => 'License Uninstallation',
                ),

                // License Validity Test Route.
                array(
                    'route'         => self::$license_validity_route,
                    'methods'       => ['POST'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'validity_test_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'validity_test_permission' ),
                    'args'          => self::get_license_validity_args(),
                    'category'      => 'license',
                    'name'          => 'License Validity Test',
                ),

                // Plugin Info Route.
                array(
                    'route'         => self::$plugin_info,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Plugins::class, 'plugin_info_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Plugins::class, 'info_permission_callback' ),
                    'args'          => self::get_app_info_args( 'plugin' ),
                    'category'      => 'repository',
                    'name'          => 'Plugin Information',
                ),

                // Theme Info Route.
                array(
                    'route'         => self::$theme_info,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Themes::class, 'theme_info_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Themes::class, 'info_permission_callback' ),
                    'args'          => self::get_app_info_args( 'theme' ),
                    'category'      => 'repository',
                    'name'          => 'Theme Information',
                ),

                // Repository Route.
                array(
                    'route'         => self::$repository_route,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_get_guard' ),
                    'args'          => self::get_repository_args(),
                    'category'      => 'repository',
                    'name'          => 'Repository Query',
                ),

                // Repository App Route (CRUD).
                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'single_app_get' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_get_guard' ),
                    'args'          => self::get_repository_app_args(),
                    'category'      => 'repository',
                    'name'          => 'Get Single Application',
                ),
                
                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => ['POST'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'create_app' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_unsafe_method_guard' ),
                    'args'          => self::get_app_write_args( 'POST' ),
                    'category'      => 'repository',
                    'name'          => 'Create a New Application',
                ),

                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => array( 'PUT', 'PATCH' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'update_app' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_unsafe_method_guard' ),
                    'args'          => self::get_app_write_args( 'PUT', 'PATCH' ),
                    'category'      => 'repository',
                    'name'          => 'Update an Existing Application',
                ),

                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => array( 'DELETE' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'delete_app' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_unsafe_method_guard' ),
                    'args'          => self::get_app_delete_args(),
                    'category'      => 'repository',
                    'name'          => 'Delete Existing Application'
                ),

                // App asset routes.
                array(
                    'route'         => self::APP_ASSETS_TYPE_ROUTE,
                    'methods'       => array( 'POST' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'upload_app_assets' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'assets_management_guard' ),
                    'args'          => [],
                    'category'      => 'repository',
                    'name'          => 'App Asset Bulk Upload'
                ),

                array(
                    'route'         => self::APP_ASSET_TYPE_ROUTE,
                    'methods'       => array( 'PUT' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'update_app_asset' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'assets_management_guard' ),
                    'args'          => [],
                    'category'      => 'repository',
                    'name'          => 'Create or Replace a Single App Asset'
                ),

                array(
                    'route'         => self::APP_ASSETS_ROUTE_BASE . self::ASSET_NAME_REGEX,
                    'methods'       => array( 'DELETE' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'delete_app_asset' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'assets_management_guard' ),
                    'args'          => [],
                    'category'      => 'repository',
                    'name'          => 'Create or Replace App Asset'
                ),

                // Download Token Reauthentication Route
                array(
                    'route'         => self::$download_reauth,
                    'methods'       => ['POST'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'app_download_reauth' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'download_reauth_permission' ),
                    'args'          => self::get_download_reauth_args(),
                    'category'      => 'license',
                    'name'          => 'Download Token Reauthentication',
                ),

                // Mock Inbox Route (for testing)
                array(
                    'route'         => '/mock-inbox',
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\BulkMessages::class, 'mock_dispatch' ),
                    'guard'         => [__CLASS__, 'return_true'],
                    'args'          => array(),
                    'category'      => 'testing',
                    'name'          => 'Mock Inbox (Testing)',
                ),

                // Bulk Messages Route
                array(
                    'route'         => self::$bulk_messages_route,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\BulkMessages::class, 'dispatch_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\BulkMessages::class, 'permission_callback' ),
                    'args'          => self::get_bulk_messages_args(),
                    'category'      => 'bulk-messages',
                    'name'          => 'Bulk Messages',
                ),
            ),
        );
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
                'description' => 'The ID of the item associated with the license.',
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
                'description' => sprintf( 'The %s ID', $type ),
            ),
            'slug' => array(
                'required'    => false,
                'type'        => 'string',
                'description' => sprintf( 'The %1$s slug eg. %2$s', $type, $eg_slug ),
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
     * Get available route categories.
     * 
     * @return array List of available categories.
     */
    public static function get_categories() : array {
        return array(
            'license'         => 'License Management',
            'repository'      => 'Repository',
            'bulk-messages'   => 'Bulk Messages',
            'authentication'  => 'Authentication',
            'plugin'          => 'Plugin',
            'testing'         => 'Testing',
        );
    }

    /**
     * Describe routes by category or all routes.
     * 
     * Returns pre-formatted HTML cards for each route.
     * 
     * @param string|null $category The category to filter by (e.g., 'license', 'repository').
     *                              If null, returns all routes.
     * @return array Associative array where key is the route path and value is formatted HTML card.
     */
    public static function describe_routes( ?string $category = null ) : array {

        $api_config   = self::get_routes();
        $routes       = $api_config['routes'];
        $namespace    = $api_config['namespace'];
        $descriptions = array();

        foreach ( $routes as $route_config ) {

            // Filter by category.
            if (
                $category !== null &&
                ( ! isset( $route_config['category'] ) || $route_config['category'] !== $category )
            ) {
                continue;
            }

            $route_path = \str_ireplace( self::APP_ROUTE_REGEX, '{app-type}/{app-slug}', $namespace . $route_config['route'] );
            $route_name = $route_config['name'] ?? 'Unnamed Route';
            $methods    = is_array( $route_config['methods'] ) ? $route_config['methods'] : array( $route_config['methods'] );
            $args       = $route_config['args'] ?? array();

            // Create **one card per method**.
            foreach ( $methods as $method ) {
                $method_upper = strtoupper( trim( $method ) );
                $card_key     = $route_path . '|' . $method_upper; // unique key per route+method

                $html  = '<div class="smliser-api-route-card">';
                $html .= '<div class="smliser-api-route-header">';
                $html .= '<h3 class="smliser-api-route-name">' . self::esc_html( $route_name ) . ' <span class="method">' . self::esc_html( $method_upper ) . '</span></h3>';

                // Route path + copy button
                $html .= '<div class="smliser-api-route-path-container">';
                $html .= '<div class="smliser-api-route-path">' . self::esc_html( $route_path ) . '</div>';
                $html .= '<button class="smliser-api-copy-btn" onclick="navigator.clipboard.writeText(\'' . self::esc_js( $route_path ) . '\'); this.textContent=\'Copied!\'; setTimeout(() => this.textContent=\'Copy\', 2000);">Copy</button>';
                $html .= '</div>'; // path container.

                $html .= '</div>'; // header.

                // Body: args.
                $html .= '<div class="smliser-api-route-body">';
                if ( empty( $args ) ) {
                    $html .= '<p class="smliser-api-no-arguments">No arguments required.</p>';
                } else {
                    $html .= '<h4 class="smliser-api-arguments-title">Parameters</h4>';
                    foreach ( $args as $arg_name => $arg_config ) {
                        $required        = ! empty( $arg_config['required'] );
                        $type            = $arg_config['type'] ?? 'mixed';
                        $arg_description = $arg_config['description'] ?? 'No description';
                        $default         = $arg_config['default'] ?? null;

                        $html .= '<div class="smliser-api-argument">';
                        $html .= '<div class="smliser-api-argument-header">';
                        $html .= '<span class="smliser-api-argument-name">' . self::esc_html( $arg_name ) . '</span>';
                        $html .= '<span class="smliser-api-argument-type">' . self::esc_html( $type ) . '</span>';

                        if ( $required ) {
                            $html .= '<span class="smliser-api-argument-required">Required</span>';
                        } else {
                            $html .= '<span class="smliser-api-argument-optional">Optional</span>';
                        }

                        if ( null !== $default ) {
                            $html .= '<span class="smliser-api-argument-default">Default: ' . self::esc_html( $default ) . '</span>';
                        }

                        $html .= '</div>'; // arg header.
                        $html .= '<p class="smliser-api-argument-description">' . self::esc_html( $arg_description ) . '</p>';
                        $html .= '</div>'; // arg.
                    }
                }

                $html .= '</div>'; // body.
                $html .= '</div>'; // card.

                $descriptions[ $card_key ] = $html;
            }
        }

        return $descriptions;
    }

    /**
     * Helper method to return true
     * 
     * @return true
     */
    public static function return_true() : true {
        return true;
    }

    /**
     * Escape HTML for display.
     * 
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    private static function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Escape JavaScript string.
     * 
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    private static function esc_js( $text ) {
        return addslashes( $text );
    }

    /**
     * Render a html index of REST API documentation page
     */
    public static function html_index() {
        ?>
            <div class="smliser-admin-api-description-section">
                <h2 class="heading">REST API Documentation</h2>
                <div class="smliser-api-base-url">
                    <strong>Base URL:</strong>
                    <code><?php echo esc_url( rest_url() ); ?></code>
                </div>
                
                <?php foreach ( self::describe_routes() as $path => $html ) : 
                    echo $html;
                endforeach; ?>
            </div>
    
        <?php
    }
}