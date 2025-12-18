<?php
/**
 * The license REST API class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 * @since 1.0.0
 */

namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use SmartLicenseServer\HostedApps\SmliserSoftwareCollection;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Handles all the REST API for the license endpoints.
 */
class Licenses {
    /**
     * A flag to monitor response duration.
     * 
     * @var int $start_time
     */
    protected static $start_time = 0;

    /**
     * Class constructor.
     */
    private function __construct() {}

    /**
     * The new license activation route permission callback.
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function activation_permission_callback( WP_REST_Request $request ) {
        self::$start_time = microtime( true );
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $domain         = $request->get_param( 'domain' );
        $app_type       = $request->get_param( 'app_type' );
        $app_slug       = $request->get_param( 'app_slug' );

        $hosted_app     = SmliserSoftwareCollection::get_app_by_slug( $app_type, $app_slug );

        if ( ! $hosted_app ) {
            $response = new WP_Error( 'license_error', sprintf( 'The %s with this slug "%s" does not exist', $app_type, $app_slug ), array( 'status' => 404 ) );
            return $response;
        }

        $license        = License::get_license( $service_id, $license_key );

        if ( ! $license ) {
            $response = new WP_Error( 'license_error', 'The license could not be found. The license key or service ID may be incorrect.', array( 'status' => 404 ) );
            return $response;
        }

        if ( ! $license->is_issued() ) {
            $error = new WP_Error( 'license_error', 'Activation not allowed for unissued licenses.', ['status' => 400] );

            return $error;
        }

        $request->set_param( 'license', $license );

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
    public static function activation_response( WP_REST_Request $request ) {
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $domain         = $request->get_param( 'domain' );
        /** @var License $license */
        $license        = $request->get_param( 'license' );

        $response_data  = array(
            'message'   => sprintf( 'License has been activated for your domain "%s"', $domain ),
            'data' => array(
                'license_key'       => $license_key,
                'service_id'        => $service_id,
                'download_token'    => smliser_generate_item_token( $license, 2 * DAY_IN_SECONDS ),
                'token_expiry'      => gmdate( 'Y-m-d', time() + ( 2 * DAY_IN_SECONDS ) ),
                'license_expiry'    => $license->get_end_date()?: \gmdate( 'Y-m-d', \strtotime( '+15 years' ) )
            )
        );

        if ( $license->is_new_domain( $domain ) && ! $license->has_reached_max_allowed_domains() ) {
            $domain_secret                  = 'sc_' . bin2hex( random_bytes( 32 ) );
            $response_data['data']['site_secret']   = base64_encode( $domain_secret );
            $domain_secret_hash             = hash_hmac( 'sha256', $domain_secret, DownloadToken::derive_key() );
            
            $license->update_active_domains( $domain, $domain_secret_hash );
        }

        $response = new WP_REST_Response( $response_data, 200 );

        $license_data   = array(
            'license_id'    => $license->get_id(),
            'ip_address'    => smliser_get_client_ip(),
            'event_type'    => 'activation',
            'website'       => $domain,
            'comment'       => 'License activated',
            'duration'      => microtime( true ) - self::$start_time
        );

