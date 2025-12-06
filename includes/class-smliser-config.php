<?php
/**
 * file name class-smliser-config.php
 * License Server environment configuration file
 * 
 * @author Callistus
 * @package SmartLicenseServer
 * @since 1.0.0
 */


defined( 'ABSPATH' ) || exit;

class SmartLicense_config {

    /** 
     * REST API Route namespace.
     * 
     * @var string
     */
    private $namespace  = 'smliser/v1';

    /** 
     * Plugin info REST API route.
     * 
     * @var string
     */
    private $plugin_info   = '/plugin-info/';

    /** 
     * Theme info REST API route.
     * 
     * @var string
     */
    private $theme_info   = '/theme-info/';

    /** 
     * License activation REST API route.
     * 
     * @var string
     */
    private $activation_route = '/license-activation/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

    /** 
     * License deactivation REST API route
     * 
     * @var string
     */
    private $deactivation_route = '/license-deactivation/';

    /**
     * License uninstallation route.
     * 
     * @var string
     */
    private $license_uninstallation_route = '/license-uninstallation/';

    /**
     * License validity route.
     * 
     * @var string
     */
    private $license_validity_route = '/license-validity-test/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

    /** 
     * Repository REST API route.
     * 
     * @var string
     */
    private $repository_route = '/repository/';

    /** 
     * Repository REST API route for specific hosted application type.
     * 
     * @var string
     */
    private $repository_app_route = '/repository/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';
    
    /** 
     * Client authentication REST API route, essentially for token regeneration.
     * 
     * @var string
     */
    private $app_reauth = '/client-auth/';

    /**
     * Download token regeneration REST API route
     * 
     * @var string
     */
    private $download_reauth = '/download-token-reauthentication/(?P<app_type>[a-zA-Z0-9_-]+)/(?P<app_slug>[a-zA-Z0-9_-]+)';

    /**
     * RESE endoint for bulk bulk messages fetching.
     * 
     * @var string $bulk_messages_route
     */
    private $bulk_messages_route = '/bulk-messages/';

    /** 
     * Instance of current class.
     * 
     * @var SmartLicense_config
     */
    private static $instance = null;

