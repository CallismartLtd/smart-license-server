<?php
/**
 * File for the WordPress adapter
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

namespace SmartLicenseServer;

use SmartLicenseServer\DownloadsApi\FileRequestController;
use SmartLicenseServer\DownloadsApi\FileRequest;
use SmartLicenseServer\Exception\FileRequestException;

defined( 'ABSPATH'  ) || exit;

/**
 * Wordpress adapter bridges the gap beween Smart License Server and request from
 * WP environments
 */
class WP_Adapter {

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
        add_action( 'admin_post_smliser_admin_download', array( __CLASS__, 'init_request' ) );
        add_action( 'template_redirect', array( __CLASS__, 'init_request' ) );
        add_action( 'admin_post_smliser_authorize_app', array( 'Smliser_Api_Cred', 'oauth_client_consent_handler' ) );
        // add_action( 'admin_post_smliser_download_image', array( __CLASS__, 'proxy_image_download' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
    }

    /**
     *  Handle incoming requests for this application.
     */
    public static function init_request() {
        global $wp_query;


        $page   = get_query_var( 'pagename' );

        if ( ! $page ) {
            $page = smliser_get_query_param( 'action' );
        }

        $handler_map    = [
            'smliser-downloads'         => function() {
                ( self::resolve_download_callback() )();
            },
            'smliser-repository-assets' => [__CLASS__, 'parse_app_asset_request'],
            'smliser_admin_download'    => [__CLASS__, 'parse_admin_download_request'],
            'smliser_download_image'    => [__CLASS__, 'parse_proxy_image_request']
        ];

        $callback = $handler_map[$page] ?? '';

        // \pretty_print( $callback );

        is_callable( $callback ) && call_user_func( $callback );
        
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
    public static function parse_app_asset_request() {
        $app_type   = sanitize_text_field( unslash( get_query_var( 'smliser_app_type' ) ) );
        $app_slug   = sanitize_text_field( unslash( get_query_var( 'smliser_app_slug' ) ) );
        $asset_name = sanitize_text_field( unslash( get_query_var( 'smliser_asset_name' ) ) );
        
        // Construct the FileRequest object.
        $request = new FileRequest([
            'app_type'          => $app_type,
            'app_slug'          => $app_slug,
            'asset_name'        => $asset_name,
            'user_agent'        => smliser_get_user_agent(),
            'request_time'      => time(),
            'client_ip'         => smliser_get_client_ip(),
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

        $file = download_url( $image_url );

        if ( is_smliser_error( $file ) ) {
            smliser_abort_request( $file->get_error_message() );
        }
        
        $content_type = mime_content_type( $file );
        $allowed_types = array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' );

        if ( ! in_array( $content_type, $allowed_types, true ) ) {
            @unlink( $file );
            smliser_abort_request( 'Only valid image types are allowed.' );
        }

        $filename = basename( parse_url( $image_url, PHP_URL_PATH ) );

        status_header( 200 );
        header( 'content-description: File Transfer' );
        header( 'content-type: ' . $content_type );
        header( 'content-disposition: inline; filename="' . $filename . '"' );
        header( 'expires: 0' );
        header( 'cache-control: public, max-age=86400' );
        header( 'access-control-allow-Origin: ' . esc_url_raw( site_url() ) );
        header( 'content-length: ' . filesize( $file ) );
        readfile( $file );

        @unlink( $file );
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

WP_Adapter::instance();