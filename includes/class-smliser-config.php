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
        define( 'SMLISER_LICENSE_STATS_TABLE', $wpdb->prefix . 'smliser_license_stats' );
        define( 'SMLISER_PLUGIN_ITEM_TABLE', $wpdb->prefix.'smliser_products' );
        define( 'SMLISER_REPO_DIR', WP_CONTENT_DIR . '/premium-repository' );
        register_activation_hook( SMLISER_FILE, array( 'Smliser_install', 'install' ) );

        // Register REST endpoints.
        add_action( 'rest_api_init', array( $this, 'rest_load' ) );
        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'admin_post_smliser_bulk_action', array( 'Smliser_license', 'bulk_action') );
        add_action( 'admin_post_smliser_license_new', array( 'Smliser_license', 'license_form_controller') );
        add_action( 'admin_post_smliser_license_update', array( 'Smliser_license', 'license_form_controller') );

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

    }

    /**
     * Load styles
     */
    public function load_styles() {
        wp_enqueue_style( 'smliser-styles', SMLISER_URL . 'assets/css/smliser-styles.css', array(), SMLISER_VER, 'all' );
    }
    
}

