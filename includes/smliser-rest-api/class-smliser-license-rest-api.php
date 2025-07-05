<?php
/**
 * The license REST API class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles all the REST API for the license endpoints.
 */
class Smliser_License_Rest_API {
    /**
     * The current license object
     * 
     * @var Smliser_license $license
     */
    protected $license;

    /**
     * The current REST API user object.
     * 
     * @var Smliser_Rest_User $user
     */
    protected $user;

    /**
     * Activation tasks.
     * 
     * @param array $tasks.
     */
    protected $tasks = array();

    /**
     * Instance of the class.
     * 
     * @var Smliser_License_Rest_API $instance
     */
    private static $instance = null;

    /**
     * A flag to monitor response duration.
     * 
     * @var int $start_time
     */
    protected $start_time = 0;

    /**
     * Class constructor.
     */
    private function __construct() {
        $this->license = new Smliser_license();

    }
    
    /**
     * Singleton instance of the class.
     * 
     * @return self
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * The new license activation route permission callback.
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function license_activation_permission_callback( WP_REST_Request $request ) {
        self::instance();
        self::$instance->start_time = microtime( true );
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $item_id        = $request->get_param( 'item_id' );
        $domain         = $request->get_param( 'domain' );

        $license        = self::$instance->license->get_license_data( $service_id, $license_key );

        if ( ! $license ) {
 
            $response = new WP_Error( 'license_error', 'Invalid license', array( 'status' => 404 ) );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 404,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $response->get_error_message() )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
    
            return $response;
        }

        self::$instance->license = $license;
        $status_access  = $license->can_serve_license( $item_id, 'license activation', $domain );
        $domain_access  = self::restrict_domain_activation( $domain, $request );

        $access_denied  = is_wp_error( $status_access ) || is_wp_error( $domain_access );

        if ( $access_denied  ) {
            $error = is_wp_error( $status_access ) ? $status_access : $domain_access;
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => $error->get_error_data( 'status' ) ?: 403,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $error->get_error_message() )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
    
            return $error;
        }

        return true;
    }

    /**
     * Responds to license activation request.
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function license_activation_response( WP_REST_Request $request ) {
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $item_id        = $request->get_param( 'item_id' );
        $domain         = $request->get_param( 'domain' );
        $license        = self::instance()->license;
        $encoded_data   = $license->encode();

        if ( is_wp_error( $encoded_data ) ) {
            $response_data = array(
                'code'      => 'license_server_busy',
                'message'   => 'Server is currently busy please retry. Contact support if the issue persists.'
            );

            $response = new WP_REST_Response( $response_data, 503 );
            $reasons = array(
                '',
                array( 
                    'route'         => 'license-validator',
                    'status_code'   => 503,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $response_data['message'] )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
            $response->header( 'content-type', 'application/json' );
    
            return $response;
        }
        
        /**
         * Handles stats sycronization.
         * 
         * @param string $context The context which the hook is fired.
         * @param Smliser_Plugin The plugin object (optional).
         * @param Smliser_License The license object (optional).
         * @param array $additional An associative array(callable => arg)
         *                          only one argument is passed to the callback function
         */
        // do_action( 'smliser_stats', 'license_activation', '', $license, $additional );

        $response_data  = array(
            'item_id'           => $item_id,
            'license_key'       => $license_key,
            'service_id'        => $service_id,
            'download_token'    => smliser_generate_item_token( $license->get_item_id(), $license_key, 2 * DAY_IN_SECONDS ),
            'token_expiry'      => wp_date( 'Y-m-d', time() + ( 2 * DAY_IN_SECONDS ) ),
            'license_expiry'    => $license->get_end_date()
        );

