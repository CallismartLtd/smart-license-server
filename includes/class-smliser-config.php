<?php
/**
 * file name class-license-server-config.php
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
     * License activation REST API route.
     * 
     * @var string
     */
    private $activation_route = '/license-activation/';

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
    private $license_validity_route = '/license-validity-test/';

    /** 
     * Repository REST API route.
     * 
     * @var string
     */
    private $repository_route = '/repository/';

    /** 
     * Repository REST API route for specific plugin.
     * 
     * @var string
     */
    private $repository_plugin_route = '/repository/(?P<slug>[^/]+)/(?P<scope>read|write|read-write)';
    
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
    private $download_reauth = '/download-token-reauthentication/';

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
        define( 'SMLISER_REPOSITORY_ROUTE', $this->repository_route );

        global $wpdb;
        define( 'SMLISER_LICENSE_TABLE', $wpdb->prefix.'smliser_licenses' );
        define( 'SMLISER_PLUGIN_META_TABLE', $wpdb->prefix . 'smliser_plugin_meta' );
        define( 'SMLISER_THEME_META_TABLE', $wpdb->prefix . 'smliser_theme_meta' );
        define( 'SMLISER_LICENSE_META_TABLE', $wpdb->prefix . 'smliser_license_meta' );
        define( 'SMLISER_PLUGIN_ITEM_TABLE', $wpdb->prefix . 'smliser_plugins' );
        define( 'SMLISER_THEME_ITEM_TABLE', $wpdb->prefix . 'smliser_themes' );
        define( 'SMLISER_APPS_ITEM_TABLE', $wpdb->prefix . 'smliser_applications' );
        define( 'SMLISER_APPS_META_TABLE', $wpdb->prefix . 'smliser_applications_meta' );
        define( 'SMLISER_API_ACCESS_LOG_TABLE', $wpdb->prefix . 'smliser_api_access_logs' );
        define( 'SMLISER_API_CRED_TABLE', $wpdb->prefix . 'smliser_api_creds' );
        define( 'SMLISER_DOWNLOAD_TOKEN_TABLE', $wpdb->prefix . 'smliser_item_download_token' );
        define( 'SMLISER_MONETIZATION_TABLE', $wpdb->prefix . 'smliser_monetization' );
        define( 'SMLISER_PRICING_TIER_TABLE', $wpdb->prefix . 'smliser_pricing_tiers' );

        define( 'SMLISER_REPO_DIR', WP_CONTENT_DIR . '/premium-repository' );
        define( 'SMLISER_NEW_REPO_DIR', WP_CONTENT_DIR . '/smliser-repo' );
        define( 'SMLISER_PLUGINS_REPO_DIR', SMLISER_NEW_REPO_DIR . '/plugins' );
        define( 'SMLISER_THEMES_REPO_DIR', SMLISER_NEW_REPO_DIR . '/themes' );
        
        register_activation_hook( SMLISER_FILE, array( 'Smliser_install', 'install' ) );

        // Register REST endpoints.
        add_action( 'rest_api_init', array( $this, 'rest_load' ) );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https_for_rest_api' ), 10, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'filter_rest_response' ), 10, 3 );
        add_filter( 'redirect_canonical', array( $this, 'disable_redirect_on_downloads' ), 10, 2 );
        add_filter( 'query_vars', array( $this, 'query_vars') );
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );

        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_post_smliser_bulk_action', array( 'Smliser_license', 'bulk_action') );
        add_action( 'admin_post_smliser_all_actions', array( 'Smliser_license', 'bulk_action') );
        add_action( 'admin_post_smliser_license_new', array( 'Smliser_license', 'license_form_controller') );
        add_action( 'admin_post_smliser_license_update', array( 'Smliser_license', 'license_form_controller' ) );
        add_action( 'admin_post_smliser_plugin_upload', array( 'Smliser_Plugin', 'plugin_upload_controller' ) );
        add_action( 'admin_post_smliser_admin_download_plugin', array( 'Smliser_Server', 'serve_admin_download' ) );
        add_action( 'admin_notices', array( __CLASS__, 'print_notice' ) );
        add_action( 'wp_ajax_smliser_plugin_action', array( 'Smliser_Plugin', 'action_handler' ) );
        add_action( 'wp_ajax_smliser_upgrade', array( 'Smliser_Install', 'ajax_update' ) );
        add_action( 'admin_post_nopriv_smliser_oauth_login', array( 'Smliser_API_Cred', 'oauth_login_form_handler' ) );
        add_action( 'smliser_stats', array( 'Smliser_Stats', 'action_handler' ), 10, 4 );
        add_action( 'wp_ajax_smliser_key_generate', array( 'Smliser_API_Cred', 'admin_create_cred_form' ) );
        add_action( 'wp_ajax_smliser_revoke_key', array( 'Smliser_API_Cred', 'revoke' ) );
        add_action( 'wp_ajax_smliser_token_gen_form', array( 'Smliser_Plugin_Download_Token', 'ajax_token_form' ) );
        add_action( 'wp_ajax_smliser_generate_item_token', array( 'Smliser_Plugin_Download_Token', 'get_new_token' ) );
        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );
        add_action( 'smliser_clean', array( 'Smliser_Plugin_Download_Token', 'clean_expired_tokens' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );
    }

    /** Load or Register our Rest route */
    public function rest_load() {
        
        /** 
         * Register the license activation route. 
         */
        register_rest_route( $this->namespace, $this->activation_route, 
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            =>  array( 'Smliser_License_Rest_API', 'license_activation_response' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_activation_permission_callback'),
                'args'  => array(
                    'item_id'   => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'description'       => 'The ID of the item associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'is_int' ),
                    ),

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
                'callback'            => array( 'Smliser_License_Rest_API', 'license_deactivation_response' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_deactivation_permission' ),
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
                'callback'            => array( 'Smliser_License_Rest_API', 'license_uninstallation_response' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_uninstallation_permission' ),
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
                'callback'            => array( 'Smliser_License_Rest_API', 'license_validity_test' ),
                'permission_callback' => array( 'Smliser_License_Rest_API', 'license_validity_test_permission' ),
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
                    ),
                    'item_id'       => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'description'       => 'The ID of the software this license is associated with.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'is_int' ),
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
            'permission_callback' => array( 'Smliser_Plugin_REST_API', 'plugin_info_permission_callback' ),
            'callback'            => array( 'Smliser_Plugin_REST_API', 'plugin_info' ),
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

        /** 
         * Register REST API route for querying entire repository
         */
        register_rest_route( $this->namespace, $this->repository_route, 
            array(
                'methods'               => WP_REST_Server::READABLE,
                'callback'              => array( 'Smliser_Repository_Rest_API', 'repository_response' ),
                'permission_callback'   => array( 'Smliser_Repository_Rest_API', 'repository_access_permission' ),
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
                'callback'              => array( 'Smliser_License_Rest_API', 'item_download_reauth' ),
                'permission_callback'   => array( 'Smliser_License_Rest_API', 'item_download_reauth_permission' ),
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
                    'item_id'   => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'description'       => 'The ID of the item associated with the license.',
                        'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                        'validate_callback' => array( __CLASS__, 'is_int' ),
                    ),
                    'download_token'    => array(
                        'required'         => true,
                        'type'              => 'string',
                        'description'       => 'The base64 encoded download token issued during license activation.',
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
     * Set Props
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
     */
    public static function initialize_plugin_context( $response, $handler, $request ) {
        // Ensure this request is for our route
        if ( ! str_contains( $request->get_route(), self::instance()->namespace ) ) {
            return $response;
        }

        if ( is_wp_error( $response ) ) {
            remove_filter( 'rest_post_dispatch', 'rest_send_allow_header' );
        }
        if ( is_null( self::instance()->plugin ) ) {
            self::instance()->plugin   = Smliser_Plugin::instance();            
        }

        if ( is_null( self::instance()->license ) ) {
            self::instance()->license  = Smliser_license::instance();

        }

        return $response;
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
        require_once SMLISER_PATH . 'includes/hosted-apps/hosted-apps-interface.php';
        require_once SMLISER_PATH . 'includes/hosted-apps/class-software-collection.php';
        require_once SMLISER_PATH . 'includes/monetization/provider-interface.php';
        require_once SMLISER_PATH . 'includes/utils/smliser-functions.php';
        require_once SMLISER_PATH . 'includes/utils/smliser-formating-functions.php';
        require_once SMLISER_PATH . 'includes/utils/class-callismart-encryption.php';
        require_once SMLISER_PATH . 'includes/utils/class-callismart-markdown-parser.php';
        require_once SMLISER_PATH . 'includes/admin/class-menu.php';
        require_once SMLISER_PATH . 'includes/admin/class-admin-dashboard.php';
        require_once SMLISER_PATH . 'includes/admin/class-repository-page.php';
        require_once SMLISER_PATH . 'includes/admin/class-admin-license-page.php';
        require_once SMLISER_PATH . 'includes/admin/class-admin-options-page.php';
        require_once SMLISER_PATH . 'includes/class-smliser-repository.php';
        require_once SMLISER_PATH . 'includes/class-smliser-plugin.php';
        require_once SMLISER_PATH . 'includes/class-smlicense.php';
        require_once SMLISER_PATH . 'includes/class-smliser-server.php';
        require_once SMLISER_PATH . 'includes/class-smliser-stats.php';
        require_once SMLISER_PATH . 'includes/class-smliser-api-cred.php';
        require_once SMLISER_PATH . 'includes/class-smliser-plugin-download-token.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-rest-auth.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-smliser-license-rest-api.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-smliser-plugin-rest-api.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/class-smliser-repository-rest-api.php';
        require_once SMLISER_PATH . 'includes/monetization/class-monetization.php';
        require_once SMLISER_PATH . 'includes/monetization/class-pricing-tier.php';
        require_once SMLISER_PATH . 'includes/monetization/provider-collection.php';
        require_once SMLISER_PATH . 'includes/monetization/class-woocomerce-provider.php';
        require_once SMLISER_PATH . 'includes/monetization/class-controller.php';
        
        do_action( 'smliser_loaded' );
        
    }

    /**
     * Load Scripts
     */
    public function load_scripts() {
        wp_enqueue_script( 'smliser-script', SMLISER_URL . 'assets/js/main-script.js', array( 'jquery' ), SMLISER_VER, true );
        if ( is_admin() ) {
            wp_enqueue_script( 'smliser-chart', SMLISER_URL . 'assets/js/chart.js', array(), SMLISER_VER, true );
        }

        // Script localizer.
        wp_localize_script(
            'smliser-script',
            'smliser_var',
            array(
                'smliser_ajax_url'  => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'smliser_nonce' ),
                'admin_url'         => admin_url()
            )
        );

    }

    /**
     * Load styles
     */
    public function load_styles() {
        wp_enqueue_style( 'smliser-styles', SMLISER_URL . 'assets/css/smliser-styles.css', array(), SMLISER_VER, 'all' );
        wp_enqueue_style( 'smliser-form-styles', SMLISER_URL . 'assets/css/smliser-forms.css', array(), SMLISER_VER, 'all' );
    }

    /**
     * Init hooks
     */
    public function init_hooks() {
        $this->run_automation();
        $repo_base_url = get_option( 'smliser_repo_base_perma', 'repository' );
    
        add_rewrite_rule(
            '^' . $repo_base_url . '$',
            'index.php?smliser_repository=1',
            'top'
        );
    
        add_rewrite_rule(
            '^' . $repo_base_url . '/([^/]+)$',
            'index.php?smliser_repository=1&smliser_repository_plugin_slug=$matches[1]',
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
            'index.php?pagename=smliser-downloads&software_category=$matches[1]',
            'top'
        );
        
        /** 
         * File Download URI Rule 
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/([^/]+)\.zip/?$',
            'index.php?pagename=smliser-downloads&software_category=$matches[1]&file_slug=$matches[2]',
            'top'
        );

        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/([^/]+)/?$',
            'index.php?pagename=smliser-downloads&software_category=$matches[1]&license_id=$matches[2]',
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
        $vars[] = 'file_slug';
        $vars[] = 'license_id';
        $vars[] = 'software_category';
        $vars[] = 'smliser_auth';
        
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
        if ( ! wp_next_scheduled( 'smliser_validate_license' ) ) {
			wp_schedule_event( time(), 'smliser_three_minutely', 'smliser_validate_license' );
		}

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
     * Encapsulted sanitization function for REST param.
     * 
     * @param mixed $value The value to sanitize.
     */
    public static function sanitize( $value ) {
        if ( is_string( $value ) ) {
            $value = sanitize_text_field( wp_unslash( $value ) );
        } elseif ( is_array( $value ) ) {
            $value = array_map( 'sanitize_text_field', wp_unslash( $value ) );
        } elseif ( is_int( $value ) ) {
            $value = absint( $value );
        } elseif ( is_float( $value ) ) {
            $value = floatval( $value );
        }

        return $value;
    }

    /**
     * Sanitize a URL for REST API validation.
     * 
     * @param string $url The URL to sanitize.
     * @return string Sanitized URL.
     */
    public static function sanitize_url( $url ) {
        $url = esc_url_raw( $url );
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }
        return $url;
    }

    /**
     * Check whether a value is empty for REST API validation.
     * @param string $value The value to check.
     * @return bool true if not empty, false otherwise.
     */
    public static function not_empty( $value ) {
        if ( empty( $value ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value cannot be empty.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Validate if the given URL is an HTTP or HTTPS URL.
     *
     * @param string $url The URL to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $param The parameter name.
     * @return true|WP_Error
     */
    public static function is_url( $url, $request, $param ) {
        if ( empty( $url ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The domain parameter is required.', 'smliser' ), array( 'status' => 400 ) );
        }

        $url        = self::sanitize_url( $url );
        $parsed_url = wp_parse_url( $url );
        if ( ! is_array( $parsed_url ) || empty( $parsed_url['scheme'] ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }

        if ( ! in_array( strtolower( $parsed_url['scheme'] ), array( 'http', 'https' ), true ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'Only HTTP and HTTPS URLs are allowed.', 'smliser' ), array( 'status' => 400 ) );
        }

        return true;
    }

    /**
     * Validate if a given REST API parameter is an integer.
     * 
     * @param mixed $value The value to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $key The parameter name.
     * @return true|WP_Error Returns true if the value is an integer, otherwise returns a WP_Error object.
     */
    public static function is_int( $value, $request, $key ) {
        if ( ! is_numeric( $value ) || intval( $value ) != $value ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value must be an integer.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
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
}

