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

    /** REST API plugin update route */
    private $update_route = '/software-update/';

    /** REST API license activation route */
    private $activation_route = '/license-validator/';

    /** REST API license deactivation route */
    private $deactivation_route = '/license-deactivator/';

    /** REST API Repository interaction route */
    private $repository_route = '/repository/';
    
    /** 
     * REST API App re-authentication route, essentially for token regeneration.
     * @var string $app_reauth property value for re-authentication route
     */
    private $app_reauth = '/client-auth/';

    /** REST API Repository route for plugin interaction */
    private $repository_plugin_route = '/repository/(?P<slug>[^/]+)/(?P<scope>read|write|read-write)';

    /** Route namespace */
    private $namespace = 'smliser/v1';

    /** Instance of current class */
    private static $instance = null;

    /**
     * Class constructor.
     */
    public function __construct() {
        global $wpdb;
        define( 'SMLISER_LICENSE_TABLE', $wpdb->prefix.'smliser_licenses' );
        define( 'SMLISER_PLUGIN_META_TABLE', $wpdb->prefix . 'smliser_plugin_meta' );
        define( 'SMLISER_LICENSE_META_TABLE', $wpdb->prefix . 'smliser_license_meta' );
        define( 'SMLISER_PLUGIN_ITEM_TABLE', $wpdb->prefix . 'smliser_plugins' );
        define( 'SMLISER_API_ACCESS_LOG_TABLE', $wpdb->prefix . 'smliser_api_access_logs' );
        define( 'SMLISER_API_CRED_TABLE', $wpdb->prefix . 'smliser_api_creds' );
        define( 'SMLISER_REPO_DIR', WP_CONTENT_DIR . '/premium-repository' );
        register_activation_hook( SMLISER_FILE, array( 'Smliser_install', 'install' ) );

        // Register REST endpoints.
        add_action( 'rest_api_init', array( $this, 'rest_load' ) );
        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_post_smliser_bulk_action', array( 'Smliser_license', 'bulk_action') );
        add_action( 'admin_post_smliser_all_actions', array( 'Smliser_license', 'bulk_action') );
        add_action( 'admin_post_smliser_license_new', array( 'Smliser_license', 'license_form_controller') );
        add_action( 'admin_post_smliser_license_update', array( 'Smliser_license', 'license_form_controller' ) );
        add_action( 'admin_post_smliser_plugin_upload', array( 'Smliser_Plugin', 'plugin_upload_controller' ) );
        add_filter( 'query_vars', array( $this, 'download_query_var') );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_https_for_rest_api' ), 10 );

        add_action( 'admin_post_smliser_plugin_action', array( 'Smliser_Plugin', 'action_handler') );
        add_action( 'smliser_stats', array( 'Smliser_Stats', 'action_handler' ), 10, 4 );
        add_action( 'wp_ajax_smliser_key_generate', array( 'Smliser_API_Cred', 'form_handler' ) );
        add_action( 'wp_ajax_smliser_revoke_key', array( 'Smliser_API_Cred', 'revoke' ) );
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

        register_rest_route( $this->namespace, $this->repository_route, array(
            'methods'               => 'GET',
            'callback'              => array( 'Smliser_Server', 'repository_response' ),
            'permission_callback'   => array( 'Smliser_Server', 'repository_access_permission' ),
        ) );

        register_rest_route( $this->namespace, $this->repository_plugin_route, array(
            'methods'               => 'GET',
            'callback'              => array( 'Smliser_Server', 'repository_response' ),
            'permission_callback'   => array( 'Smliser_Server', 'repository_access_permission' ),
        ) );

        register_rest_route( $this->namespace, $this->app_reauth, array(
            'methods'               => 'GET',
            'callback'              => array( 'Smliser_REST_Authentication', 'client_authentication_response' ),
            'permission_callback'   => array( 'Smliser_REST_Authentication', 'auth_permission' ),
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
    public function enforce_https_for_rest_api() {
        // Check if current request belongs to the plugin's namespace.
        if ( ! str_contains( $_SERVER['REQUEST_URI'], $this->namespace ) ) {
            return;
        }

        // Check if environment is production and request is not over HTTPS.
        if ( 'production' === wp_get_environment_type() && ! is_ssl() ) {
            // Create WP_Error object to indicate insecure SSL.
            $error = new WP_Error( 'insecure_ssl', 'HTTPS/TLS is required for secure communication.', array( 'status' => 400, ) );
            
            // Return the WP_Error object.
            return $error;
        }
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
        require_once SMLISER_PATH . 'includes/smliser-rest-api/classr-rest-auth.php';

        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );
        do_action( 'smliser_loaded' );        
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
        add_rewrite_rule(
            '^plugin/([^/]+)/([^/]+)/([^/]+)\.zip$',
            'index.php?plugin_slug=$matches[1]&api_key=$matches[2]&plugin_file=$matches[3]',
            'top'
        );

        add_rewrite_rule(
            'smliser-auth/v1/authorize',
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
     * Plugin Download Query Variable
     */
    public function download_query_var( $vars ) {
        $vars[] = 'plugin_slug';
        $vars[] = 'api_key';
        $vars[] = 'plugin_file';
        $vars[] = 'smliser_auth';
        
        return $vars;
    }
    
}

