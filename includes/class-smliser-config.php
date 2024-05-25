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
        define( 'SMLISER_PRODUCT_TABLE', $wpdb->prefix.'smliser_products' );
        define( 'SMLISER_REPO_DIR', WP_CONTENT_DIR . '/premium-repository' );
        register_activation_hook( SMLISER_FILE, array( 'Smliser_install', 'install' ) );

        // Register REST endpoints.
        add_action( 'rest_api_init', array( $this, 'rest_load' ) );
        add_action( 'plugins_loaded', array( $this, 'include' ) );
        add_action( 'admin_post_smliser_bulk_action', array( 'Smliser_license', 'bulk_action') );

    }

    /** Load or Register our Rest route */
    public function rest_load() {
        /** Register the license validator route */
        register_rest_route( $this->namespace, $this->activation_route, array(
            'methods'             => 'GET',
            'callback'            =>  array( $this, 'validation_response' ),
            'permission_callback' => array( $this, 'validation_permission'),
        ) );

        /** Register the license deactivation route */
        register_rest_route( $this->namespace, $this->deactivation_route, array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'deactivation_response' ),
            'permission_callback' => array( $this, 'deactivation_permission' ),
        ) );

        /** Register the software update route */
        register_rest_route( $this->namespace, $this->update_route, array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'update_response' ),
            'permission_callback' => array( $this, 'update_permission' ),
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
     * Check license activation permission
     */
    public function validation_permission( $request ) {
        // Get the Authorization header from the request.
        $authorization_header = $request->get_header( 'authorization' );
        $service_id     =  $request->get_param( 'service_id' );
        $item_id        =  $request->get_param( 'item_id' );
        $license_key    =  $request->get_param( 'license_key' );
        $callback_url   =  $request->get_param( 'callback_url' );
        
        /**
         * Authorization token in this regard is the token from the client
         * server that they can use to validate our response in other to avoid CSRF and XSS.
         * Basically, we ensure the security of both our server and the client's too.
         */
        if ( empty( $authorization_header ) ) {
           return false;
        }

        $authorization_parts = explode( ' ', $authorization_header );

        // Additional checks.
        if ( count( $authorization_parts ) !== 2 && $authorization_parts[0] !== 'Bearer' ) {
           return false;
        }

        // All required parameters must be met.
        if ( 
            empty( $service_id ) 
            || empty( $item_id ) 
            || empty( $license_key ) 
            || empty( $callback_url ) 
            ) {
            return false;
        }

        // We need to ensure the param inputs are not ill-intended.
         if ( 
            $service_id !== sanitize_text_field( $service_id )
            || $item_id !== sanitize_text_field( $item_id )
            || $license_key !== sanitize_text_field( $license_key ) 
            || ! filter_var( $callback_url, FILTER_VALIDATE_URL )
        ) {
            return false;
        }
        
        /** 
         * Since the basic requirement and validation convention is met,
         * client can access this endpoint.
         */
        return true;
    }

    /**
     * Handling immediate response to validation request.
     */
    public function validation_response( $request ) {
        $request_params = $request->get_params();
        $service_id     = $request_params['service_id'];
        $license_key    = $request_params['license_key'];
        $item_id        = $request_params['item_id'];
        $callback_url   = $request_params['callback_url'];
        $token          = $this->get_auth_token( $request );
        $license_data   = get_license_data( $service_id, $license_key, $item_id );
        
        if ( ! $license_data ) {
            $response_data = array(
                'code'      => 'license_error',
                'message'   => 'Invalid License key or service ID'
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }
    
        if ( $license_data['expiry_date'] < current_time( 'Y-m-d' ) ) {
            $response_data = array(
                'code'      => 'license_expired',
                'message'   => 'License has expired, log into you account to renew your license'
            );
            $response = new WP_REST_Response( $response_data, 402 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }
    
        $encoded_data   = wp_json_encode( $license_data );
        $waiting_period = smliser_wait_period();
        $local_duration = preg_replace( '/\D/', '', $waiting_period );
        // add new task.
       $license_server = new smartwoo_license_server();
        $license_server->add_task_queue(
            $local_duration, array(
                'licence_key'   => $license_key,
                'token'         => $token,
                'expiry_date'   => $license_data['expiry_date'],
                'callback_url'  => $callback_url,
                'data'          => $encoded_data
    
            )
        );
    
    
        $response_data = array(
            'waiting_period'    => $waiting_period,
            'message'           => 'License is being validated',
        );
        $response = new WP_REST_Response( $response_data, 200 );
        return $response;
    }

    /**
     * Extract the token from the authorization header.
     * 
     * @param WP_REST_Request $request The current request object.
     * @return string|null The extracted token or null if not found.
     */
    private function get_auth_token( $request ) {
        // Get the authorization header.
        $headers = $request->get_headers();
        
        // Check if the authorization header is set
        if ( isset( $headers['authorization'] ) ) {
            $auth_header = $headers['authorization'][0];
            
            // Extract the token using a regex match for Bearer token.
            if ( preg_match( '/Bearer\s(\S+)/', $auth_header, $matches ) ) {
                return $matches[1]; // Return the token
            }
        }
        
        // Return null if no valid token is found.
        return null;
    }

    /**
     * Include files
     */
    public function include() {
        require_once SMLISER_PATH . 'includes/utils/smliser-functions.php';
        require_once SMLISER_PATH . 'includes/class-smliser.php';
        require_once SMLISER_PATH . 'includes/class-smliser-menu.php';
        require_once SMLISER_PATH . 'includes/class-smliser-repository.php';
        require_once SMLISER_PATH . 'includes/class-smlicense.php';

        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_styles' ) );
        do_action( 'smliser_loaded' );
        $result = new Smliser_license();
$result->set_user_id( 1 );
$result->set_service_id( 'Smwoopro-DE653y848' );
$result->set_license_key( '', 'new' );
$result->set_item_id( 34556 );
$result->set_status( 'Active' );
$result->set_start_date( '2024-02-24' );
$result->set_end_date( '2024-05-24' );
//$result->save();
        
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



