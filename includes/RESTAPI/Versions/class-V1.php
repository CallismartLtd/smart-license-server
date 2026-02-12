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
    private static $activation_route = '/license-activation/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

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
    private static $license_validity_route = '/license-validity-test/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

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
    private static $repository_app_route = '/repository/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

    /**
     * Download token regeneration REST API route
     * 
     * @var string
     */
    private static $download_reauth = '/download-token-reauthentication/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

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
     *         methods: string|array,
     *         handler: callable,
     *         guard: callable,
     *         args: array,
     *         category: string,
     *         name: string
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

                // License Uninstallation Route
                array(
                    'route'         => self::$license_uninstallation_route,
                    'methods'       => array( 'PUT', 'POST', 'PATCH' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'uninstallation_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'uninstallation_permission' ),
                    'args'          => self::get_license_uninstallation_args(),
                    'category'      => 'license',
                    'name'          => 'License Uninstallation',
                ),

                // License Validity Test Route
                array(
                    'route'         => self::$license_validity_route,
                    'methods'       => ['POST'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'validity_test_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Licenses::class, 'validity_test_permission' ),
                    'args'          => self::get_license_validity_args(),
                    'category'      => 'license',
                    'name'          => 'License Validity Test',
                ),

                // Plugin Info Route
                array(
                    'route'         => self::$plugin_info,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Plugins::class, 'plugin_info_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Plugins::class, 'info_permission_callback' ),
                    'args'          => self::get_app_info_args(),
                    'category'      => 'repository',
                    'name'          => 'Plugin Information',
                ),

                // Theme Info Route
                array(
                    'route'         => self::$theme_info,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\Themes::class, 'theme_info_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\Themes::class, 'info_permission_callback' ),
                    'args'          => self::get_app_info_args(),
                    'category'      => 'repository',
                    'name'          => 'Plugin Information',
                ),

                // Repository Route
                array(
                    'route'         => self::$repository_route,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_response' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_get_guard' ),
                    'args'          => self::get_repository_args(),
                    'category'      => 'repository',
                    'name'          => 'Repository Query',
                ),

                // Repository App Route (CRUD)
                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => ['GET'],
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'single_app_get' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_get_guard' ),
                    'args'          => self::get_repository_app_args(),
                    'category'      => 'repository',
                    'name'          => 'Repository App CRUD',
                ),
                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'single_app_crud' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_get_guard' ),
                    'args'          => self::get_repository_app_args(),
                    'category'      => 'repository',
                    'name'          => 'Repository App CRUD',
                ),
                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'single_app_crud' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_get_guard' ),
                    'args'          => self::get_repository_app_args(),
                    'category'      => 'repository',
                    'name'          => 'Repository App CRUD',
                ),
                array(
                    'route'         => self::$repository_app_route,
                    'methods'       => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
                    'handler'       => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'single_app_crud' ),
                    'guard'         => array( \SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_get_guard' ),
                    'args'          => self::get_repository_app_args(),
                    'category'      => 'repository',
                    'name'          => 'Repository App CRUD',
                ),

                // Download Token Reauthentication Route
                array(
                    'route'         => self::$download_reauth,
                    'methods'       => 'POST',
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
     * @return array
     */
    private static function get_app_info_args() {
        return array(
            'id' => array(
                'required'    => false,
                'type'        => 'integer',
                'description' => 'The plugin ID',
            ),
            'slug' => array(
                'required'    => false,
                'type'        => 'string',
                'description' => 'The plugin slug eg. plugin-slug/plugin-slug',
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
     * Repository app route arguments.
     * 
     * @return array
     */
    private static function get_repository_app_args() : array {
        return array(
            'app_type' => array(
                'required'    => false,
                'type'        => 'string',
                'description' => 'The type of application being queried. This should be specified in the URL path after /repository/, example /repository/plugin/',
            ),
            'app_slug' => array(
                'required'    => false,
                'type'        => 'string',
                'description' => 'The slug of the application being queried. This should be specified in the URL path after /repository/{app-type}/, eg /repository/plugin/smart-woo-pro',
            ),
        );
    }

    /**
     * Request arguments for `Non-Safe Methods` on single app route.
     * 
     * @return array
     */
    private static function get_app_write_args() : array {
        return [
            'app_type'                      => array(),
            'app_id'                        => array(),
            'app_name'                      => array(),
            'app_author'                    => array(),
            'app_author_url'                => array(),
            'app_version'                   => array(),
            'app_required_php_version'      => array(),
            'app_required_wp_version'       => array(),
            'app_tested_wp_version'         => array(),
            'app_download_url'              => array(),
            'app_support_url'               => array(),
            'app_homepage_url'              => array(),
            'app_preview_url'               => array(),
            'app_documentation_url'         => array(),
            'app_external_repository_url'   => array(),
            'app_zip_file'                  => array(),
            'app_json_file'                 => array(),
        ];
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
        $api_config = self::get_routes();
        $routes = $api_config['routes'];
        $namespace = $api_config['namespace'];
        $descriptions = array();

        foreach ( $routes as $route_config ) {
            // Filter by category if specified
            if ( $category !== null && ( ! isset( $route_config['category'] ) || $route_config['category'] !== $category ) ) {
                continue;
            }

            $route_path = $namespace . $route_config['route'];
            $route_name = isset( $route_config['name'] ) ? $route_config['name'] : 'Unnamed Route';
            $methods = is_array( $route_config['methods'] ) ? $route_config['methods'] : array( $route_config['methods'] );
            
            // Build HTML card
            $html = '<div class="smliser-api-route-card">';
            
            // Header section
            $html .= '<div class="smliser-api-route-header">';
            $html .= '<h3 class="smliser-api-route-name">' . self::esc_html( $route_name ) . '</h3>';
            
            // Method badges
            $html .= '<div class="smliser-api-route-methods">';
            foreach ( $methods as $method ) {
                $method = strtoupper( trim( $method ) );
                $method_class = 'method-' . strtolower( $method );
                $html .= '<span class="smliser-api-method-badge ' . $method_class . '">' . self::esc_html( $method ) . '</span>';
            }
            $html .= '</div>';
            
            // Route path with copy button
            $html .= '<div class="smliser-api-route-path-container">';
            $html .= '<div class="smliser-api-route-path">' . self::esc_html( $route_path ) . '</div>';
            $html .= '<button class="smliser-api-copy-btn" onclick="navigator.clipboard.writeText(\'' . self::esc_js( $route_path ) . '\'); this.textContent=\'Copied!\'; setTimeout(() => this.textContent=\'Copy\', 2000);">Copy</button>';
            $html .= '</div>';
            $html .= '</div>'; // End header
            
            // Body section
            $html .= '<div class="smliser-api-route-body">';
            
            if ( empty( $route_config['args'] ) ) {
                $html .= '<p class="smliser-api-no-arguments">No arguments required.</p>';
            } else {
                $html .= '<h4 class="smliser-api-arguments-title">Parameters</h4>';
                
                foreach ( $route_config['args'] as $arg_name => $arg_config ) {
                    $required = isset( $arg_config['required'] ) && $arg_config['required'];
                    $type = isset( $arg_config['type'] ) ? $arg_config['type'] : 'mixed';
                    $arg_description = isset( $arg_config['description'] ) ? $arg_config['description'] : 'No description';
                    $default = isset( $arg_config['default'] ) ? $arg_config['default'] : null;
                    
                    $html .= '<div class="smliser-api-argument">';
                    $html .= '<div class="smliser-api-argument-header">';
                    $html .= '<span class="smliser-api-argument-name">' . self::esc_html( $arg_name ) . '</span>';
                    $html .= '<span class="smliser-api-argument-type">' . self::esc_html( $type ) . '</span>';
                    
                    if ( $required ) {
                        $html .= '<span class="smliser-api-argument-required">Required</span>';
                    } else {
                        $html .= '<span class="smliser-api-argument-optional">Optional</span>';
                    }
                    
                    if ( $default !== null ) {
                        $html .= '<span class="smliser-api-argument-default">Default: ' . self::esc_html( $default ) . '</span>';
                    }
                    
                    $html .= '</div>';
                    $html .= '<p class="smliser-api-argument-description">' . self::esc_html( $arg_description ) . '</p>';
                    $html .= '</div>';
                }
            }
            
            $html .= '</div>'; // End body
            $html .= '</div>'; // End card
            
            $descriptions[$route_path] = $html;
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
            <div>
                <h2>REST API Documentation</h2>
                <div class="smliser-admin-api-description-section">
                    <div class="smliser-api-base-url">
                        <strong>Base URL:</strong>
                        <code><?php echo esc_url( rest_url() ); ?></code>
                    </div>
                    
                    <?php foreach ( self::describe_routes() as $path => $html ) : 
                        echo $html; // Already safely escaped in the V1 class
                    endforeach; ?>
                </div>
            </div>
        <?php
    }
}