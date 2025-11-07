<?php
/**
 * REST API Server file
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

namespace SmartLicenseServer;

use SmartLicenseServer\DownloadsApi\FileRequestController;
use SmartLicenseServer\DownloadsApi\FileRequest;

defined( 'ABSPATH'  ) || exit;

/**
 * Serves as a request controller and a proxy server for downloads and assets.
 */
class Server extends FileRequestController {

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
        add_action( 'template_redirect', array( $this, 'asset_server' ) );
        add_action( 'admin_post_smliser_authorize_app', array( 'Smliser_Api_Cred', 'oauth_client_consent_handler' ) );
        add_action( 'admin_post_smliser_download_image', array( __CLASS__, 'proxy_image_download' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
    }

    /**
     *  Handle incoming requests for this application.
     */
    public static function init_request() {
        if ( 'smliser-downloads' === get_query_var( 'pagename' ) ) {
            self::parse_download_request();
        }

        if ( 'smliser_admin_download' === \smliser_get_query_param( 'action' ) ) {
            self::parse_admin_download_request();
        }

    }

    /**
     * Parses request meant for an application zip file download.
     */
    private static function parse_download_request() {
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

        self::serve_package( $request );
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

        $response = self::serve_admin_download( $request );

        $response->send();
    }

    /**
     * Serve license document download.
     * 
     * The expected URL should be siteurl/downloads-page/documents/licence_id/
     * The download token is required, and must be in the url query parameter like
     * siteurl/downloads-page/documents/licence_id/?download_token={{token}} or in the
     * http authorization bearer header.
     */
    private function serve_license_document_download() {
        $license_id = absint( get_query_var( 'license_id' ) );

        if ( ! $license_id ) {
            smliser_abort_request( __( 'Please provide the correct license ID', 'smliser' ), 'License ID Required',  400 );
        }

        $download_token = smliser_get_query_param( 'download_token' );
        if ( empty( $download_token ) ) { // fallback to authorization header.
            $authorization = smliser_get_authorization_header();

            if ( $authorization ) {
                $parts = explode( ' ', $authorization );
                $download_token = sanitize_text_field( unslash( $parts[1] ) );
            }
        
        }

        if ( empty( $download_token ) ) {
            smliser_abort_request( __( 'Download token is required', 'smliser' ), 'Missing token', 401 );
        }

        $license = Smliser_license::get_by_id( $license_id );

        if ( ! $license ) {
            smliser_abort_request( __( 'License was not found', 'smliser' ), 'Not found', 404 );
        }

        $item_id = $license->get_item_id();
        if ( ! smliser_verify_item_token( $download_token, $item_id ) ) {
            smliser_abort_request( __( 'Token verification failed.', 'smliser' ), 'Unauthorized', 403 );
        }

        $license_key    = $license->get_license_key();
        $service_id     = $license->get_service_id();
        $issued         = $license->get_start_date();
        $expiry         = $license->get_end_date();
        $company_name   = get_option( 'smliser_company_name', get_bloginfo( 'name' ) );
        $terms_url      = get_option( 'smliser_license_terms_url', 'https://callismart.com.ng/terms/' );
        $today          = date_i18n( 'Y-m-d H:i:s' );
        $site_limit     = $license->get_allowed_sites();
        
        $document = <<<EOT
        ========================================
        SOFTWARE LICENSE CERTIFICATE
        Issued by {$company_name}
        ========================================
        ----------------------------------------
        License Details
        ----------------------------------------
        Service ID:     {$service_id}
        License Key:    {$license_key}
        (Ref: Item ID {$item_id})

        ----------------------------------------
        License Validity
        ----------------------------------------
        Start Date:     {$issued}
        End Date:       {$expiry}
        Allowed Sites:  {$site_limit}

        ----------------------------------------
        Activation Guide
        ----------------------------------------
        Use the Service ID and License Key above to activate this software.

        Note:
        - The software already includes its internal ID.
        - Activation may vary by product. Refer to product documentation.

        ----------------------------------------
        License Terms (Summary)
        ----------------------------------------
        ✔ Use on up to {$site_limit} site(s)
        ✔ Allowed for personal or client projects
        ✘ Not allowed to resell, redistribute, or modify for resale

        Full License Agreement:
        {$terms_url}

        ----------------------------------------
        Issued By:      Smart Woo Licensing System
        Generated On:   {$today}
        ========================================
        EOT;
       
        
        status_header( 200 );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="license-document.txt"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . strlen( $document ) );
        header( 'Content-Transfer-encoding: binary' );
        echo $document;
        exit;
    }

    /**
     * Serve inline static assets and images with aggressive caching.
     */
    public function asset_server() {
        if ( 'smliser-repository-assets' !== get_query_var( 'pagename' ) ) {
            return;
        }

        $app_type   = sanitize_text_field( unslash( get_query_var( 'smliser_app_type' ) ) );
        $app_slug   = sanitize_text_field( unslash( get_query_var( 'smliser_app_slug' ) ) );
        $asset_name = sanitize_text_field( unslash( get_query_var( 'smliser_asset_name' ) ) );

        $repo_class = Smliser_Software_Collection::get_app_repository_class( $app_type );
        if ( ! $repo_class ) {
            smliser_abort_request( 'Invalid app type', 'Asset Error', [ 'response' => 400 ] );
        }

        $file_path = $repo_class->get_asset_path( $app_slug, $asset_name );
        if ( is_smliser_error( $file_path ) ) {
            smliser_abort_request( $file_path->get_error_message(), 'Asset Error', [ 'response' => $file_path->get_error_code() ] );
        }

        if ( ! $repo_class->is_readable( $file_path ) ) {
            smliser_abort_request( 'Invalid or corrupted file', 'Asset Error', [ 'response' => 500 ] );
        }

        // --- File details ---
        $mime          = wp_check_filetype( $file_path );
        $mime_type     = $mime['type'] ?: 'application/octet-stream';
        $filesize      = $repo_class->filesize( $file_path );
        $last_modified = gmdate( 'D, d M Y H:i:s', $repo_class->filemtime( $file_path ) ) . ' GMT';
        $etag          = '"' . md5( $filesize . $last_modified ) . '"';

        // --- Handle conditional requests (304) ---
        if (
            ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) ||
            ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) === $last_modified )
        ) {
            status_header( 304 );
            header( 'Cache-Control: public, max-age=31536000, immutable' );
            header( 'ETag: ' . $etag );
            header( 'Last-Modified: ' . $last_modified );
            exit;
        }

        // --- Send headers ---
        status_header( 200 );
        header( 'Content-Type: ' . $mime_type );
        header( 'Content-Length: ' . $filesize );
        header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
        header( 'Cache-Control: public, max-age=31536000, immutable' );
        header( 'Pragma: public' );
        header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 31536000 ) . ' GMT' );
        header( 'ETag: ' . $etag );
        header( 'Last-Modified: ' . $last_modified );

        // --- Stream the file ---
        $repo_class->readfile( $file_path );
        exit;
    }

    /**
     * Proxy image download
     */
    public static function proxy_image_download() {
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
}

Server::instance();