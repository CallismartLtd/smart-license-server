<?php
/**
 * REST API Server file
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

namespace SmartLicenseServer;

defined( 'ABSPATH'  ) || exit;

/**
 * Serves as a request controller and a proxy server for downloads and assets.
 */
class Server {

    /**
     * Single instance of this class.
     * 
     * @var Smliser_Server $instance Instance.
     */
    private static $instance = null;

    /**
     * Permission
     * 
     * @var string $permission
     */
    private $permission = '';

    /**
     * Class constructor.
     */
    public function __construct() {
        add_action( 'admin_post_smliser_admin_download_plugin', array( __CLASS__, 'serve_admin_download' ) );
        add_action( 'template_redirect', array( $this, 'download_server' ) );
        add_action( 'template_redirect', array( $this, 'asset_server' ) );
        add_action( 'admin_post_smliser_authorize_app', array( 'Smliser_Api_Cred', 'oauth_client_consent_handler' ) );
        add_action( 'admin_post_smliser_download_image', array( __CLASS__, 'proxy_image_download' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
    }

    /**
     * Call the correct item download handler.
     */
    public static function download_server() {
        if ( 'smliser-downloads' !== get_query_var( 'pagename' ) ) {
            return;
        }

        $app_type = get_query_var( 'smliser_app_type' );

        switch ( $app_type ) {
            case 'plugins':
                self::serve_package_download();
                break;
            case 'themes':
                // Handle themes download.
                break;
            case 'software':
                // Handle software download.
                break;
            case 'documents':
                self::instance()->serve_license_document_download();
                break;
            default:
                //redirect to repository page.
        }
        
    }

    /**
     * Serve plugin Download.
     * 
     * The expected URL should be siteurl/donloads-page/plugins/plugin_slug.zip
     * Additionally for licensed plugins, append the download token to the URL query parameter like
     * siteurl/donloads-page/plugins/plugin_slug.zip?download_token={{token}} or
     * in the http authorization bearer header.
     */
    private static function serve_package_download() {
        $app_type = get_query_var( 'smliser_app_type' );
        if ( 'plugins' !== $app_type ) {
            return;
        }
            
        $plugin_slug    = sanitize_and_normalize_path( get_query_var( 'smliser_app_slug' ) );

        if ( empty( $plugin_slug ) ) {
            wp_die( 'File slug or extension missing', 'Download Error', 400 );
        }

        $plugin = Smliser_Plugin::get_by_slug( $plugin_slug );

        if ( ! $plugin ) {
            wp_die( 'Plugin not found.', 'File not found', 404 );
        }

        /**
         * Serve download for monetized plugin
         */
        if ( $plugin->is_monetized() ) {
            $download_token    = smliser_get_query_param( 'download_token' );

            if ( empty( $download_token ) ) { // fallback to authorization header.
                $authorization = smliser_get_authorization_header();

                if ( $authorization ) {
                    $parts = explode( ' ', $authorization );
                    $download_token = sanitize_text_field( unslash( $parts[1] ) );
                }
            
            }

            if ( empty( $download_token ) ) {
                wp_die( 'Licensed plugin, please provide download token.', 'Download Token Required', 400 );
            }

            $item_id = $plugin->get_item_id();

            if ( ! smliser_verify_item_token( $download_token, $item_id ) ) {
                wp_die( 'Invalid token, you may need to purchase a license for this plugin.', 'Unauthorized', 401 );
            }

        }

        $slug           = $plugin->get_slug();
        $plugin_path    = $smliser_repo->get_plugin( $slug );    
        
        if ( ! $smliser_repo->exists( $plugin_path ) ) {
            wp_die( 'Plugin file does not exist.', 'File not found', 404 );
        }

        // Serve the file for download.
        if ( $smliser_repo->is_readable( $plugin_path ) ) {

            /**
             * Fires for download stats syncronization.
             * 
             * @param string $context The context which the hook is fired.
             * @param Smliser_Plugin The plugin object (optional).
             */
            do_action( 'smliser_stats', 'plugin_download', $plugin );

            status_header( 200 );
            header( 'x-content-type-options: nosniff' );
            header( 'x-Robots-tag: noindex, nofollow', true );
            header( 'content-description: file transfer' );
            if ( isset($_SERVER['HTTP_USER_AGENT'] ) && strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) ) {
                header( 'content-Type: application/force-download' );
            } else {
                header( 'content-Type: application/zip' );
            }
            
            header( 'content-disposition: attachment; filename="' . basename( $plugin_path ) . '"' );
            header( 'expires: 0' );
            header( 'cache-control: must-revalidate' );
            header( 'pragma: public' );
            header( 'content-length: ' . $smliser_repo->size( $plugin_path ) );
            header( 'content-transfer-encoding: binary' );
            
            $smliser_repo->readfile( $plugin_path );
            exit;
        } else {
            wp_die( 'Error: The file cannot be read.', 'File Reading Error', 500 );
        }
        
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
            wp_die( __( 'Please provide the correct license ID', 'smliser' ), 'License ID Required',  400 );
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
            wp_die( __( 'Download token is required', 'smliser' ), 'Missing token', 401 );
        }

        $license = Smliser_license::get_by_id( $license_id );

        if ( ! $license ) {
            wp_die( __( 'License was not found', 'smliser' ), 'Not found', 404 );
        }

        $item_id = $license->get_item_id();
        if ( ! smliser_verify_item_token( $download_token, $item_id ) ) {
            wp_die( __( 'Token verification failed.', 'smliser' ), 'Unauthorized', 403 );
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
     * Serve admin plugin download.
     * 
     * @global Smliser_Repository $smliser_repo The Repository instance()
     */
    public static function serve_admin_download() {
        if ( ! isset( $_GET['download_token'] ) || ! wp_verify_nonce( sanitize_text_field( unslash( $_GET['download_token'] ) ), 'smliser_download_token' ) ) {
            wp_die( 'Invalid token', 401 );
        }

        if ( ! is_admin() || ! current_user_can( 'install_plugins' ) ) {
            wp_die( __( 'You are not authorized to perform this action.', 'smliser' ), 'Unathorized Download', array( 'response_code' => 400 ) );
        }

        $item_id    = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        $plugin_obj = new \Smliser_Plugin();
        $the_plugin = $plugin_obj->get_plugin( $item_id );

        if ( ! $the_plugin ) {
            wp_die( 'Invalid or deleted plugin', 404 );
        }

        $slug       = $the_plugin->get_slug();
        $file_path  = $smliser_repo->get_plugin( $slug );
        
        if ( ! $smliser_repo->exists( $file_path ) ) {
            wp_die( 'Plugin file does not exist.', 404 );
        }

        // Serve the file for download.
        if ( $smliser_repo->is_readable( $file_path ) ) {

            /**
             * Fires for download stats syncronization.
             * 
             * @param string $context The context which the hook is fired.
             * @param Smliser_Plugin The plugin object (optional).
             */
            do_action( 'smliser_stats', 'plugin_download', $the_plugin );

            header( 'content-description: File Transfer' );
            header( 'content-type: application/zip' );
            header( 'content-disposition: attachment; filename="' . basename( $file_path ) . '"' );
            header( 'expires: 0' );
            header( 'cache-control: must-revalidate' );
            header( 'pragma: public' );
            header( 'content-length: ' . $smliser_repo->size( $file_path ) );
            $smliser_repo->readfile( $file_path );
            exit;
        } else {
            wp_die( 'Error: The file cannot be read.' );
        }

        wp_safe_redirect( smliser_admin_repo_tab( 'view', $item_id ) );
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
            wp_die( 'Invalid app type', 'Asset Error', [ 'response' => 400 ] );
        }

        $file_path = $repo_class->get_asset_path( $app_slug, $asset_name );
        if ( is_smliser_error( $file_path ) ) {
            wp_die( $file_path->get_error_message(), 'Asset Error', [ 'response' => $file_path->get_error_code() ] );
        }

        if ( ! $repo_class->is_readable( $file_path ) ) {
            wp_die( 'Invalid or corrupted file', 'Asset Error', [ 'response' => 500 ] );
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
            wp_die( 'Expired link please refresh current page' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You are not authorized to perform this action.' );
        }

        $image_url  = smliser_get_query_param( 'image_url', false ) ?: wp_die( 'Image URL is required' );

        $file = download_url( $image_url );

        if ( is_smliser_error( $file ) ) {
            wp_die( $file->get_error_message() );
        }
        
        $content_type = mime_content_type( $file );
        $allowed_types = array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' );

        if ( ! in_array( $content_type, $allowed_types, true ) ) {
            @unlink( $file );
            wp_die( 'Only valid image types are allowed.' );
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
            $oauth_template_handler = SMLISER_PATH . 'templates/auth/auth-controller.php';
            return $oauth_template_handler;
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