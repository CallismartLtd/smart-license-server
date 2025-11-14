<?php
/**
 * The license REST API class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;

/**
 * Handles all the REST API for the license endpoints.
 */
class Smliser_License_Rest_API {
    /**
     * The current license object
     * 
     * @var License $license
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
        $this->license = License::class;

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
        $domain         = $request->get_param( 'domain' );
        $app_type       = $request->get_param( 'app_type' );
        $app_slug       = $request->get_param( 'app_slug' );

        $hosted_app     = Smliser_Software_Collection::get_app_by_slug( $app_type, $app_slug );

        if ( ! $hosted_app ) {
            $response = new WP_Error( 'license_error', sprintf( 'The %s with this slug "%s" does not exist', $app_type, $app_slug ), array( 'status' => 404 ) );
            return $response;
        }

        $license        = self::$instance->license::get_license( $service_id, $license_key );

        if ( ! $license ) {
 
            $response = new WP_Error( 'license_error', 'Invalid license', array( 'status' => 404 ) );
            return $response;
        }

        if ( ! $license->is_issued() ) {
            $error = new WP_Error( 'license_error', 'Activation not allowed for unissued licenses.', ['status' => 400] );

            return $error;
        }

        self::$instance->license = $license;

        $status_access  = $license->can_serve_license( $hosted_app->get_id() );
        $domain_access  = self::restrict_domain_activation( $domain, $request );

        $access_denied  = is_smliser_error( $status_access ) || is_smliser_error( $domain_access );

        if ( $access_denied  ) {
            $error = is_smliser_error( $status_access ) ? $status_access : $domain_access;

            if ( method_exists( $error, 'to_wp_error' ) ) {
                $error = $error->to_wp_error();
            }
    
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
        $domain         = $request->get_param( 'domain' );
        $license        = self::instance()->license;

        $response_data  = array(
            'message'   => sprintf( 'License has been activated for your domain "%s"', $domain ),
            'data' => array(
                'license_key'       => $license_key,
                'service_id'        => $service_id,
                'download_token'    => smliser_generate_item_token( $license, 2 * DAY_IN_SECONDS ),
                'token_expiry'      => wp_date( 'Y-m-d', time() + ( 2 * DAY_IN_SECONDS ) ),
                'license_expiry'    => $license->get_end_date()
            )
        );

        if ( $license->is_new_domain( $domain ) && ! $license->has_reached_max_allowed_domains() ) {
            $domain_secret                  = 'sc_' . bin2hex( random_bytes( 32 ) );
            $response_data['site_secret']   = base64_encode( $domain_secret );
            $domain_secret_hash             = hash_hmac( 'sha256', $domain_secret, DownloadToken::derive_key() );
            
            $license->update_active_domains( $domain, $domain_secret_hash );
        }

        $response = new WP_REST_Response( $response_data, 200 );

        $license_data   = array(
            'license_id'    => $license->get_id(),
            'ip_address'    => smliser_get_client_ip(),
            'website'       => $domain,
            'comment'       => 'License activated',
            'duration'      => microtime( true ) - self::$instance->start_time
        );

        Smliser_Stats::log_license_activity( $license_data );
        return $response;
    }
    
    /**
     * License deactivation permission.
     * The license key and service ID are required to deactivate a license.
     * 
     * @param WP_REST_Request $request The REST API request object
     * @return WP_Error|true
     */
    public static function license_deactivation_permission( WP_REST_Request $request ) {
        self::instance();
        self::$instance->start_time = microtime( true );
        $license_key    = $request->get_param( 'license_key' );
        $service_id     = $request->get_param( 'service_id' );
        
        $license        = self::$instance->license::get_license( $service_id, $license_key );
        
        if ( ! $license ) {
            return new WP_Error( 'invalid_license', 'The provided license does not exist.', array( 'status' => 404 ) );
        }

        if ( $license->is_deactivated() ) {
            return new WP_Error( 'license_error', 'License is already deactivated', ['status' => 200 ] );
        }

        self::$instance->license = $license;
        
        return self::verify_domain( $request );
    }

