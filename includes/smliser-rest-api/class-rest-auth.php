<?php
/**
 * REST API Authentication class file
 * 
 * @author Callistus
 * @package Smliser\classes
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rest API Authentication class
 */
class Smliser_REST_Authentication {

	/**
	 * Authentication error.
	 *
	 * @var WP_Error
	 */
	protected $error = null;

    /**
     * Instance of current class.
     * 
     * @var Smliser_REST_Authentication
     */
    private static $instance = null;

	/**
	 * Resource Owner.
	 *
	 * @var WP_User
	 */
	protected $user = null;

	/**
	 * The current REST Request method.
	 *
	 * @var string
	 */
	protected $auth_method = '';


    /**
     * Client Authentication Route permission handler.
     * Auth process checks for consumer_secret and consumer_public which is issued through the
     * authorization page or generated through the admin REST API settings page.
     * 
     * @param WP_REST_Request $request The WordPress REST Response object.
     * @see Smliser_admin_menu::api_keys_option().
     * 
     */
    public static function auth_permission( WP_REST_Request $request ) {
        
        $consumer_public    = ! empty( $request->get_param( 'consumer_public' ) ) ? sanitize_text_field( wp_unslash( urldecode( $request->get_param( 'consumer_public' ) ) ) ) : '';
        $consumer_secret    = ! empty( $request->get_param( 'consumer_secret' ) ) ? sanitize_text_field( wp_unslash(urldecode( $request->get_param( 'consumer_secret' ) ) ) ) : '';
        
        if ( ! empty( $consumer_secret ) && ! empty( $consumer_public ) ) {
            $api_cred_obj   = new Smliser_API_Cred();
            $the_api_cred    = $api_cred_obj->get_api_data( $consumer_public, $consumer_secret );
            
            if ( empty( $the_api_cred ) ) {
                return false; // The API Key must exist.
            }

            return true;
        }
        
        return false;
    }

