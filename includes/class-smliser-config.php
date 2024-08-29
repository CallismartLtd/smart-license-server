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
     * Plugin update REST API route.
     * 
     * @var string
     */
    private $update_route   = '/software-update/';

    /** 
     * License activation REST API route.
     * 
     * @var string
     */
    private $activation_route = '/license-validator/';

    /** 
     * License deactivation REST API route
     * 
     * @var string
     */
    private $deactivation_route = '/license-deactivator/';

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
    private $download_reauth = '/item-token-reauth/';

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
        define( 'SMLISER_LICENSE_META_TABLE', $wpdb->prefix . 'smliser_license_meta' );
        define( 'SMLISER_PLUGIN_ITEM_TABLE', $wpdb->prefix . 'smliser_plugins' );
        define( 'SMLISER_API_ACCESS_LOG_TABLE', $wpdb->prefix . 'smliser_api_access_logs' );
        define( 'SMLISER_API_CRED_TABLE', $wpdb->prefix . 'smliser_api_creds' );
        define( 'SMLISER_REPO_DIR', WP_CONTENT_DIR . '/premium-repository' );
        define( 'SMLISER_DOWNLOAD_TOKEN_TABLE', $wpdb->prefix . 'smliser_item_download_token' );
        
        register_activation_hook( SMLISER_FILE, array( 'Smliser_install', 'install' ) );

        // Register REST endpoints.
        add_action( 'rest_api_init', array( $this, 'rest_load' ) );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https_for_rest_api' ), 10, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'rest_signature_headers' ), 10, 3 );
        add_filter( 'redirect_canonical', array( $this, 'disable_redirect_on_downloads' ), 10, 2 );
        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_post_smliser_bulk_action', array( 'Smliser_license', 'bulk_action') );
        add_action( 'admin_post_smliser_all_actions', array( 'Smliser_license', 'bulk_action') );
        add_action( 'admin_post_smliser_license_new', array( 'Smliser_license', 'license_form_controller') );
        add_action( 'admin_post_smliser_license_update', array( 'Smliser_license', 'license_form_controller' ) );
        add_action( 'admin_post_smliser_plugin_upload', array( 'Smliser_Plugin', 'plugin_upload_controller' ) );
        add_action( 'admin_post_smliser_admin_download_plugin', array( 'Smliser_Server', 'serve_admin_download' ) );
        add_filter( 'query_vars', array( $this, 'query_vars') );
        add_action( 'admin_post_smliser_plugin_action', array( 'Smliser_Plugin', 'action_handler' ) );
        add_action( 'admin_post_nopriv_smliser_oauth_login', array( 'Smliser_API_Cred', 'oauth_login_form_handler' ) );
        add_action( 'smliser_stats', array( 'Smliser_Stats', 'action_handler' ), 10, 4 );
        add_action( 'wp_ajax_smliser_key_generate', array( 'Smliser_API_Cred', 'admin_create_cred_form' ) );
        add_action( 'wp_ajax_smliser_revoke_key', array( 'Smliser_API_Cred', 'revoke' ) );
        add_action( 'wp_ajax_smliser_token_gen_form', array( 'Smliser_Plugin_Download_Token', 'ajax_token_form' ) );
        add_action( 'wp_ajax_smliser_generate_item_token', array( 'Smliser_Plugin_Download_Token', 'get_new_token' ) );
        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );
    }

    /** Load or Register our Rest route */
    public function rest_load() {
        
        /** Register the license validator route */
        register_rest_route( $this->namespace, $this->activation_route, array(
            'methods'             => 'GET',
            'callback'            =>  array( 'Smliser_Server', 'validation_response' ),
            'permission_callback' => array( 'Smliser_Server', 'validation_permission'),
        ) );

        /** Register the license deactivation route */
        register_rest_route( $this->namespace, $this->deactivation_route, array(
            'methods'             => 'GET',
            'callback'            => array( 'Smliser_Server', 'deactivation_response' ),
            'permission_callback' => array( 'Smliser_Server', 'deactivation_permission' ),
        ) );

        /** Register the software update route */
        register_rest_route( $this->namespace, $this->update_route, array(
            'methods'             => 'GET',
            'callback'            => array( 'Smliser_Server', 'update_response' ),
            'permission_callback' => array( 'Smliser_Server', 'update_permission' ),
        ) );

        /**Register REST API route for querying entire repository*/
        register_rest_route( $this->namespace, $this->repository_route, array(
            'methods'               => array( 'GET', 'POST'),
            'callback'              => array( 'Smliser_Server', 'repository_response' ),
            'permission_callback'   => array( 'Smliser_Server', 'repository_access_permission' ),
        ) );

        /** Register REST API Route for querying a specific plugin */
        register_rest_route( $this->namespace, $this->repository_plugin_route, array(
            'methods'               => array( 'GET', 'POST'),
            'callback'              => array( 'Smliser_Server', 'repository_response' ),
            'permission_callback'   => array( 'Smliser_Server', 'repository_access_permission' ),
        ) );

        /** Register Oauth client authentication route */
        register_rest_route( $this->namespace, $this->app_reauth, array(
            'methods'               => 'GET',
            'callback'              => array( 'Smliser_REST_Authentication', 'client_authentication_response' ),
            'permission_callback'   => array( 'Smliser_REST_Authentication', 'auth_permission' ),
        ) );

        /** Register licensed plugin download token regeneration route */
        register_rest_route( $this->namespace, $this->download_reauth, array(
            'methods'               => 'GET',
            'callback'              => array( 'Smliser_REST_Authentication', 'item_download_reauth' ),
            'permission_callback'   => array( 'Smliser_REST_Authentication', 'item_download_reauth_permission' ),
        ) );
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
     * Add custom headers to REST API responses.
     *
     * @param WP_REST_Response $response The REST API response object.
     * @param WP_REST_Server   $server   The REST server object.
     * @param WP_REST_Request  $request  The REST request object.
     * @return WP_REST_Response Modified REST API response object.
     */
    public function rest_signature_headers( $response, $server, $request ) {
        
        if ( strpos( $request->get_route(), $this->namespace )  ) {
            $response->header( 'x-plugin-name', 'Smart License Server' );
            $response->header( 'x-api', 'Smart License Server API' );
            $response->header( 'x-plugin-version', SMLISER_VER );
            $response->header( 'x-api-version', 'v1' );
            $response->header( 'x-powered-By', 'Callistus Nwachukwu' );
        }

        return $response;
    }

    /**
     * Instanciate the current class.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
    }

    /**
     * Include files
     */
    public function include() {
        require_once SMLISER_PATH . 'includes/utils/smliser-functions.php';
        require_once SMLISER_PATH . 'includes/utils/smliser-formating-functions.php';
        require_once SMLISER_PATH . 'includes/class-smliser-server.php';
        require_once SMLISER_PATH . 'includes/class-smliser-menu.php';
        require_once SMLISER_PATH . 'includes/class-smliser-repository.php';
        require_once SMLISER_PATH . 'includes/class-smliser-plugin.php';
        require_once SMLISER_PATH . 'includes/class-smlicense.php';
        require_once SMLISER_PATH . 'includes/class-smliser-stats.php';
        require_once SMLISER_PATH . 'includes/class-smliser-api-cred.php';
        require_once SMLISER_PATH . 'includes/class-smliser-item-download-token.php';
        require_once SMLISER_PATH . 'includes/smliser-rest-api/classr-rest-auth.php';

        add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );
        do_action( 'smliser_loaded' );

        // wp_send_json_success( array( 'token' => base64_encode( 'youfrhdn' ) ) );
        // var_dump( smliser_verify_item_token( 'c21saXNlcl80OGUwMDg0NWQwODNlN2YzOWE3NTUzZmVlOGU2ZWI3Y2VhZDBkMDZjNGQ3YTRmMmJiNDY0NGFkOGMzMTdiN2My', 1 ) );
        // echo wp_date( 'Y-m-d', 1724925664 );

    }

    /**
     * Load Scripts
     */
    public function load_scripts() {
        wp_enqueue_script( 'smliser-script', SMLISER_URL . 'assets/js/forms.js', array( 'jquery' ), SMLISER_VER, true );
        if ( defined( 'SMLISER_ADMIN_PAGE' ) ) {
            wp_enqueue_script( 'smliser-chart', SMLISER_URL . 'assets/js/chart.js', array(), SMLISER_VER, true );
        }

        // Script localizer.
        wp_localize_script(
            'smliser-script',
            'smliser_var',
            array(
                'smliser_ajax_url'  => admin_url( 'admin-ajax.php' ),
                'nonce'             => wp_create_nonce( 'smliser_nonce' ),
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
        $repo_base_url = get_option( 'smliser_repo_base_perma', 'plugins' );
    
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
        | Plugin download rules
        |------------------------
        */

        $download_slug = smliser_get_download_slug();
        /**
         * Plugin download base pagename.
         */
        add_rewrite_rule(
            '^' . $download_slug . '$',
            'index.php?smliser_repository_download_page=1',
            'top'
        );
        
        /** Plugin Download URI Rule */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/([^/]+)\.zip/?$',
            'index.php?smliser_repository_download_page=1&plugin_slug=$matches[1]&plugin_file=$matches[2]',
            'top'
        );

        /**Licensed Plugin Download URI Rule */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/([^/]+)/([^/]+)\.zip/?$',
            'index.php?smliser_repository_download_page=1&plugin_slug=$matches[1]&download_token=$matches[2]&plugin_file=$matches[3]',
            'top'
        );

        /**OAUTH authorization endpoint */
        add_rewrite_rule(
            '^smliser-auth/v1/authorize$',
            'index.php?smliser_auth=$matches[1]',
            'top'
        );
        
        
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );
        $this->run_automation();
    }

    /**
     * Register cron.
     */
    public function register_cron( $schedules ) {
        /** Add a new cron schedule interval for every 3 minutes. */
        $schedules['smliser_three_minutely'] = array(
            'interval' => 3 * MINUTE_IN_SECONDS,
            'display'  => 'Smliser Three Minutely',
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

        $vars[] = 'smliser_repository_download_page';
        $vars[] = 'plugin_slug';
        $vars[] = 'download_token';
        $vars[] = 'plugin_file';
        $vars[] = 'smliser_auth';
        
        return $vars;
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
}