        RepositoryAnalytics::log_license_activity( $license_data );
        return $response;
    }
    
    /**
     * License deactivation permission.
     * The license key and service ID are required to deactivate a license.
     * 
     * @param WP_REST_Request $request The REST API request object
     * @return WP_Error|true
     */
    public static function deactivation_permission( WP_REST_Request $request ) {
        self::$start_time = microtime( true );
        $license_key    = $request->get_param( 'license_key' );
        $service_id     = $request->get_param( 'service_id' );
        
        $license        = License::get_license( $service_id, $license_key );
        
        if ( ! $license ) {
            $response = new WP_Error( 'license_error', 'The license could not be found. The license key or service ID may be incorrect.', array( 'status' => 404 ) );
            return $response;
        }

        if ( $license->is_deactivated() ) {
            return new WP_Error( 'license_error', 'License is already deactivated', ['status' => 200 ] );
        }

        $request->set_param( 'license', $license );
        
        return self::verify_domain( $request );
    }

    /**
     * License deactivation route handler.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function deactivation_response( WP_REST_Request $request ) {
        $domain = smliser_get_base_url( $request->get_param( 'domain' ) );
        /** @var License $license */
        $license            = $request->get_param( 'license' );
        $original_status    = $license->get_status();
        
        $license->set_status( 'Deactivated' );
        $log_data   = array(
            'license_id'    => $license->get_id(),
            'ip_address'    => smliser_get_client_ip(),
            'event_type'    => 'deactivation',
            'website'       => $domain,
            'comment'       => '',
            'duration'      => microtime( true ) - self::$start_time
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

        } else {
            $response_data = array(
                'message'   => 'Unable to process this request at the moment.',
                'data'  => array(
                    'license_status'    => $original_status,
                    'date'              => gmdate( 'Y-m-d' )
                ),
            );

            $status_code = 503;

        }

        $log_data['comment']    = $response_data['message'];
        RepositoryAnalytics::log_license_activity( $log_data );
        $response = new WP_REST_Response( $response_data, $status_code );
        
        return $response;
    }

    /**
     * License unistallation permission callback.
     * 
     * @param WP_REST_Request $request The REST Request Object.
     * @return WP_Error|true
     */
    public static function uninstallation_permission( WP_REST_Request $request ) {
        return self::deactivation_permission( $request );
    }

    /**
     * Uninstall or remove th given domain from the list of activated domains for this license.
     * 
     * @param WP_REST_Request $request The REST Request Object
     */
    public static function uninstallation_response( WP_REST_Request $request ) {
        $domain         = smliser_get_base_url( $request->get_param( 'domain' ) );
        /** @var License $license */
        $license        = $request->get_param( 'license' );
        $response_data  = array(
            'data'  => array(
                'license status'    => $license->get_status(),
                'activated on'      => $license->get_total_active_domains()
            )
        );

        $log_data   = array(
            'license_id'    => $license->get_id(),
            'event_type'    => 'uninstallation',
            'ip_address'    => smliser_get_client_ip(),
            'website'       => $domain,
            'comment'       => '',
            'duration'      => microtime( true ) - self::$start_time
        );

        if ( $license->remove_activated_domain( $domain ) ) {
            $response_data['message']   = sprintf( 'The license uninstalled for the domain "%s" successfully.', $domain );
            $status_code                = 200;
        } else {
            $response_data['message']   = sprintf( 'Unable to unstall %s please try again later.', $domain );
            $status_code                = 503;   
        }

        $log_data['comment']    = $response_data['message'];
        $response = new WP_REST_Response( $response_data, $status_code );
        RepositoryAnalytics::log_license_activity( $log_data );

        return $response;
    }

    /**
     * License validity test permission callback
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_Error|true
     */
    public static function validity_test_permission( WP_REST_Request $request ) {
        self::$start_time = microtime( true );
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $app_type       = $request->get_param( 'app_type' );
        $app_slug       = $request->get_param( 'app_slug' );
        $hosted_app     = SmliserSoftwareCollection::get_app_by_slug( $app_type, $app_slug );

        if ( ! $hosted_app ) {
            $response = new WP_Error( 'license_error', sprintf( 'The %s with this slug "%s" does not exist', $app_type, $app_slug ), array( 'status' => 404 ) );
            return $response;
        }
        $license        = License::get_license( $service_id, $license_key );
        
        if ( ! $license ) {
            $response = new WP_Error( 'license_error', 'The license could not be found. The license key or service ID may be incorrect.', array( 'status' => 404 ) );
            return $response;
        }

        $request->set_param( 'license', $license );
        $request->set_param( 'hosted_app', $hosted_app );

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
    public static function validity_test_response( WP_REST_Request $request ) {
        /** @var License $license */
        $license    = $request->get_param( 'license' );

        /** @var AbstractHostedApp $hosted_app */
        $hosted_app     = $request->get_param( 'hosted_app' );

        $download_token = $request->get_header( 'x-download-token' );
        $token_validity = smliser_verify_item_token( $download_token, $hosted_app );
        
        $response_data = array(
            'message'   => 'License validity test result is ready.',
            'data' => array(
                'license' => array(
                    'status'        => $license->get_status(),
                    'expiry_date'   => $license->get_end_date()
                ),
                'token_validity'    => \is_smliser_error( $token_validity ) ? 'Invalid' : 'Valid',                
            )
        );

        $log_data   = array(
            'license_id'    => $license->get_id(),
            'event_type'    => 'verification',
            'ip_address'    => smliser_get_client_ip(),
            'website'       => $request->get_param( 'domain' ),
            'comment'       => \sprintf( 'Message: %s | Token validity: %s', $response_data['message'], $response_data['data']['token_validity'] ),
            'duration'      => microtime( true ) - self::$start_time
        );

        $response = new WP_REST_Response( $response_data, 200 );
        RepositoryAnalytics::log_license_activity( $log_data );

        return $response;

    }

    /**
     * Download token reauthentication permission callback.
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|true
     */
    public static function download_reauth_permission( WP_REST_Request $request ) {
        self::$start_time = microtime( true );
        $service_id     = $request->get_param( 'service_id' );
        $license_key    = $request->get_param( 'license_key' );
        $license        = License::get_license( $service_id, $license_key );

        $app_type       = $request->get_param( 'app_type' );
        $app_slug       = $request->get_param( 'app_slug' );
        $hosted_app     = SmliserSoftwareCollection::get_app_by_slug( $app_type, $app_slug );

        if ( ! $hosted_app ) {
            $response = new WP_Error( 'license_error', sprintf( 'The %s with this slug "%s" does not exist', $app_type, $app_slug ), array( 'status' => 404 ) );
            return $response;
        }

        if ( ! $license ) {
            $response = new WP_Error( 'license_error', 'The license could not be found. The license key or service ID may be incorrect.', array( 'status' => 404 ) );
            return $response;
        }

        $request->set_param( 'license', $license );
        $status_access  = $license->can_serve_license( $hosted_app->get_id() );
        $domain_access  = self::verify_domain( $request );
        $has_error      = is_smliser_error( $status_access ) || is_smliser_error( $domain_access );

        if ( $has_error ) {
            $error = is_smliser_error( $status_access ) ? $status_access : $domain_access;

            if ( method_exists( $error, 'to_wp_error' ) ) {
                $error = $error->to_wp_error();
            }
    
            return $error;
        
        }

        $download_token = $request->get_param( 'download_token' );
        $token          = smliser_verify_item_token( $download_token, $hosted_app );

        if ( is_smliser_error( $token ) ) {
            return $token->to_wp_error();
        }

        $token->delete();

        return true;
    }

    /**
     * Re-issue a token for downloading a licensed application hosted on this repository.
     * 
     * -Note - This action is required before the expiration of the prevously issued token.
     * 
     * @param WP_REST_Request $request.
     * @return WP_REST_Response
     */
    public static function app_download_reauth( WP_REST_Request $request ) {
        /** @var License $license */
        $license        = $request->get_param( 'license' );
        $two_weeks      = 2 * WEEK_IN_SECONDS;
        $response_data  = array(
            'message'   => 'Download token has been refreshed.',
            'data'      => array(
                'download_token'    => smliser_generate_item_token( $license, $two_weeks ),
                'token_expiry'      => gmdate( 'Y-m-d', time() + $two_weeks ),                
            )

        );
        $response = new WP_REST_Response( $response_data, 200 );
        return $response;
    }

    /**
     * Whether or not to restrict a domain from activating a license
     * 
     * @param string $domain The domian name to check
     * @return false|WP_Error Returns false if the domain is allowed, or a WP_Error object if the domain is restricted.
     */
    public static function restrict_domain_activation( string $domain, WP_REST_Request $request ) {
        /** @var License $license */
        $license    = $request->get_param( 'license' );;

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
        /** @var License $license */
        $license        = $request->get_param( 'license' );
        $domain         = $request->get_param( 'domain' );

        $domain_data    = $license->get_active_domain( $domain );

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