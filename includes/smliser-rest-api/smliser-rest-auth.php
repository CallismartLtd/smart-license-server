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
     * Error Property
     * 
     * @var array $error 
     */
    private $error = array();


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
    public static function client_authentication_response( $request ) {
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
                'code'      => 'invalid_credential',
                'message'   => 'The provided credentials are not valid'
            );
            $response = new WP_REST_Response( $response_data, 404 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        if ( 'reauth' === $context && 'Active' === $the_api_cred->get_status() && ! empty( $app_name ) && hash_equals( $app_name, $the_api_cred->get_token( 'app_name', 'edit' ) ) ) {
            // Old token validation.
            $bearer = self::$instance->extract_token( $request );
            
            if ( hash_equals( $bearer, $the_api_cred->get_token( 'token' ) ) ) {
                $access_token = $the_api_cred->reauth();
                $response_data = array(
                    'code'          => 'success',
                    'access_token'  => $access_token,
                );
                $response = new WP_REST_Response( $response_data, 200 );
                $response->header( 'Content-Type', 'application/json' );
        
                return $response;             
            }


        } elseif( 'auth' === $context && ! empty( $app_name ) && 'Inactive' === $the_api_cred->get_status() ) {
            $access_token = $the_api_cred->activate( $app_name );

            if ( false === $access_token ) {
                $response_data = array(
                    'code'          => 'failed',
                    'access_token'  => "",
                );
                $response = new WP_REST_Response( $response_data, 403 );
                $response->header( 'Content-Type', 'application/json' );
        
                return $response;
            }
            $response_data = array(
                'code'          => 'success',
                'access_token'  => $access_token,
            );
            $response = new WP_REST_Response( $response_data, 200 );
            $response->header( 'Content-Type', 'application/json' );
    
            return $response;
        }

        $response_data = array(
            'code'          => 'failed',
            'access_token'  => $access_token,
        );
        $response = new WP_REST_Response( $response_data, 403 );
        $response->header( 'Content-Type', 'application/json' );

        return $response;
    }


    /**
     * Set headers for a denied response.
     *
     * @param WP_REST_Response $response The response object to set headers on.
     * @return WP_REST_Response The response object with headers set.
     */
    private static function set_denied_header( WP_REST_Response $response ) {
        $response->header( 'Content-Type', 'application/json' );
        $response->header( 'X-Smliser-REST-Response', 'AccessDenied' );
        $response->header( 'WWW-Authenticate', 'Bearer realm="example", error="invalid_token", error_description="The access token expired"' );

    }

}