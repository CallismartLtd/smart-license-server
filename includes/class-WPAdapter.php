<?php
/**
 * File for the WordPress adapter
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

namespace SmartLicenseServer;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\DownloadsApi\FileRequestController;
use SmartLicenseServer\DownloadsApi\FileRequest;
use Smliser_Software_Collection as AppCollection;
use SmartLicenseServer\Exception\FileRequestException;
use SmartLicenseServer\Monetization\Controller;

defined( 'ABSPATH'  ) || exit;

/**
 * Wordpress adapter bridges the gap beween Smart License Server and request from
 * WP environments
 */
class WPAdapter {

    /**
     * Single instance of this class.
     * 
     * @var Smliser_Server $instance Instance.
     */
    private static $instance = null;

    /**
     * Class constructor.
     */
    public function __construct() {
        add_action( 'admin_init', [__CLASS__, 'init_request'] );
        add_action( 'template_redirect', array( __CLASS__, 'init_request' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
        
    }

    /**
     *  Handle incoming requests for this application.
     */
    public static function init_request() {
        $trigger   = get_query_var( 'pagename' );

		if ( ! $trigger && isset( $_REQUEST['action'] ) ) {
			$trigger = sanitize_text_field( unslash( $_REQUEST['action'] ) );
		}

        if ( empty( $trigger ) || ! is_string( $trigger ) ) {
            return;
        }

        $handler_map    = [
            'smliser-downloads'         => function() { ( self::resolve_download_callback() )(); },
            'smliser-repository-assets' => [__CLASS__, 'parse_app_asset_request'],
            'smliser_admin_download'    => [__CLASS__, 'parse_admin_download_request'],
            'smliser_download_image'    => [__CLASS__, 'parse_proxy_image_request'],
            'smliser_save_plugin'       => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_theme'        => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_software'     => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_license'      => [__CLASS__, 'parse_license_save_request'],
            'smliser_app_asset_upload'  => [__CLASS__, 'parse_app_asset_upload_request'],
            'smliser_app_asset_delete'  => [__CLASS__, 'parse_app_asset_delete_request'],

            'smliser_authorize_app'     => [Smliser_Api_Cred::class, 'oauth_client_consent_handler'],
        ];

        if ( isset( $handler_map[$trigger] ) ) {
            $callback   = $handler_map[$trigger];
            is_callable( $callback ) && $callback();

        }

        
    }

    /**
     * Parses request meant for an application zip file download.
     */
    private static function parse_public_package_download() {
        $app_type = get_query_var( 'smliser_app_type' );
        $app_slug = sanitize_and_normalize_path( get_query_var( 'smliser_app_slug' ) );

        if ( empty( $app_slug ) ) {
            smliser_abort_request(
                __( 'Please provide the correct application slug', 'smliser' ),
                'Bad Request',
                array( 'response' => 400 )
            );
        }

        // Construct the FileRequest object.
        $request = new FileRequest([
            'app_type'        => $app_type,
            'app_slug'        => $app_slug,
            'download_token'  => smliser_get_query_param( 'download_token' ),
            'authorization'   => smliser_get_authorization_header(),
            'user_agent'      => smliser_get_user_agent(),
            'request_time'    => time(),
            'client_ip'       => \smliser_get_client_ip(),
        ]);

        $response = FileRequestController::get_application_zip_file( $request );

        if ( ! $response->is_valid_zip_file() ) {
            $response->set_exception( new FileRequestException( 'file_corrupted' ) );
        }
        
        $response->send();
    }

    /**
     * Parses requests when admin downloads a ackage from the backend.
     */
    private static function parse_admin_download_request() {
        if ( ! wp_verify_nonce( smliser_get_query_param( 'download_token' ) , 'smliser_download_token' ) ) {
            smliser_abort_request( __( 'Expired download link, please refresh current page.', 'smliser' ), 'Expired Link', array( 'response' => 400 ) );
        }

        if ( ! is_admin() || ! current_user_can( 'install_plugins' ) ) {
            smliser_abort_request( __( 'You are not authorized to perform this action.', 'smliser' ), 'Unathorized Download', array( 'response' => 400 ) );
        }

        $type   = smliser_get_query_param( 'type' );
        $id     = smliser_get_query_param( 'id' );

        if ( empty( $type ) || empty( $id ) ) {
            smliser_abort_request( __( 'Invalid download request.', 'smliser' ), 'Invalid Request', array( 'response' => 400 ) );
        }

        $request = new FileRequest([
            'app_type'  => $type,
            'app_id'    => $id
        ]);

        $response = FileRequestController::get_admin_application_zip_file( $request );

        $response->send();
    }
    
    /**
     * Parse the license document download request.
     * @example The expected URL will look like siteurl/downloads-page/documents/licence_id/
     * @example The download token is required, and must be in the url query parameter like
     *              siteurl/downloads-page/documents/licence_id/?download_token={{token}} or in the
     *              http authorization bearer header.
    */
    private static function parse_license_document_download() {
        // Construct the FileRequest object.
        $request = new FileRequest([
            'license_id'        => absint( get_query_var( 'license_id' ) ),
            'download_token'    => smliser_get_query_param( 'download_token' ),
            'authorization'     => smliser_get_authorization_header(),
            'user_agent'        => smliser_get_user_agent(),
            'request_time'      => time(),
            'client_ip'         => smliser_get_client_ip(),
            'is_authorized'     => current_user_can( 'manage_options' ),
            'issuer'            => get_option( 'smliser_company_name', get_bloginfo( 'name' ) ),
            'terms_url'         => get_option( 'smliser_license_term_url', 'https://callismart.com.ng/terms/' )
        ]);

        $response = FileRequestController::get_license_document( $request );

        $response->send();
    }

    /**
     * Serve inline static assets and images with aggressive caching.
     */
    private static function parse_app_asset_request() {
        $app_type   = sanitize_text_field( unslash( get_query_var( 'smliser_app_type' ) ) );
        $app_slug   = sanitize_text_field( unslash( get_query_var( 'smliser_app_slug' ) ) );
        $asset_name = sanitize_text_field( unslash( get_query_var( 'smliser_asset_name' ) ) );
        
        // Construct the FileRequest object.
        $request = new FileRequest([
            'app_type'      => $app_type,
            'app_slug'      => $app_slug,
            'asset_name'    => $asset_name,
            'user_agent'    => smliser_get_user_agent(),
            'request_time'  => time(),
            'client_ip'     => smliser_get_client_ip(),
        ]);

        $response = FileRequestController::get_app_static_asset( $request );

        $response->send();
    }

    /**
     * Proxy image download
     */
    public static function parse_proxy_image_request() {
        if ( ! wp_verify_nonce( smliser_get_query_param( 'security' ), 'smliser_nonce' ) ) {
            smliser_abort_request( 'Expired link please refresh current page' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            smliser_abort_request( 'You are not authorized to perform this action.' );
        }

        $image_url  = smliser_get_query_param( 'image_url', false ) ?: smliser_abort_request( 'Image URL is required' );

        // Construct the FileRequest object.
        $request = new FileRequest([
            'asset_url'     => $image_url,
            'asset_name'    => smliser_get_query_param( 'asset_name', '' ),
            'user_agent'    => smliser_get_user_agent(),
            'request_time'  => time(),
            'client_ip'     => smliser_get_client_ip(),
        ]);

        $response = FileRequestController::get_proxy_asset( $request );

        /**
         * Makes sure the temporary file is removed.
         */
        $response->register_after_serve_callback(
            function( $response ) {
                @unlink( $response->get_file() );
            }
        );
        $response->send();
    }

    /**
     * Parse request to save a hosted application.
     */
    private static function parse_save_app_request() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        // Construct the FileRequest object.
        $request = new Request([
            'is_authorized'             => current_user_can( 'manage_options' ),
            'app_type'                  => smliser_get_post_param( 'app_type', null ),
            'app_id'                    => smliser_get_post_param( 'app_id', 0 ),
            'app_name'                  => smliser_get_post_param( 'app_name', null ),
            'app_author'                => smliser_get_post_param( 'app_author', null ),
            'app_author_url'            => smliser_get_post_param( 'app_author_url', null ),
            'app_version'               => smliser_get_post_param( 'app_version', null ),
            'app_required_php_version'  => smliser_get_post_param( 'app_required_php_version', null ),
            'app_required_wp_version'   => smliser_get_post_param( 'app_required_wp_version', null ),
            'app_tested_wp_version'     => smliser_get_post_param( 'app_tested_wp_version', null ),
            'app_download_url'          => smliser_get_post_param( 'app_download_url', null ),
            'support_url'               => smliser_get_post_param( 'support_url', null ),
            'homepage_url'              => smliser_get_post_param( 'homepage_url', null ),
            'app_file'                  => isset( $_FILES['app_file'] ) && UPLOAD_ERR_OK === $_FILES['app_file']['error'] ? $_FILES['app_file'] : null,
            'user_agent'                => smliser_get_user_agent(),
            'request_time'              => time(),
            'client_ip'                 => smliser_get_client_ip(),
        ]);

        $response   = AppCollection::save_app( $request );
        $response->register_after_serve_callback( function() { die; } );
        
        $response->send();
    }

    /**
     * Parses the WP request for application asset upload, builds the Request object,
     * and calls the core controller.
     * * @throws RequestException On security or basic validation failure.
     */
    private static function parse_app_asset_upload_request() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            throw new RequestException( 'invalid_nonce', 'This action failed basic security check.' );
        }

        $is_authorized = current_user_can( 'manage_options' );
        if ( ! $is_authorized ) {
            throw new RequestException( 'permission_denied', 'You do not have the required permission to perform this operation.' );
        }
        
        $asset_file = null;
        if ( ! ( isset( $_FILES['asset_file'] ) && UPLOAD_ERR_OK === $_FILES['asset_file']['error'] ) ) {
             // Throw exception if file is missing or corrupted
             throw new RequestException( 'invalid_input', 'Uploaded file missing or corrupted.' );
        }
        $asset_file = $_FILES['asset_file'];

        $request = new Request([
            'is_authorized' => $is_authorized,
            'app_type'      => smliser_get_post_param( 'app_type' ),
            'app_slug'      => smliser_get_post_param( 'app_slug' ),
            'asset_type'    => smliser_get_post_param( 'asset_type' ),
            'asset_name'    => smliser_get_post_param( 'asset_name', '' ),
            'asset_file'    => $asset_file, // Pass the normalized file array
            'user_agent'    => smliser_get_user_agent(),
            'request_time'  => time(),
            'client_ip'     => smliser_get_client_ip(),
        ]);

        $response = AppCollection::app_asset_upload( $request );
        $response->register_after_serve_callback( function() { die; } );
        
        $response->send();
    }