    /**
     * Class constructor.
     */
    public function __construct() {
        global $wpdb;

        define( 'SMLISER_REPOSITORY_ROUTE', $this->repository_route );
        define( 'SMLISER_LICENSE_TABLE', $wpdb->prefix.'smliser_licenses' );
        define( 'SMLISER_LICENSE_META_TABLE', $wpdb->prefix . 'smliser_license_meta' );

        /**
         * The plugin database table name
         */
        define( 'SMLISER_PLUGIN_ITEM_TABLE', $wpdb->prefix . 'smliser_plugins' );
        /**
         * The plugin database metadata table name.
         */
        define( 'SMLISER_PLUGIN_META_TABLE', $wpdb->prefix . 'smliser_plugin_meta' );

        /**
         * The theme database table name.
         */
        define( 'SMLISER_THEME_ITEM_TABLE', $wpdb->prefix . 'smliser_themes' );
        /**
         * The themes database metadata table name.
         */
        define( 'SMLISER_THEME_META_TABLE', $wpdb->prefix . 'smliser_theme_meta' );

        define( 'SMLISER_APPS_ITEM_TABLE', $wpdb->prefix . 'smliser_applications' );
        define( 'SMLISER_APPS_META_TABLE', $wpdb->prefix . 'smliser_applications_meta' );
        define( 'SMLISER_API_ACCESS_LOG_TABLE', $wpdb->prefix . 'smliser_api_access_logs' );
        define( 'SMLISER_API_CRED_TABLE', $wpdb->prefix . 'smliser_api_creds' );
        define( 'SMLISER_DOWNLOAD_TOKEN_TABLE', $wpdb->prefix . 'smliser_item_download_token' );
        define( 'SMLISER_APP_DOWNLOAD_TOKEN_TABLE', $wpdb->prefix . 'smliser_app_download_tokens' );
        define( 'SMLISER_MONETIZATION_TABLE', $wpdb->prefix . 'smliser_monetization' );
        define( 'SMLISER_PRICING_TIER_TABLE', $wpdb->prefix . 'smliser_pricing_tiers' );
        define( 'SMLISER_BULK_MESSAGES_TABLE', $wpdb->prefix . 'smliser_bulk_messages' );
        define( 'SMLISER_BULK_MESSAGES_APPS_TABLE', $wpdb->prefix . 'smliser_bulk_messages_apps' );

        /**
         * Absolute path to the root Smart License Server repository directory.
         *
         * This is the base directory where all hosted application files are stored.
         */
        define( 'SMLISER_NEW_REPO_DIR', WP_CONTENT_DIR . '/smliser-repo' );

        /**
         * Alias for the Smart License Server repository directory.
         *
         * Used as the root path for all application repositories.
         */
        define( 'SMLISER_REPO_DIR', SMLISER_NEW_REPO_DIR );

        /**
         * Absolute path to the plugin repository directory.
         *
         * Stores all plugin packages and related assets hosted in the repository.
         */
        define( 'SMLISER_PLUGINS_REPO_DIR', SMLISER_REPO_DIR . '/plugins' );

        /**
         * Absolute path to the theme repository directory.
         *
         * Stores all theme packages and related assets hosted in the repository.
         */
        define( 'SMLISER_THEMES_REPO_DIR', SMLISER_REPO_DIR . '/themes' );

        /**
         * Absolute path to the software repository directory.
         *
         * Stores all software packages and related assets hosted in the repository.
         */
        define( 'SMLISER_SOFTWARE_REPO_DIR', SMLISER_REPO_DIR . '/software' );
        
        register_activation_hook( SMLISER_FILE, array( SmartLicenseServer\Installer::class, 'install' ) );

        // Register REST endpoints.
        // add_action( 'rest_api_init', array( $this, 'rest_load' ) );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https_for_rest_api' ), 10, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'filter_rest_response' ), 10, 3 );
        add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'rest_request_before_callbacks' ), -1, 3 );

        add_filter( 'redirect_canonical', array( $this, 'disable_redirect_on_downloads' ), 10, 2 );
        add_filter( 'query_vars', array( $this, 'query_vars') );
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );

        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );
        add_action( 'wp_ajax_smliser_upgrade', array( 'Smliser_Install', 'ajax_update' ) );
        add_action( 'admin_post_nopriv_smliser_oauth_login', array( 'Smliser_API_Cred', 'oauth_login_form_handler' ) );
        add_action( 'smliser_stats', array( 'Smliser_Stats', 'action_handler' ), 10, 4 );
        add_action( 'wp_ajax_smliser_key_generate', array( 'Smliser_API_Cred', 'admin_create_cred_form' ) );
        add_action( 'wp_ajax_smliser_revoke_key', array( 'Smliser_API_Cred', 'revoke' ) );
        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );

        add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );

        add_action( 'admin_notices', function() { self::check_filesystem_errors(); } );
    }

    /** Load or Register our Rest route */
    public function rest_load() {
        
        /** 
         * Register the license activation route. 
         */
        register_rest_route( $this->namespace, $this->activation_route, 
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            =>  array( SmartLicenseServer\RESTAPI\Licenses::class, 'activation_response' ),
                'permission_callback' => array( SmartLicenseServer\RESTAPI\Licenses::class, 'activation_permission_callback'),
                'args'  => array(
                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service id associated with the license key',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to verify',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The ID of the item associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    )
                ),
            )
        );

        /**
         * Register the license deactivation route
         */
        register_rest_route( $this->namespace, $this->deactivation_route, 
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( SmartLicenseServer\RESTAPI\Licenses::class, 'deactivation_response' ),
                'permission_callback' => array( SmartLicenseServer\RESTAPI\Licenses::class, 'deactivation_permission' ),
                'args'  => array(
                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to deactivate.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The URL of the website where the license is currently activated.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    )
                ),
            )
        );

        /**
         * Register license unstallation route.
         */
        register_rest_route( $this->namespace, $this->license_uninstallation_route, 
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( SmartLicenseServer\RESTAPI\Licenses::class, 'uninstallation_response' ),
                'permission_callback' => array( SmartLicenseServer\RESTAPI\Licenses::class, 'uninstallation_permission' ),
                'args'  => array(
                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to uninstall.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The URL of the website where the license is currently activated.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    )
                ),
            )
        );

        /**
         * Register license validity route.
         */
        register_rest_route( $this->namespace, $this->license_validity_route, 
            array(
                'methods'             => 'POST',
                'callback'            => array( SmartLicenseServer\RESTAPI\Licenses::class, 'validity_test_response' ),
                'permission_callback' => array( SmartLicenseServer\RESTAPI\Licenses::class, 'validity_test_permission' ),
                'args'  => array(
                    'license_key'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to validate.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),

                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'domain'  => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The URL of the website where the license is currently activated.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' ),
                    )
                ),
            )
        );

        /** 
         * Register plugin info route.
         * 
         */
        register_rest_route( $this->namespace, $this->plugin_info, array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => array( SmartLicenseServer\RESTAPI\Plugin::class, 'info_permission_callback' ),
            'callback'            => array( SmartLicenseServer\RESTAPI\Plugin::class, 'plugin_info_response' ),
            'args'  => array(
                'id'   => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'description'       => 'The plugin ID',
                    'sanitize_callback' => 'absint',
                ),
                'slug'      => array(
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => 'The plugin slug eg. plugin-slug/plugin-slug',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' )
                ),
            ),
            

        ));

        /** 
         * Register REST API route for querying entire repository
         */
        register_rest_route( $this->namespace, $this->repository_route, 
            array(
                'methods'               => WP_REST_Server::READABLE,
                'callback'              => array( SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_response' ),
                'permission_callback'   => array( SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_access_permission' ),
                'args'  => array(
                    'search'   => array(
                        'required'          => false,
                        'type'              => 'string',
                        'description'       => 'The search term',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => '__return_true'
                    )
                ),
            )
        );

        /** 
         * Register dynamic repository route for hosted applications.
         * 
         * This route is designed for CRUD operations on all hosted apps.
         * URL: route/repository/app_type/app_slug
         */
        register_rest_route( $this->namespace, $this->repository_app_route, array(
            'methods'             => WP_REST_Server::ALLMETHODS,
            'permission_callback' => array( SmartLicenseServer\RESTAPI\AppCollection::class, 'repository_access_permission' ),
            'callback'            => array( SmartLicenseServer\RESTAPI\AppCollection::class, 'single_app_crud' ),
            'args'  => array(
                'item_id'   => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'description'       => 'The plugin ID',
                    'sanitize_callback' => 'absint',
                ),
                'slug'      => array(
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => 'The plugin slug eg. plugin-slug/plugin-slug',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' )
                ),
            ),
            

        ) );

        /** Register Oauth client authentication route */
        register_rest_route( $this->namespace, $this->app_reauth, array(
            'methods'               => 'GET',
            'callback'              => array( 'Smliser_REST_Authentication', 'client_authentication_response' ),
            'permission_callback'   => array( 'Smliser_REST_Authentication', 'auth_permission' ),
        ) );

        /** 
         * Register download token reauthenticatiion route.
        */
        register_rest_route( $this->namespace, $this->download_reauth, 
            array(
                'methods'               => 'POST',
                'callback'              => array( SmartLicenseServer\RESTAPI\Licenses::class, 'app_download_reauth' ),
                'permission_callback'   => array( SmartLicenseServer\RESTAPI\Licenses::class, 'download_reauth_permission' ),
                'args'  => array(
                    'domain'    => array(
                        'required'  => true,
                        'type'      => 'string',
                        'description'   => 'The domain where the plugin is installed.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
                        'validate_callback' => array( __CLASS__, 'is_url' )
                    ),
                    'license_key'           => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The license key to reauthenticate.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' )
                    ),
                    'download_token'    => array(
                        'required'         => true,
                        'type'              => 'string',
                        'description'       => 'The base64 encoded download token issued during license activation or the last reauthentication token.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' ),
                    ),
                    'service_id'    => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The service ID associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'not_empty' )
                    )
                )
            )
        );

        /**
         * Register mock inbox route for testing SmartWoo_Inbox_Manager
         *
         * This route simulates inbox message fetch response and can be
         * used for local development or testing plugin communications.
         *
         * Example: GET /wp-json/smliser/v1/mock-inbox
         *
         * @since 1.0.0
         */
        register_rest_route( $this->namespace, '/mock-inbox', array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => array( SmartLicenseServer\RESTAPI\BulkMessages::class, 'mock_dispatch' ),
            'permission_callback'   => '__return_true',
        ));

        /**
         * Register Bulk Messages route.
         *
         * Example: GET /wp-json/smliser/v1/bulk-messages?app_types[]=plugin&app_slugs[]=smart-woo-pro
         */
        register_rest_route( $this->namespace, $this->bulk_messages_route, array(
            'methods'               => WP_REST_Server::READABLE,
            'callback'              => array( \SmartLicenseServer\RESTAPI\BulkMessages::class, 'dispatch_response' ),
            'permission_callback'   => array( \SmartLicenseServer\RESTAPI\BulkMessages::class, 'permission_callback' ),
            'args'                  => array(
                'page' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 1,
                    'description'       => 'Page number for pagination.',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
                'limit' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 10,
                    'description'       => 'Number of messages per page.',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
                'app_slugs' => array(
                    'required'          => false,
                    'type'              => 'array',
                    'description'       => 'An array of app slugs to filter by.',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
                'app_types' => array(
                    'required'          => false,
                    'type'              => 'array',
                    'description'       => 'An array of app types to filter by (e.g., plugin, theme).',
                    'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                ),
            ),
        ));

    }

    /**
     * Ensures HTTPS/TLS for REST API endpoints within the plugin's namespace.
     *
     * Checks if the current REST API request belongs to the plugin's namespace
     * and enforces HTTPS/TLS requirements if the environment is production.
     *
     * @return WP_Error|null WP_Error object if HTTPS/TLS requirement is not met, null otherwise.
     */
    public function enforce_https_for_rest_api( $result, $server, $request ) {
        // Check if current request belongs to the plugin's namespace.
        if ( ! str_contains( $request->get_route(), $this->namespace ) ) {
            return;
        }

        // Check if environment is production and request is not over HTTPS.
        if ( 'production' === wp_get_environment_type() && ! is_ssl() ) {
            // Create WP_Error object to indicate insecure SSL.
            $error = new WP_Error( 'connection_not_secure', 'HTTPS/TLS is required for secure communication.', array( 'status' => 400, ) );
            
            // Return the WP_Error object.
            return $error;
        }
    }

    /**
     * Filter the REST API response.
     *
     * @param WP_REST_Response $response The REST API response object.
     * @param WP_REST_Server   $server   The REST server object.
     * @param WP_REST_Request  $request  The REST request object.
     * @return WP_REST_Response Modified REST API response object.
     */
    public function filter_rest_response( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ) {

        if ( false !== strpos( $request->get_route(), $this->namespace ) ) {

            $response->header( 'X-Plugin-Name', 'Smart License Server' );
            $response->header( 'X-API', 'Smart License Server API' );
            $response->header( 'X-Plugin-Version', SMLISER_VER );
            $response->header( 'X-API-Version', 'v1' );

            $data = $response->get_data();

            if ( is_array( $data ) ) {
                $data = array( 'success' => ! $response->is_error() ) + $data;
                $response->set_data( $data );
            }
        }

        return $response;
    }

    /**
     * Preempt REST API request callbacks.
     * 
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
     *                                                                   Usually a WP_REST_Response or WP_Error.
     * @param array                                            $handler  Route handler used for the request.
     * @param WP_REST_Request                                  $request  Request used to generate the response.
     */
    public static function rest_request_before_callbacks( $response, $handler, $request ) {
        
        $route     = ltrim( $request->get_route(), '/' );
        $namespace = trim( self::namespace(), '/' );

        // Match if route starts with namespace
        if ( ! preg_match( '#^' . preg_quote( $namespace, '#' ) . '(/|$)#', $route ) ) {
            return $response;
        }

        if ( is_smliser_error( $response ) ) {
            remove_filter( 'rest_post_dispatch', 'rest_send_allow_header' ); // Prevents calling the permission callback again.
        }

        return $response;
    }



    /**
     * Instanciate the current class.
     * @return self
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the namespace
     */
    public static function namespace() {
        return self::instance()->namespace;
    }

    /**
     * Include files
     */
    public function include() {
        require_once SMLISER_PATH . 'vendor/autoload.php';
        require_once SMLISER_PATH . 'includes/database/DatabaseAdapterInterface.php';
        require_once SMLISER_PATH . 'includes/database/class-Database.php';
        require_once SMLISER_PATH . 'includes/database/class-WPAdapter.php';

        require_once SMLISER_PATH . 'includes/Utils/conditional-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/functions.php';
        require_once SMLISER_PATH . 'includes/Utils/sanitization-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/formating-functions.php';
        require_once SMLISER_PATH . 'includes/Utils/class-callismart-encryption.php';
        require_once SMLISER_PATH . 'includes/Utils/class-WPReadmeParser.php';
        require_once SMLISER_PATH . 'includes/Utils/class-MDParser.php';
        
        require_once SMLISER_PATH . 'includes/Exceptions/class-Exception.php';
        require_once SMLISER_PATH . 'includes/Exceptions/class-RequestException.php';
        require_once SMLISER_PATH . 'includes/Exceptions/class-FileRequestException.php';

        require_once SMLISER_PATH . 'includes/Core/class-request.php';
        require_once SMLISER_PATH . 'includes/Core/class-response.php';
        require_once SMLISER_PATH . 'includes/Core/class-URL.php';

        require_once SMLISER_PATH . 'includes/Filesystem/class-FileSystem.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-FileSystemHelper.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-Repository.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-PluginRepository.php';
        require_once SMLISER_PATH . 'includes/Filesystem/class-ThemeRepository.php';
        require_once SMLISER_PATH . 'includes/Filesystem/DownloadsApi/class-FileRequest.php';
        require_once SMLISER_PATH . 'includes/Filesystem/DownloadsApi/class-FileResponse.php';
        require_once SMLISER_PATH . 'includes/Filesystem/DownloadsApi/class-FileRequestController.php';
    
        require_once SMLISER_PATH . 'includes/hosted-apps/hosted-apps-interface.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-AbstractHostedApp.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-software-collection.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-plugin.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-theme.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-smliser-software.php';

        require_once SMLISER_PATH . 'includes/class-WPAdapter.php';

        require_once SMLISER_PATH . 'includes/class-smliser-stats.php';
        require_once SMLISER_PATH . 'includes/class-smliser-api-cred.php';
        require_once SMLISER_PATH . 'includes/class-BulkMessages.php';

        require_once SMLISER_PATH . 'includes/RESTAPI/class-rest-auth.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/Versions/class-V1.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-Licenses.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-Plugins.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-Themes.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/class-AppCollection.php';
        require_once SMLISER_PATH . 'includes/RESTAPI/bulk-messages.php';

        require_once SMLISER_PATH . 'includes/Monetization/provider-interface.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-License.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-monetization.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-DownloadToken.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-pricing-tier.php';
        require_once SMLISER_PATH . 'includes/Monetization/provider-collection.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-woocomerce-provider.php';
        require_once SMLISER_PATH . 'includes/Monetization/class-controller.php';

        require_once SMLISER_PATH . 'includes/Admin/class-menu.php';
        require_once SMLISER_PATH . 'includes/Admin/class-dashboard-page.php';
        require_once SMLISER_PATH . 'includes/Admin/class-bulk-messages-page.php';
        require_once SMLISER_PATH . 'includes/Admin/class-repository-page.php';
        require_once SMLISER_PATH . 'includes/Admin/class-license-page.php';
        require_once SMLISER_PATH . 'includes/Admin/class-options-page.php';
        
        do_action( 'smliser_loaded' );
        
    }

    /**
     * Load Scripts
     */
    public function load_scripts( $s ) {
        wp_enqueue_script( 'smliser-script', SMLISER_URL . 'assets/js/main-script.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-apps-uploader', SMLISER_URL . 'assets/js/apps-uploader.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'select2', SMLISER_URL . 'assets/js/select2.min.js', array( 'jquery' ), SMLISER_VER, true );
        wp_register_script( 'smliser-tinymce', SMLISER_URL . 'assets/js/tinymce/tinymce.min.js', array( 'jquery' ), SMLISER_VER, true );

        if ( is_admin() ) {
            wp_enqueue_script( 'smliser-chart', SMLISER_URL . 'assets/js/chart.js', array(), SMLISER_VER, true );
        }

        if ( 'smart-license-server_page_smliser-bulk-message' === $s || 'smart-license-server_page_licenses' === $s ) {
            wp_enqueue_script( 'select2' );
        }

        // Script localizer.
        wp_localize_script(
            'smliser-script',
            'smliser_var',
            array(
                'smliser_ajax_url'  => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'smliser_nonce' ),
                'admin_url'         => admin_url(),
                'wp_spinner_gif'    => admin_url('images/spinner.gif'),
                'wp_spinner_gif_2x' => admin_url('images/spinner-2x.gif'),
                'app_search_api'    => rest_url( $this->namespace . $this->repository_route )
            )
        );

    }

    /**
     * Load styles
     */
    public function load_styles( $s ) {
        wp_enqueue_style( 'smliser-styles', SMLISER_URL . 'assets/css/smliser-styles.css', array(), SMLISER_VER, 'all' );
        wp_enqueue_style( 'smliser-form-styles', SMLISER_URL . 'assets/css/smliser-forms.css', array(), SMLISER_VER, 'all' );
        wp_register_style( 'select2', SMLISER_URL . 'assets/css/select2.min.css', array(), SMLISER_VER, 'all' );
    
        
        if ( 'smart-license-server_page_smliser-bulk-message' === $s || 'smart-license-server_page_licenses' === $s ) {
            wp_enqueue_style( 'select2' );
        }
    
    }

    /**
     * Init hooks
     */
    public function init_hooks() {
        $this->run_automation();
        $repo_base_url = get_option( 'smliser_repo_base_perma', 'repository' );
    
        add_rewrite_rule(
            '^' . $repo_base_url . '$',
            'index.php?pagename=smliser-repository',
            'top'
        );
    
        /**
         * Repository app type page matches siteurl/repository/{app_type}/ where app type can be (themes, plugins, softwares)
         */
        add_rewrite_rule(
            '^' . $repo_base_url . '/([^/]+)$',
            'index.php?pagename=smliser-repository&smliser_app_type=$matches[1]',
            'top'
        );

        /**
         * Repository app type page matches siteurl/repository/{app_type}/{app_slug}/
         */
        add_rewrite_rule(
            '^' . $repo_base_url . '/([^/]+)/([^/]+)/?$',
            'index.php?pagename=smliser-repository&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]',
            'top'
        );


        /**
         * Asset serving url matchs siteurl/repository/{app_type}/{app_slug}/assets/{filename}
         */
        add_rewrite_rule(
            '^' . $repo_base_url . '/([^/]+)/([^/]+)/assets/(.+)$',
            'index.php?pagename=smliser-repository-assets&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]&smliser_asset_name=$matches[3]',
            'top'
        );

        /*
        |------------------------
        | Software download rules
        |------------------------
        */

        $download_slug = smliser_get_download_slug();

        /**
         * The base downloads page 
         */
        add_rewrite_rule(
            '^' . $download_slug . '/?$',
            'index.php?pagename=smliser-downloads',
            'top'
        );

        /**
         * Downloads category page
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]',
            'top'
        );
        
        /** 
         * License document download rule (specific)
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/([0-9]+)/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]&license_id=$matches[2]',
            'top'
        );

        /** 
         * File Download URI Rule
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/((?![0-9]+$)[^/]+)(?:\.zip)?/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]',
            'top'
        );

        /**OAUTH authorization endpoint */
        add_rewrite_rule(
            '^smliser-auth/v1/authorize$',
            'index.php?smliser_auth=$matches[1]',
            'top'
        );
    }

    /**
     * Plugin Query Variables
     *
     * Adds custom query variables to WordPress recognized query variables.
     *
     * @param array $vars The existing array of query variables.
     * @return array Modified array of query variables.
     */
    public function query_vars( $vars ) {
        
        $vars[] = 'smliser_repository';
        $vars[] = 'smliser_repository_plugin_slug';
        $vars[] = 'license_id';
        $vars[] = 'smliser_app_type';
        $vars[] = 'smliser_auth';
        
        $vars[] = 'smliser_app_slug';
        $vars[] = 'smliser_asset_name';
        
        return $vars;
    }    

    /**
     * Register cron.
     */
    public function register_cron( $schedules ) {
        $schedules = array();
        /** Add a new cron schedule interval for every 3 minutes. */
        $schedules['smliser_three_minutely'] = array(
            'interval' => 3 * MINUTE_IN_SECONDS,
            'display'  => 'Smliser Three Minutely',
        );

        /** Add a new cron schedule interval for every 4 hours. */
        $schedules['smliser_4_hourly'] = array(
            'interval' => 4 * HOUR_IN_SECONDS,
            'display'  => 'Smliser Four Hourly',
        );
        return $schedules;
    }

    /**
     * Schedule event.
     */
    public function run_automation() {
        if ( ! wp_next_scheduled( 'smliser_clean' ) ) {
            wp_schedule_event( time(), 'smliser_4_hourly', 'smliser_clean' );
        }

    }
    
    /**
     * Disable unintended http 301 redirect code during file downloads, this ensures
     * we handle the responses to the download url the proper way instead of the default 301 returned initially
     * by WordPress.
     * 
     * @param string $redirect_url The redirected url.
     * @param string $requested_url The client requested url.
     * @return false|string False when accessing downloads page, perform redirect when not.
     * @since 1.0.0
     */
    public function disable_redirect_on_downloads( $redirect_url, $requested_url ) {
        $download_slug = site_url( smliser_get_download_slug() );
        if ( strpos( $requested_url, $download_slug ) !== false ) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Print admin notices
     */
    public static function print_notice() {
        $repo_version = get_option( 'smliser_repo_version', 0 );
        if ( SMLISER_VER === $repo_version ) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p>Smart License Server requires an update, click <a id="smliser-update-btn" style="cursor: pointer;">HERE</a> to update now.</p>
            <p id="smliser-click-notice" style="display: none">Update started in the backgroud <span class="dashicons dashicons-yes-alt" style="color: blue"></span></p>
        </div>
        <?php
    }

    /**
     * Check filesystem permissions and print admin notice if not writable.
     * 
     * @return void
     */
    private static function check_filesystem_errors() {
        $fs_instance    = SmartLicenseServer\FileSystem::instance();
        $wp_error       = $fs_instance->get_fs()->errors;

        if ( $wp_error->has_errors() ) {
            $error_messages = $wp_error->get_error_messages();
            $messages_html = '';
            foreach ( $error_messages as $message ) {
                $messages_html .= '<code>' . esc_html( $message ) . '</code><br />';
            }

            wp_admin_notice( 
                sprintf(
                    __( 'Smart License Server Filesystem Error: <br/> %s Please ensure the WordPress filesystem is properly configured and writable.', 'smliser' ),
                    $messages_html
                ),
                'error'
            );

        }
    }
}