    /**
     * License deactivation route handler.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function license_deactivation_response( WP_REST_Request $request ) {
        $domain             = smliser_get_base_url( $request->get_param( 'domain' ) );
        $license            = self::instance()->license;
        $original_status    = $license->get_status();
        
        $license->set_status( 'Deactivated' );
        $log_data   = array(
            'license_id'    => $license->get_id(),
            'ip_address'    => smliser_get_client_ip(),
            'website'       => $domain,
            'comment'       => '',
            'duration'      => microtime( true ) - self::$instance->start_time
        );

        if ( $license->save() ) {
            $response_data = array(
                'message'   => 'License has been deactivated',
                'data'      => array(
                    'license_status'    => $license->get_status(),
                    'date'              => gmdate( 'Y-m-d' )
                ),
            );

            $status_code = 200;
            $log_data['comment']    = $response_data['message'];

        } else {
            $response_data = array(
                'message'   => 'Unable to process this request at the moment.',
                'data'  => array(
                    'license_status'    => $original_status,
                    'date'              => gmdate( 'Y-m-d' )
                ),
            );

            $status_code = 503;

            $log_data['comment']    = $response_data['message'];
        }

        Smliser_Stats::log_license_activity( $log_data );
        $response = new WP_REST_Response( $response_data, $status_code );
        
        return $response;
    }

    /**
     * License unistallation permission callback.
     * 
     * @param WP_REST_Request $request The REST Request Object.
     * @return WP_Error|true
     */
    public static function license_uninstallation_permission( WP_REST_Request $request ) {
        return self::license_deactivation_permission( $request );
    }

    /**
     * Uninstall or remove th given domain from the list of activated domains for this license.
     * 
     * @param WP_REST_Request $request The REST Request Object
     */
    public static function license_uninstallation_response( WP_REST_Request $request ) {
        $domain         = smliser_get_base_url( $request->get_param( 'domain' ) );
        $response_data  = array(
            'data'  => array(
                'license status'    => self::instance()->license->get_status(),
                'activated on'      => self::instance()->license->get_total_active_domains()
            )
        );

        if ( self::instance()->license->remove_activated_domain( $domain ) ) {
            $response_data['message']   = sprintf( 'Your domain %s has been uninstalled successfully.', $domain );
            $status_code                = 200;
        } else {
            $response_data['message']   = sprintf( 'Unable to unstall %s please try again later.', $domain );
            $status_code                = 503;   
        }

        $response = new WP_REST_Response( $response_data, $status_code );

        return $response;
    }

    /**
     * License validity test permission callback
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_Error|true
     */
    public static function license_validity_test_permission( WP_REST_Request $request ) {
        self::instance();
        self::$instance->start_time = microtime( true );
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );

        $license        = self::$instance->license->get_license_data( $service_id, $license_key );
        
        if ( ! $license ) {
            return new WP_Error( 'invalid_license', 'The provided license does not exist.', array( 'status' => 404 ) );
        }

        self::$instance->license = $license;

        $domain_access = self::verify_domain( $request );
        if ( is_smliser_error( $domain_access ) ) {
            return $domain_access;
        }

        $download_token = $request->get_header( 'x-download-token' );

        if ( ! $download_token ) {
            return new WP_Error( 'missing_download_token', 'Please provide the X-Download-Token header value', array( 'status' => 400 ) );
        }

        return true;
    }

    /**
     * Perform data validation test.
     * This operation will test for:
     * - License document validity.
     * - Domain validity.
     * - Download token validity.
     */
    public static function license_validity_test( WP_REST_Request $request ) {
        $license        = self::$instance->license;
        $item_id        = $request->get_param( 'item_id' );
        $download_token = $request->get_header( 'x-download-token' );
        
        $response_data = array(
            'license' => array(
                'status'        => $license->get_status(),
                'expiry_date'   => $license->get_end_date()
            ),
            'token_validity'    => smliser_verify_item_token( $download_token, absint( $item_id ) ) ? 'Valid' : 'Invalid',

        );

        $response = new WP_REST_Response( array( 'data' => $response_data ), 200 );

        return $response;

    }

    /**
     * Download token reauthentication permission callback.
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|true
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
        $has_error      = is_smliser_error( $status_access ) || is_smliser_error( $domain_access );

        if ( $has_error ) {
            $error = is_smliser_error( $status_access ) ? $status_access : $domain_access;
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
     * Re-issue a token for downloading a licensed application hosted on this repository.
     * 
     * -Note - This action is required before the expiration of the prevously issued token.
     * 
     * @param WP_REST_Request $request.
     * @return WP_REST_Response
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
            if ( $license->has_reached_max_allowed_domains() ) {
                return new WP_Error( 'license_error', 'Maximum allowed domains has been reached.', array( 'status' => 409 ) );
                
            }
            
            return false;
        }

        $result = self::verify_domain( $request );
        return is_smliser_error( $result ) ? $result : false;        
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
        $known_domains  = $license->get_active_domains( 'edit' );

        $domain_data    = isset( $known_domains[$domain] ) ? $known_domains[$domain] : false;

        if ( ! isset( $domain_data['secret'] ) ) {
            return new WP_Error( 'site_token_missing', sprintf( 'Invalid domain, please activate the domain %s to access this route', $domain ), array( 'status' => 401 ) );
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

        $client_secret_hash = hash_hmac( 'sha256', $client_secret, DownloadToken::derive_key() );

        if ( ! hash_equals( $known_secret, $client_secret_hash ) ) {
            return new WP_Error( 'authorization_failed', 'Invalid authorization token', array( 'status' => 401 ) );
        }

        return true;
    }
}