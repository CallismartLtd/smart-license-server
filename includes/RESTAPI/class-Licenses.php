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
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Handles all the REST API for the license endpoints.
 */
class Licenses {

    /**
     * Class constructor.
     */
    private function __construct() {}

    /**
     * The new license activation route permission callback.
     * 
     * @param Request $request The current request object.
     */
    public static function activation_permission_callback( Request $request ) : bool|RequestException {
        $service_id     = $request->get( 'service_id' );
        $license_key    = $request->get( 'license_key' );
        $domain         = $request->get( 'domain' );
        $app_type       = $request->get( 'app_type' );
        $app_slug       = $request->get( 'app_slug' );

        $hosted_app     = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( ! $hosted_app ) {
            $response = new RequestException( 'license_error', sprintf( 'The %s with this slug "%s" does not exist', $app_type, $app_slug ), array( 'status' => 404 ) );
            return $response;
        }

        $license    = License::get_license( $service_id, $license_key );

        if ( ! $license ) {
            $response = new RequestException( 'license_error', 'The license could not be found. The license key or service ID may be incorrect.', array( 'status' => 404 ) );
            return $response;
        }

        if ( ! $license->is_issued() ) {
            $error = new RequestException( 'license_error', 'Activation not allowed for unissued licenses.', ['status' => 400] );

            return $error;
        }

        $request->set( 'smliser_resource', $license );

        $status_access  = $license->can_serve_license( $hosted_app->get_id() );
        $domain_access  = self::restrict_domain_activation( $domain, $request );

        $access_denied  = is_smliser_error( $status_access ) || is_smliser_error( $domain_access );

        if ( $access_denied  ) {
            $error = is_smliser_error( $status_access ) ? $status_access : $domain_access;

            return $error;
        }

        return true;
    }

    /**
     * Responds to license activation request.
     * 
     * @param Request $request The current request object.
     */
    public static function activation_response( Request $request ) {
        $service_id     = $request->get( 'service_id' );
        $license_key    = $request->get( 'license_key' );
        $domain         = $request->get( 'domain' );
        
        /** @var License $license */
        $license        = $request->get( 'smliser_resource' );

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

        $response = new Response( 200, array(), $response_data );

        $license_data   = array(
            'license_id'    => $license->get_id(),
            'ip_address'    => smliser_get_client_ip(),
            'event_type'    => 'activation',
            'website'       => $domain,
            'comment'       => 'License activated',
            'duration'      => microtime( true ) - $request->startTime()
        );

        RepositoryAnalytics::log_license_activity( $license_data );
        
        return $response;
    }
    
    /**
     * License deactivation permission.
     * The license key and service ID are required to deactivate a license.
     * 
     * @param Request $request The REST API request object
     * @return RequestException|true
     */
    public static function deactivation_permission( Request $request ) : bool|RequestException {
        $license_key    = $request->get( 'license_key' );
        $service_id     = $request->get( 'service_id' );
        $license        = License::get_license( $service_id, $license_key );
        
        if ( ! $license ) {
            $response = new RequestException(
                'license_error',
                'The license could not be found. The license key or service ID may be incorrect.',
                array( 'status' => 404 )
            );

            return $response;
        }

        if ( $license->is_deactivated() ) {
            return new RequestException( 'license_error', 'License is already deactivated', ['status' => 200 ] );
        }

        $request->set( 'smliser_resource', $license );
        
        return self::verify_domain( $request );
    }