    /**
     * Client REST Authentication handler.
     * 
     * 
     * @param WP_REST_Request $request The current REST API Request object
     */
    public static function client_authentication_response( WP_REST_Request $request ) {
        $consumer_public    = ! empty( $request->get_param( 'consumer_public' ) ) ? sanitize_text_field( wp_unslash( urldecode( $request->get_param( 'consumer_public' ) ) ) ) : '';
        $consumer_secret    = ! empty( $request->get_param( 'consumer_secret' ) ) ? sanitize_text_field( wp_unslash( urldecode( $request->get_param( 'consumer_secret' ) ) ) ) : '';
        $app_name           = ! empty( $request->get_param( 'app_name' ) ) ? sanitize_text_field( wp_unslash( urldecode( $request->get_param( 'app_name' ) ) ) ) : '';
        $context            = ! empty( $request->get_param( 'context' ) ) ? sanitize_text_field( wp_unslash( urldecode( $request->get_param( 'context' ) ) ) ) : '';        
        $api_cred_obj       = new Smliser_API_Cred();
        $the_api_cred       = $api_cred_obj->get_api_data( $consumer_public, $consumer_secret );
        $access_token       = '';

        /**
         * Recheck credential validity.
         */
        if ( empty( $the_api_cred ) ) {
            $response_data = array(
                'success'   => false,
                'code'      => 'invalid_credentials',
                'message'   => 'The provided credentials are not valid',
                'data'      => array(
                    'status'    => 404,
                ),
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $response->header( 'content-type', 'application/json' );
            self::set_denied_header( $response );

    
            return $response;
        }

        if ( 'reauth' === $context && 'Active' === $the_api_cred->get_status() && ! empty( $app_name ) && hash_equals( $app_name, $the_api_cred->get_token( 'app_name', 'edit' ) ) ) {
            // Old token validation.
            $bearer = self::$instance->extract_token( $request );
       
            if ( empty( $bearer ) || ! hash_equals( $bearer, $the_api_cred->get_token( 'token' ) ) ) {
                $response_data = array(
                    'success'   => false,
                    'code'      => 'cannot_reauthenticate',
                    'message'   => 'Current token is invalid.',
                    'data'      => array(
                        'status'    => 403,
                    ),
                );

                $response = new WP_REST_Response( $response_data, 403 );
                self::set_denied_header( $response );
                $response->header( 'content-type', 'application/json' );
        
                return $response;             
            }

            if ( $the_api_cred->reauth() ) {
                
                $response_data = array(
                    'success'   => true,
                    'data'      => array(
                        'app_name'      => $the_api_cred->get_token( 'app_name' ),
                        'access_token'  => base64_encode( $the_api_cred->get_token( 'token' ) ),
                        'token_expiry'  => $the_api_cred->get_token( 'token_expiry' ),
                    ),
                );
                
                $response = new WP_REST_Response( $response_data, 200 );
                $response->header( 'content-type', 'application/json' );
        
                return $response;
            }
            


        } elseif( 'auth' === $context && ! empty( $app_name ) && 'Inactive' === $the_api_cred->get_status() ) {
            $access_token = $the_api_cred->activate( $app_name );

            if ( false === $access_token ) {
                $response_data = array(
                    'code'          => 'token_creation_failed',
                    'access_token'  => "",
                );
                $response = new WP_REST_Response( $response_data, 503 );
                $response->header( 'content-type', 'application/json' );
        
                return $response;
            }
            
            $response_data = array(
                'success'   => true,
                'data'      => array(
                    'app_name'      => $the_api_cred->get_token( 'app_name' ),
                    'access_token'  => base64_encode( $the_api_cred->get_token( 'token' ) ),
                    'token_expiry'  => $the_api_cred->get_token( 'token_expiry' ),
                ),
            );
            $response = new WP_REST_Response( $response_data, 200 );
            $response->header( 'content-type', 'application/json' );
    
            return $response;
        }

        $response_data = array(
            'code'          => 'failed',
            'access_token'  => $access_token,
        );
        $response = new WP_REST_Response( $response_data, 403 );
        $response->header( 'content-type', 'application/json' );

        return $response;
    }

    /**
     * Download token reauthentication for licensed plugins.
     */
    public static function item_download_reauth_permission( WP_REST_Request $request ) {
        $item_id        = ! empty( $request->get_param( 'item_id' ) ) ? sanitize_text_field( urldecode( $request->get_param( 'item_id' )) ): '';
        $token          = self::$instance->extract_token( $request, 'raw' );
        $service_id     = ! empty( $request->get_param( 'service_id' ) ) ? sanitize_text_field( urldecode( $request->get_param( 'service_id' ) ) ): '';
        $license_key    = ! empty( $request->get_param( 'license_key' ) ) ? sanitize_text_field( urldecode( $request->get_param( 'license_key' ) ) ): '';
        $callback_url   = ! empty( $request->get_param( 'callback_url' ) ) ? sanitize_text_field( urldecode( $request->get_param( 'callback_url' ) ) ): '';

        if ( empty( $item_id ) || empty( $token ) || empty( $service_id ) || empty( $license_key ) || empty( $callback_url) ) {
            return false;
        }

        return smliser_verify_item_token( $token, absint( $item_id ) );
    }

    /**
     * Re-issue donwload token.
     * 
     * @param WP_REST_Request $request.
     */
    public static function item_download_reauth( WP_REST_Request $request ) {
        $item_id        = sanitize_text_field( urldecode( $request->get_param( 'item_id' ) ) );
        $service_id     = sanitize_text_field( urldecode( $request->get_param( 'service_id' ) ) );
        $license_key    = sanitize_text_field( urldecode( $request->get_param( 'license_key' ) ) );
        $callback_url   = sanitize_text_field( smliser_get_base_url( rawurldecode( $request->get_param( 'callback_url' ) ) ) );

        // Check if license is still valid.
        $license_obj    = new Smliser_license();
        $the_license    = $license_obj->get_license_data( $service_id, $license_key );

        if ( empty( $the_license ) ) {
            $response_data = array(
                'code'  => 'invalid_license',
                'message'   => 'License could not be found',
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $response->header( 'content-type', 'application/json' );

            return $response;
        } 
        
        $access = $the_license->can_serve_license( $item_id );
        if ( is_wp_error( $access ) ) {
            $response_data = array(
                'code'      => 'license_error',
                'message'   => $access->get_error_message()
            );

            $response = new WP_REST_Response( $response_data, 403 );
            $response->header( 'content-type', 'application/json' );

            return $response;
        }

        /**
         *  Ensure we keep track of clients with authentic tokens that 
         *  were not registered during the initial validation.
         */
        if ( ! $the_license->has_reached_max_allowed_sites() ) {
            $the_license->update_active_sites( $callback_url );
        }

        $license_end_date   = $the_license->get_end_date();

        $two_weeks = 2 * WEEK_IN_SECONDS;
        $response_data = array(
            'success'       => true,
            'API_KEY'       => smliser_generate_item_token( $item_id, $license_key, $two_weeks ),
            'token_expiry'  => wp_date( 'Y-m-d', time() + $two_weeks ),
            'last_updated'  => $license_end_date
        );
        $response = new WP_REST_Response( $response_data, 200 );
        $response->header( 'content-type', 'application/json' );

        return $response;

    }

    /**
     * Set headers for a denied response.
     *
     * @param WP_REST_Response $response The response object to set headers on.
     */
    private static function set_denied_header( WP_REST_Response $response ) {
        $response->header( 'content-type', 'application/json' );
        $response->header( 'X-Smliser-REST-Response', 'AccessDenied' );
        $response->header( 'WWW-Authenticate', 'Bearer realm="example", error="invalid_token", error_description="Invalid access token supplied."' );

    }

    /**
     * Instance of Smliser_REST_Authentication
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
    }

    /**
     * Extract token from request header.
     * 
     * @param WP_REST_Request $request The WordPress REST response object.
     * @param string $context           The context in which the token is extracted. 
     *                                  Pass "raw" for the raw data defaults to "decode"
     */
    public function extract_token( WP_REST_Request $request, $context = 'decode' ) {

        // Get the authorization header.
        $access_token = smliser_get_auth_token( $request );
        
        if ( ! empty( $access_token ) ) {
            if ( 'raw' === $context ) {
                return $access_token;
            }
            
            return smliser_safe_base64_decode( $access_token );       
        }

        // Return null if no valid token is found.
        return null;
    }

}

Smliser_REST_Authentication::instance();