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

    /** REST API software update route */
    private $update_route = '/software-update/';

    /** REST API software activation route */
    private $activation_route = '/license-validator/';

    /** REST API sofware deactivation route */
    private $deactivation_route = '/license-deactivator/';

    /** Route namespace */
    private $namespace = 'smartwoo-api/v1';

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
        add_action( 'admin_post_smliser_plugin_action', array( 'Smliser_Plugin', 'action_handler') );
        add_action( 'smliser_stats', array( 'Smliser_Stats', 'action_handler' ), 10, 4 );
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
        wp_enqueue_script( 'smliser-chart', 'https://cdn.jsdelivr.net/npm/chart.js', array(), SMLISER_VER, true );

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
        add_filter( 'cron_schedules', array( $this, 'register_cron' ) );
        $this->run_automation();
    }

    /**
     * Register cron.
     */
    public function register_cron( $schedules ) {
        /** Add a new cron schedule interval for every 5 minutes. */
        $schedules['smliser_five_minutely'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'Five Minutely',
        );
        return $schedules;
    }

    /**
     * Schedule event.
     */
    public function run_automation() {

        if ( ! wp_next_scheduled( 'smliser_validate_license' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'smliser_five_minutely', 'smliser_validate_license' );
		}

    }

    /**
     * Plugin Download Query Variable
     */
    public function download_query_var( $vars ) {
        $vars[] = 'plugin_slug';
        $vars[] = 'api_key';
        $vars[] = 'plugin_file';
        
        return $vars;
    }
    
}