    /**
     * Parses the WP request for application asset deletion, builds the Request object,
     * and calls the core controller.
     *
     * @throws RequestException On security or basic validation failure.
     */
    private static function parse_app_asset_delete_request() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            throw new RequestException( 'invalid_nonce', 'This action failed basic security check.' );
        }

        $is_authorized = current_user_can( 'manage_options' );
        if ( ! $is_authorized ) {
            throw new RequestException( 'permission_denied', 'You do not have the required permission to perform this operation.' );
        }

        $request = new Request([
            'is_authorized' => $is_authorized,
            'app_type'      => smliser_get_post_param( 'app_type' ),
            'app_slug'      => smliser_get_post_param( 'app_slug' ),
            'asset_name'    => smliser_get_post_param( 'asset_name' ),
            'user_agent'    => smliser_get_user_agent(),
            'request_time'  => time(),
            'client_ip'     => smliser_get_client_ip(),
        ]);

        $response = AppCollection::app_asset_delete( $request );
        $response->register_after_serve_callback( function() { die; } );

        $response->send();
    }

    /**
     * Parse license save form request
     */
    private static function parse_license_save_request() {
        if ( ! wp_verify_nonce( smliser_get_post_param( 'smliser_nonce_field' ), 'smliser_nonce_field' ) ) {
            wp_safe_redirect( smliser_license_admin_action_page() );
            exit;
        }

        $request    = new Request([
            'is_authorized'         => current_user_can( 'manage_options' ),
            'license_id'            => smliser_get_post_param( 'license_id', 0 ),
            'user_id'               => smliser_get_post_param( 'user_id', 0 ),
            'service_id'            => smliser_get_post_param( 'service_id' ),
            'status'                => smliser_get_post_param( 'status' ),
            'start_date'            => smliser_get_post_param( 'start_date' ),
            'end_date'              => smliser_get_post_param( 'end_date' ),
            'app_prop'              => smliser_get_post_param( 'app_prop' ),
            'max_allowed_domains'   => smliser_get_post_param( 'allowed_sites' ),
        ]);

        $response   = Controller::save_license( $request );

        if ( $response->ok() ) {
            $license_id = $response->get_response_data()->get( 'license' )->get_id();
            wp_safe_redirect( smliser_license_admin_action_page( 'edit', $license_id ) );
            exit;
        }

        wp_safe_redirect( smliser_license_admin_action_page() );
        exit;
    }

    /**
     * Authentication Tempalte file loader
     */
    public function load_auth_template( $template ) {
        global $wp_query;
        if ( isset( $wp_query->query_vars[ 'smliser_auth' ] ) ) {
            $template = SMLISER_PATH . 'templates/auth/auth-controller.php';
        }
        
        return $template;
    }

    /*
    |----------------
    |UTILITY METHODS
    |----------------
    */

    /**
     * Instance of Smiliser_Server
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
     * Get the download request callback.
     *  
     * @return callable
     */
    private static function resolve_download_callback() {
        switch ( get_query_var( 'smliser_app_type' ) ) {
            case 'plugin':
            case 'plugins':
            case 'theme':
            case 'themes':
            case 'software':
            case 'softwares':
                return [__CLASS__, 'parse_public_package_download'];
            case 'document':
            case 'documents':
                return [__CLASS__, 'parse_license_document_download'];

            
        }

        return function () {
            smliser_abort_request( new Exception( 'unsupported_route', 'The rquested route is not supported', ['status' => 404 ] ) );
        };
    }
}

WPAdapter::instance();