    /**
     * License deactivation route handler.
     * 
     * @param Request $request
     * @return Response
     */
    public static function deactivation_response( Request $request ) : Response {
        $domain = smliser_get_base_url( $request->get( 'domain' ) );
        /** @var License $license */
        $license            = $request->get( 'smliser_resource' );
        $original_status    = $license->get_status();
        
        $license->set_status( 'Deactivated' );
        $log_data   = array(
            'license_id'    => $license->get_id(),
            'ip_address'    => smliser_get_client_ip(),
            'event_type'    => 'deactivation',
            'website'       => $domain,
            'comment'       => '',
            'duration'      => microtime( true ) - $request->startTime()
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
        
        $response = new Response( $status_code, array(), $response_data );
        
        return $response;
    }

    /**
     * License unistallation permission callback.
     * 
     * @param Request $request The REST Request Object.
     * @return RequestException|true
     */
    public static function uninstallation_permission( Request $request ) {
        return self::deactivation_permission( $request );
    }

    /**
     * Uninstall or remove th given domain from the list of activated domains for this license.
     * 
     * @param Request $request The REST Request Object
     */
    public static function uninstallation_response( Request $request ) : Response {
        $domain = smliser_get_base_url( $request->get( 'domain' ) );
        /** @var License $license */
        $license        = $request->get( 'smliser_resource' );
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
            'duration'      => microtime( true ) - $request->startTime()
        );

        if ( $license->remove_activated_domain( $domain ) ) {
            $response_data['message']   = sprintf( 'The license uninstalled for the domain "%s" successfully.', $domain );
            $status_code                = 200;
        } else {
            $response_data['message']   = sprintf( 'Unable to unstall %s please try again later.', $domain );
            $status_code                = 503;   
        }

        $log_data['comment']    = $response_data['message'];

        $response = new Response( $status_code, array(), $response_data );
        RepositoryAnalytics::log_license_activity( $log_data );

        return $response;
    }