        if ( $license->is_new_domain( $domain ) && ! $license->has_reached_max_allowed_sites() ) {
            $token  = 'sc_' .bin2hex( random_bytes( 32 ) );
            $response_data['site_secret'] = base64_encode( $token );
            $license->update_active_sites( $domain, $token );
        }

        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'content-type', 'application/json' );

        $license_data   = array(
            'license_id'    => $license->get_id(),
            'ip_address'    => smliser_get_client_ip(),
            'website'       => $domain,
            'comment'       => 'License activated',
            'duration'      => microtime( true ) - self::$instance->start_time
        );

        $license->log_activation( $license_data );
        return $response;
    }
    
    /**
     * License deactivation permission.
     * We issued an api key as part of the license document during activation and subsequent reauth, same key will be required
     * during remote deactivation.
     * 
     * @param WP_REST_Request $request
     */
    public static function license_deactivation_permission( WP_REST_Request $request ) {
        self::instance();
        self::$instance->start_time = microtime( true );
        $license_key    = $request->get_param( 'license_key' );
        $service_id     = $request->get_param( 'service_id' );
        
        $license        = self::$instance->license->get_license_data( $service_id, $license_key );
        
        if ( ! $license ) {
            return new WP_Error( 'invalid_license', 'The provided license does not exists.', array( 'status' => 404 ) );
        }

        self::$instance->license = $license;
        return true;
    }

    /**
     * License deactivation route handler.
     * 
     * @param WP_REST_Request $request
     */
    public static function license_deactivation_response( WP_REST_Request $request ) {
        $license_key    = $request->get_param( 'license_key' );
        $service_id     = $request->get_param( 'service_id' );
        $website_name   = smliser_get_base_url( $request->get_param( 'domain' ) );
        $obj            = self::instance()->license;
        $original_status = $obj->get_status();
        $obj->set_status( 'Deactivated' );
        
        if ( $obj->save() ) {
            $response_data = array(
                'success'   => true,
                'message'   => 'License has been deactivated',
                'data'  => array(
                    'license_status'    => $obj->get_status(),
                    'date'              => gmdate( 'Y-m-d' )
                ),
            );
            $obj->set_action( $website_name );
            $additional = array( 'args' => $response_data );
            /**
             * Fires for stats syncronization.
             * 
             * @param string $context The context which the hook is fired.
             * @param string Empty field for license object.
             * @param Smliser_License The license object (optional).
             */
            do_action( 'smliser_stats', 'license_deactivation', '', $obj, $additional );
        } else {
            $response_data = array(
                'success'    => false,
                'message'   => 'Unable to process this request at the moment.',
                'data'  => array(
                    'license_status'    => $original_status,
                    'date'              => gmdate( 'Y-m-d' )
                ),
            );
        }
        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'content-type', 'application/json' );

        return $response;
    }

    /**
     * Download token reauthentication for licensed plugins.
     */
    public static function item_download_reauth_permission( WP_REST_Request $request ) {
        self::instance();
        self::$instance->start_time = microtime( true );
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );


        $license        = self::$instance->license->get_license_data( $service_id, $license_key );

        if ( ! $license ) {
 
            $response = new WP_Error( 'license_error', 'Invalid license', array( 'status' => 404 ) );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => 404,
                    'request_data'  => 'license validation response',
                    'response_data' => array( 'reason' => $response->get_error_message() )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
    
            return $response;
        }

        self::$instance->license = $license;
        $item_id        = $request->get_param( 'item_id' );
        $domain         = $request->get_param( 'domain' );
        $status_access  = $license->can_serve_license( $item_id, 'license activation', $domain );
        $domain_access  = self::verify_domain( $request );
        $has_error      = is_wp_error( $status_access ) || is_wp_error( $domain_access );

        if ( $has_error ) {
            $error = is_wp_error( $status_access ) ? $status_access : $domain_access;
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$license_activation,
                    'status_code'   => $error->get_error_data( 'status' ) ?: 403,
                    'request_data'  => 'Donwload token reauthentication',
                    'response_data' => array( 'reason' => $error->get_error_message() )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );
    
            return $error;
        
        }

        $download_token = $request->get_param( 'download_token' );
        return smliser_verify_item_token( $download_token, absint( $item_id ) );
    }

    /**
     * Re-issue donwload token.
     * 
     * @param WP_REST_Request $request.
     */
    public static function item_download_reauth( WP_REST_Request $request ) {
        $item_id        = $request->get_param( 'item_id' );
        $license_key    = $request->get_param( 'license_key' );
        $download_token = $request->get_param( 'download_token' );

        $decrypted = Callismart\Utilities\Encryption::decrypt( $download_token );

        list( $decoy, $raw_token ) = explode( '|', $decrypted, 2 );
        $t_obj  = new Smliser_Plugin_Download_Token();
        $token = $t_obj->get_token( $raw_token );
        
        // Invalidate the old token.
        if ( $token ) {
            $token->delete();
        }

        $two_weeks = 2 * WEEK_IN_SECONDS;
        $response_data = array(
            'download_token'    => smliser_generate_item_token( $item_id, $license_key, $two_weeks ),
            'token_expiry'      => wp_date( 'Y-m-d', time() + $two_weeks ),
        );
        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'content-type', 'application/json' );

        return $response;

    }


    /**
     * Whether or not to restrict a domain from activating a license
     * 
     * @param string $domain The domian name to check
     * @return false|WP_Error Returns false if the domain is allowed, or a WP_Error object if the domain is restricted.
     */
    public static function restrict_domain_activation( string $domain, WP_REST_Request $request ) {
        $license = self::$instance->license;

        if ( $license->is_new_domain( $domain ) ) {
            if ( ! $license->has_reached_max_allowed_sites() ) {
                return false;
            }
            return new WP_Error( 'license_error', 'Maximum allowed domains has been reached.', array( 'status' => 409 ) );
            
        }

        $result = self::verify_domain( $request );
        return is_wp_error( $result ) ? $result : false;        
    }

    /**
     * Verify that a domain can access the license REST API endpoint.
     * 
     * @param WP_REST_Request $request The WordPress REST API request object.
     * @return WP_Error|true WordPress error object on failure, true otherwise.
     */
    public static function verify_domain( WP_REST_Request $request ) {
        $license        = self::$instance->license;
        $domain         = $request->get_param( 'domain' );
        $domain         = wp_parse_url( $domain, PHP_URL_HOST );
        $known_domains  = $license->get_active_sites( 'edit' );
        $domain_data    = isset( $known_domains[$domain] ) ? $known_domains[$domain] : false;

        if ( ! isset( $domain_data['secret'] ) ) {
            return new WP_Error( 'site_token_missing', 'Your domain\'s secure token is missing', array( 'status' => 401 ) );
        }

        $known_secret   = $domain_data['secret'];
        $client_secret  = smliser_get_auth_token( $request );

        if ( ! $client_secret ) {
            return new WP_Error( 'authorization_header_not_found', 'Please provide authorization header for this domain', array( 'status' => 400 ) );
        }

        $client_secret = base64_decode( $client_secret );

        if ( ! $client_secret ) {
            return new WP_Error( 'invalid_token_format', 'Token could not be decoded properly, please be sure that the token is base64 encoded or not double encoding it.', array( 'status' => 400 ) );
        }

        if ( ! hash_equals( $known_secret, $client_secret ) ) {
            return new WP_Error( 'authorization_failed', 'Invalid authorization token', array( 'status' => 401 ) );
        }

        return true;
    }
}