    /**
     * License validity test permission callback
     * 
     * @param Request $request The REST API request object.
     * @return RequestException|true
     */
    public static function validity_test_permission( Request $request ) {
        $service_id     = $request->get( 'service_id' );
        $license_key    = $request->get( 'license_key' );
        $app_type       = $request->get( 'app_type' );
        $app_slug       = $request->get( 'app_slug' );
        $hosted_app     = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( ! $hosted_app ) {
            $response = new RequestException( 'license_error', sprintf( 'The %s with this slug "%s" does not exist', $app_type, $app_slug ), array( 'status' => 404 ) );
            return $response;
        }
        $license        = License::get_license( $service_id, $license_key );
        
        if ( ! $license ) {
            $response = new RequestException( 'license_error', 'The license could not be found. The license key or service ID may be incorrect.', array( 'status' => 404 ) );
            return $response;
        }

        $request->set( 'smliser_resource', $license );
        $request->set( 'hosted_app', $hosted_app );

        $domain_access = self::verify_domain( $request );
        if ( is_smliser_error( $domain_access ) ) {
            return $domain_access;
        }

        $download_token = $request->get_header( 'x-download-token' );

        if ( ! $download_token ) {
            return new RequestException( 'missing_download_token', 'Please provide the X-Download-Token header value', array( 'status' => 400 ) );
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
    public static function validity_test_response( Request $request ) {
        /** @var License $license */
        $license    = $request->get( 'smliser_resource' );

        /** @var AbstractHostedApp $hosted_app */
        $hosted_app     = $request->get( 'hosted_app' );

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
            'website'       => $request->get( 'domain' ),
            'comment'       => \sprintf( 'Message: %s | Token validity: %s', $response_data['message'], $response_data['data']['token_validity'] ),
            'duration'      => microtime( true ) - $request->startTime()
        );

        $response = new Response( 200, array(), $response_data );
        RepositoryAnalytics::log_license_activity( $log_data );

        return $response;

    }

    /**
     * Download token reauthentication permission callback.
     * 
     * @param Request $request
     * @return RequestException|true
     */
    public static function download_reauth_permission( Request $request ) {
        $service_id     = $request->get( 'service_id' );
        $license_key    = $request->get( 'license_key' );
        $license        = License::get_license( $service_id, $license_key );

        $app_type       = $request->get( 'app_type' );
        $app_slug       = $request->get( 'app_slug' );
        $hosted_app     = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( ! $hosted_app ) {
            $response = new RequestException( 'license_error', sprintf( 'The %s with this slug "%s" does not exist', $app_type, $app_slug ), array( 'status' => 404 ) );
            return $response;
        }

        if ( ! $license ) {
            $response = new RequestException( 'license_error', 'The license could not be found. The license key or service ID may be incorrect.', array( 'status' => 404 ) );
            return $response;
        }

        $request->set( 'smliser_resource', $license );
        $status_access  = $license->can_serve_license( $hosted_app->get_id() );
        $domain_access  = self::verify_domain( $request );
        $has_error      = is_smliser_error( $status_access ) || is_smliser_error( $domain_access );

        if ( $has_error ) {
            $error = is_smliser_error( $status_access ) ? $status_access : $domain_access;    
            return $error;
        
        }

        $download_token = $request->get( 'download_token' );
        $token          = smliser_verify_item_token( $download_token, $hosted_app );

        if ( is_smliser_error( $token ) ) {
            return $token;
        }

        $token->delete();

        return true;
    }

    /**
     * Re-issue a token for downloading a licensed application hosted on this repository.
     * 
     * -Note - This action is required before the expiration of the prevously issued token.
     * 
     * @param Request $request.
     * @return Response
     */
    public static function app_download_reauth( Request $request ) {
        /** @var License $license */
        $license        = $request->get( 'smliser_resource' );
        $two_weeks      = 2 * WEEK_IN_SECONDS;
        
        $response_data  = array(
            'message'   => 'Download token has been refreshed.',
            'data'      => array(
                'download_token'    => smliser_generate_item_token( $license, $two_weeks ),
                'token_expiry'      => gmdate( 'Y-m-d', time() + $two_weeks ),                
            )
        );

        $response = new Response( 200, array(), $response_data );
        return $response;
    }

    /**
     * Whether or not to restrict a domain from activating a license
     * 
     * @param string $domain The domian name to check
     * @return false|RequestException Returns false if the domain is allowed, or a RequestException object if the domain is restricted.
     */
    public static function restrict_domain_activation( string $domain, Request $request ) {
        /** @var License $license */
        $license    = $request->get( 'smliser_resource' );;

        if ( $license->is_new_domain( $domain ) ) {
            if ( $license->has_reached_max_allowed_domains() ) {
                return new RequestException( 'license_error', 'Maximum allowed domains has been reached.', array( 'status' => 409 ) );
                
            }
            
            return false;
        }

        $result = self::verify_domain( $request );
        return is_smliser_error( $result ) ? $result : false;        
    }

    /**
     * Verify that a domain can access the license REST API endpoint.
     * 
     * @param Request $request The request object.
     * @return RequestException|true Error object on failure, true otherwise.
     */
    public static function verify_domain( Request $request ) {
        /** @var License $license */
        $license        = $request->get( 'smliser_resource' );
        $domain         = $request->get( 'domain' );

        $domain_data    = $license->get_active_domain( $domain );

        if ( ! isset( $domain_data['secret'] ) ) {
            return new RequestException( 'site_token_missing', sprintf( 'Invalid domain, please activate the domain %s to access this route', $domain ), array( 'status' => 401 ) );
        }

        $known_secret   = $domain_data['secret'];
        $client_secret  = $request->bearerToken();

        if ( ! $client_secret ) {
            return new RequestException( 'authorization_header_not_found', 'Please provide authorization header for this domain', array( 'status' => 400 ) );
        }

        $client_secret = base64_decode( $client_secret );

        if ( ! $client_secret ) {
            return new RequestException( 'invalid_token_format', 'Token could not be decoded properly, please be sure that the token is base64 encoded or not double encoding it.', array( 'status' => 400 ) );
        }

        $client_secret_hash = hash_hmac( 'sha256', $client_secret, DownloadToken::derive_key() );

        if ( ! hash_equals( $known_secret, $client_secret_hash ) ) {
            return new RequestException( 'authorization_failed', 'Invalid authorization token', array( 'status' => 401 ) );
        }

        return true;
    }
}