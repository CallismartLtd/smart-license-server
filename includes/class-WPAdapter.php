<?php
/**
 * File for the WordPress adapter
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

namespace SmartLicenseServer;

use SmartLicenseServer\Admin\BulkMessagePage;
use SmartLicenseServer\Admin\Menu;
use SmartLicenseServer\Admin\OptionsPage;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequestController;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequest;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Monetization\Controller;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\ProviderCollection;
use SmartLicenseServer\RESTAPI\Versions\V1;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;


defined( 'ABSPATH'  ) || exit;

/**
 * Wordpress adapter bridges the gap beween Smart License Server and request from
 * WP environments
 */
class WPAdapter extends Config implements EnvironmentProviderInterface {

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
        $repo_path      = \WP_CONTENT_DIR;
        $absolute_path  = \ABSPATH;
        $db_prefix      = $GLOBALS['wpdb']?->prefix;
        parent::instance( \compact( 'absolute_path', 'db_prefix', 'repo_path' ) );

        add_action( 'admin_init', [__CLASS__, 'init_request'] );
        add_action( 'template_redirect', array( __CLASS__, 'init_request' ) );
        add_filter( 'template_include', array( $this, 'load_auth_template' ) );
        add_action( 'smliser_clean', [DownloadToken::class, 'clean_expired_tokens'] );
        add_action( 'init', [__CLASS__, 'auto_register_monetization_providers'] );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_request_before_callbacks', [__CLASS__, 'rest_request_before_callbacks'], -1, 3 );
        add_filter( 'rest_post_dispatch', [__CLASS__, 'filter_rest_response'], 10, 3 );
        add_filter( 'rest_pre_dispatch', array( __CLASS__, 'enforce_https_for_rest_api' ), 10, 3 );        
        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );
        add_action( 'admin_menu', [Menu::class, 'register_menus'] );
        add_action( 'admin_menu', [Menu::class, 'modify_sw_menu'], 999 );
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
            'smliser-downloads'                             => function() { ( self::resolve_download_callback() )(); },
            'smliser-repository-assets'                     => [__CLASS__, 'parse_app_asset_request'],
            'smliser_admin_download'                        => [__CLASS__, 'parse_admin_download_request'],
            'smliser_download_image'                        => [__CLASS__, 'parse_proxy_image_request'],
            'smliser_save_plugin'                           => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_theme'                            => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_software'                         => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_license'                          => [__CLASS__, 'parse_license_save_request'],
            'smliser_remove_licensed_domain'                => [__CLASS__, 'parse_licensed_domain_removal'],
            'smliser_app_asset_upload'                      => [__CLASS__, 'parse_app_asset_upload_request'],
            'smliser_app_asset_delete'                      => [__CLASS__, 'parse_app_asset_delete_request'],
            'smliser_save_monetization_tier'                => [__CLASS__, 'parse_monetization_tier_form'],
            'smliser_authorize_app'                         => [SmliserAPICred::class, 'oauth_client_consent_handler'],
            'smliser_bulk_action'                           => [__CLASS__, 'parse_bulk_action_request'],
            'smliser_all_actions'                           => [__CLASS__, 'parse_bulk_action_request'],
            'smliser_generate_download_token'               => [__CLASS__, 'parse_download_token_generation_request'],
            'smliser_app_status_action'                     => [__CLASS__, 'parse_app_status_action_request'],
            'smliser_save_monetization_provider_options'    => [__CLASS__, 'parse_save_provider_options'],
            'smliser_upgrade'                               => [__CLASS__, 'parse_database_migration_request'],
            'smliser_key_generate'                          => [SmliserAPICred::class, 'admin_create_cred_form'],
            'smliser_revoke_key'                            => [SmliserAPICred::class, 'revoke'],
            'smliser_oauth_login'                           => [SmliserAPICred::class, 'oauth_login_form_handler'],
            'smliser_publish_bulk_message'                  => [BulkMessagePage::class, 'publish_bulk_message'],
            'smliser_bulk_message_bulk_action'              => [BulkMessagePage::class, 'bulk_action'],
            'smliser_options'                               => [OptionsPage::class, 'options_form_handler'],
            'smliser_get_product_data'                      => [__CLASS__, 'parse_monetization_provider_product_request'],
            'smliser_delete_monetization_tier'              => [__CLASS__, 'parse_monetization_tier_deletion'],
            'smliser_toggle_monetization'                   => [__CLASS__, 'parse_toggle_monetization'],
        ];

        if ( isset( $handler_map[$trigger] ) ) {
            $callback   = $handler_map[$trigger];
            is_callable( $callback ) && $callback();

        }        
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes() {
        $api_config = V1::get_routes();
        $namespace = $api_config['namespace'];
        $routes = $api_config['routes'];

        foreach ( $routes as $route_config ) {
            register_rest_route(
                $namespace,
                $route_config['route'],
                array(
                    'methods'             => $route_config['methods'],
                    'callback'            => $route_config['callback'],
                    'permission_callback' => $route_config['permission'],
                    'args'                => $this->validate_rest_args( $route_config['args'] ),
                )
            );
        }        
    }

    /**
     * Parses request meant for an application zip file download.
     */
    private static function parse_public_package_download() {
        $app_type = get_query_var( 'smliser_app_type' );
        $app_slug = smliser_sanitize_path( get_query_var( 'smliser_app_slug' ) );

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

        if ( ! $response->is_valid_zip_file() && $response->ok() ) {
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
        $settings   = \smliser_settings_adapter();
        // Construct the FileRequest object.
        $request = new FileRequest([
            'license_id'        => absint( get_query_var( 'license_id' ) ),
            'download_token'    => smliser_get_query_param( 'download_token' ),
            'authorization'     => smliser_get_authorization_header(),
            'user_agent'        => smliser_get_user_agent(),
            'request_time'      => time(),
            'client_ip'         => smliser_get_client_ip(),
            'is_authorized'     => current_user_can( 'manage_options' ),
            'issuer'            => $settings->get( 'smliser_company_name', get_bloginfo( 'name' ) ),
            'terms_url'         => $settings->get( 'smliser_license_term_url', 'https://callismart.com.ng/terms/' )
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
    private static function parse_proxy_image_request() {
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
            'app_support_url'           => smliser_get_post_param( 'app_support_url', null ),
            'app_homepage_url'          => smliser_get_post_param( 'app_homepage_url', null ),
            'app_preview_url'          => smliser_get_post_param( 'app_preview_url', null ),
            'app_external_repository_url'   => smliser_get_post_param( 'app_external_repository_url', null ),
            'app_file'                  => isset( $_FILES['app_file'] ) && UPLOAD_ERR_OK === $_FILES['app_file']['error'] ? $_FILES['app_file'] : null,
            'user_agent'                => smliser_get_user_agent(),
            'request_time'              => time(),
            'client_ip'                 => smliser_get_client_ip(),
        ]);

        $response   = HostingController::save_app( $request );
        
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

        $response = HostingController::app_asset_upload( $request );        
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

        $response = HostingController::app_asset_delete( $request );
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
            'max_allowed_domains'   => smliser_get_post_param( 'allowed_sites', -1 ),
        ]);

        $response   = Controller::save_license( $request );

        if ( $response->ok() ) {
            $license_id = $response->get_response_data()->get( 'license' )->get_id();

            $url    = new URL( smliser_license_admin_action_page( 'edit', $license_id ) );
            $url->add_query_param( 'message', 'Saved' );
            wp_safe_redirect( $url->__toString() );
            exit;
        }

        wp_safe_redirect( smliser_license_admin_action_page() );
        exit;
    }

    /**
     * Parse bulk action
     */
    private static function parse_bulk_action_request() {
        $table_nonce_verified   = wp_verify_nonce( smliser_get_post_param( 'smliser_table_nonce' ), 'smliser_table_nonce' );
        $smliser_nonce_verified = wp_verify_nonce( smliser_get_param( 'smliser_nonce', '', $_REQUEST ), 'smliser_nonce' );
        if ( ! $table_nonce_verified && ! $smliser_nonce_verified ) {
            \wp_safe_redirect( \wp_get_referer() );
            exit;
        }

        $context    = \smliser_get_param( 'context', null, $_REQUEST ) ?? \smliser_abort_request( 'Bulk action context is required', 'Context Required', array( 'status_code' => 400 ) );
        $handler    = self::resolve_bulk_action_handler( $context );

        $request    = new Request([
            'ids'   => smliser_get_param( 'ids', [], $_REQUEST ),
            'bulk_action'   => \smliser_get_param( 'bulk_action', '', $_REQUEST ),
        ]);

        if ( 'repository' === $context ) {
            $ids    = $request->get( 'ids', [] );
            $request->set( 'ids', self::normalize_app_ids_form_input( (array) $ids ) );
        }

        /** @var Response $response */
        $response   = \call_user_func( $handler, $request );

        if ( $response->ok() ) {
            $target = $request->get( 'redirect_url' );
            $url    = new URL( $target );
            $url->add_query_param( 'message', $response->get_response_data()->get( 'message' ) );
            wp_safe_redirect( $url->get_href() );
            exit;
        }

        wp_safe_redirect( \wp_get_referer() );
        exit;
    }

    /**
     * Parses the WP request for saving a monetization tier, builds the Request object,
     * and calls the refactored core controller.
     */
    private static function parse_monetization_tier_form() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        $request = new Request([
            'is_authorized'     => current_user_can( 'manage_options' ),
            'monetization_id'   => smliser_get_post_param( 'monetization_id', 0 ),
            'app_id'           => smliser_get_post_param( 'app_id', 0 ),
            'tier_id'           => smliser_get_post_param( 'tier_id', 0 ),
            'app_type'         => smliser_get_post_param( 'app_type' ),
            'tier_name'         => smliser_get_post_param( 'tier_name' ),
            'product_id'        => smliser_get_post_param( 'product_id' ),
            'billing_cycle'     => smliser_get_post_param( 'billing_cycle' ),
            'provider_id'       => smliser_get_post_param( 'provider_id' ),
            'max_sites'         => smliser_get_post_param( 'max_sites', -1 ),
            'features'          => smliser_get_post_param( 'features' ),
            'user_agent'        => smliser_get_user_agent(),
            'request_time'      => time(),
            'client_ip'         => smliser_get_client_ip(),
        ]);

        $response = Controller::save_monetization( $request );

        $response->send();
    }

    /**
     * Options form handler
     */
    public static function options_form_handler() {
        if ( ! current_user_can( 'install_plugins' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to do this.') );
        }

        if ( isset( $_POST['smliser_page_setup'] ) && wp_verify_nonce( \smliser_get_post_param( 'smliser_options_form' ), 'smliser_options_form' ) ) {
            
            if ( isset( $_POST['smliser_permalink'] ) ) {
                $permalink = preg_replace( '~(^\/|\/$)~', '', \smliser_get_post_param( 'smliser_permalink' ) );
                \smliser_settings_adapter()->set( 'smliser_repo_base_perma', ! empty( $permalink ) ? strtolower( $permalink ) : 'plugins'  );
                
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smliser-options&path=pages&success=true' ) );
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

    /**
     * Parse donwload token generation request
     *
     * @return void
     */
    private static function parse_download_token_generation_request() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            \smliser_send_json_error( array( 'message' => 'Invalid CSRF token, please refresh current page.' ) );
        }
        if ( ! current_user_can( 'install_plugins' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to do this.') );
        }
        
        $license_id = \smliser_get_post_param( 'license_id', null ) ?? \smliser_send_json_error( array( 'message' => 'License ID is required.' ) );
        $expiry     = \smliser_get_post_param( 'expiry', 10 * \DAY_IN_SECONDS ) ?: 10 * \DAY_IN_SECONDS;

        $license    = License::get_by_id( $license_id );

        if ( ! $license ) {
            \smliser_send_json_error( array( 'message' => 'This license does not exist.' ) );
        }

        if ( \is_string( $expiry ) ) {
            $expiry = \strtotime( $expiry ) - time();
            if ( $expiry < 0 ) {
                $expiry = 10 * \DAY_IN_SECONDS;
            }
        }

        $token  = \smliser_generate_item_token( $license, $expiry );

        smliser_send_json_success( array( 'token' => $token ) );

    }

    /**
     * Parse app deletion request
     *
     * @return void
     */
    private static function parse_app_status_action_request() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            \smliser_send_json_error( array( 'message' => 'Invalid CSRF token, please refresh current page.' ) );
        }

        if ( ! current_user_can( 'install_plugins' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to do this.') );
        }
    
        $request    = new Request([
            'slug'          => \smliser_get_query_param( 'slug' ),
            'type'          => \smliser_get_query_param( 'type' ),
            'status'        => \smliser_get_query_param( 'status' ),
            'is_authorized' => true,
        ]);

        $response   = HostingController::change_app_status( $request );
        $response->send();
    
    }

    /**
     * Parse database migration request
     */
    public static function parse_database_migration_request() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            \smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! \current_user_can( 'manage_options' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $repo_version = \smliser_settings_adapter()->get( 'smliser_repo_version', 0 );
        if ( SMLISER_VER === $repo_version ) {
            \smliser_send_json_error( array( 'message' => 'No upgrade needed' ) );
        }

        if ( Installer::install() )  {
            Installer::db_migrate();   
        }

        \smliser_settings_adapter()->set( 'smliser_repo_version', SMLISER_VER );

        \smliser_send_json_success( array( 'message' => 'The repository has been migrated from version "' . $repo_version . '" to version "' . SMLISER_VER ) );
    }

    /**
     * Parse the monetization provider settings form submision.
     */
    private static function parse_save_provider_options() {
        if ( ! \check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            \smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! \current_user_can( 'manage_options' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $request    = new Request([
            'provider_id'   => \smliser_get_post_param( 'provider_id' ),
            'is_authorized' => true

        ]);

        $response = Controller::save_provider_options( $request );

        $response->send();
        
    }
    
    /**
     * Parse monetization toggle request
     */
    private static function parse_toggle_monetization() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
                'field_id' => 'security',
            ), 401 );
        }

        if ( ! \current_user_can( 'manage_options' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $request    = new Request([
            'monetization_id'   => \smliser_get_post_param( 'monetization_id' ),
            'enabled'           => \smliser_get_post_param( 'enabled' ),
            'is_authorized'     => true

        ]);

        $response   = Controller::toggle_monetization( $request );

        $response->send();
    }

    /**
     * Parse monetization tier provider product request.
     */
    private static function parse_monetization_provider_product_request() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => 'This action failed basic security check.',
            ), 401 );
        }

        if ( ! \current_user_can( 'manage_options' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $request    = new Request([
            'provider_id'   => smliser_get_query_param( 'provider_id' ),
            'product_id'    => smliser_get_query_param( 'product_id' ),
            'is_authorized' => true
        ]);

        $response   = Controller::get_provider_product( $request );

        $response->send();
    }

    /**
     * Parser monetization tier deletion
     */
    private static function parse_monetization_tier_deletion() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
                'field_id' => 'security',
            ), 401 );
        }

        if ( ! \current_user_can( 'manage_options' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $request    = new Request([
            'monetization_id'   => \smliser_get_post_param( 'monetization_id', 0 ),
            'tier_id'           => \smliser_get_post_param( 'tier_id', 0 ),
            'is_authorized'     => true

        ]);

        $response   = Controller::delete_monetization_tier( $request );

        $response->send();
    }

    /**
     * Parse licensed domain removal
     */
    private static function parse_licensed_domain_removal() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
                'field_id' => 'security',
            ), 401 );
        }

        if ( ! \current_user_can( 'manage_options' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $request    = new Request([
            'license_id'    => \smliser_get_query_param( 'license_id', 0 ),
            'domain'        => \smliser_get_query_param( 'domain' ),
            'is_authorized' => true

        ]);

        $response   = Controller::uninstall_domain_from_license( $request );

        $response->send();
    }
    
    /**
    |------------------------
    | REST API Configuration
    |------------------------
     */
    /**
     * Ensures HTTPS/TLS for REST API endpoints within the plugin's namespace.
     *
     * Checks if the current REST API request belongs to the plugin's namespace
     * and enforces HTTPS/TLS requirements if the environment is production.
     *
     * @return WP_Error|null WP_Error object if HTTPS/TLS requirement is not met, null otherwise.
     */
    public static function enforce_https_for_rest_api( $result, $server, $request ) {
        // Check if current request belongs to the plugin's namespace.
        if ( ! str_contains( $request->get_route(), self::namespace() ) ) {
            return;
        }

        // Check if environment is production and request is not over HTTPS.
        if ( 'production' === wp_get_environment_type() && ! is_ssl() ) {
            // Create WP_Error object to indicate insecure SSL.
            $error = new WP_Error( 'connection_not_secure', 'HTTPS/TLS is required for secure communication.', array( 'status' => 400, ) );
            
            // Return the WP_Error object.
            return $error;
        }
    }

    /**
     * Filter the REST API response.
     *
     * @param WP_REST_Response $response The REST API response object.
     * @param WP_REST_Server   $server   The REST server object.
     * @param WP_REST_Request  $request  The REST request object.
     * @return WP_REST_Response Modified REST API response object.
     */
    public static function filter_rest_response( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ) {

        if ( false !== strpos( $request->get_route(), self::namespace() ) ) {

            $response->header( 'X-App-Name', SMLISER_APP_NAME );
            $response->header( 'X-API-Version', 'v1' );

            $data = $response->get_data();

            if ( is_array( $data ) ) {
                $data = array( 'success' => ! $response->is_error() ) + $data;
                $response->set_data( $data );
            }
        }

        return $response;
    }

    /**
     * Preempt REST API request callbacks.
     * 
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
     *                                                                   Usually a WP_REST_Response or WP_Error.
     * @param array                                            $handler  Route handler used for the request.
     * @param WP_REST_Request                                  $request  Request used to generate the response.
     */
    public static function rest_request_before_callbacks( $response, $handler, $request ) {
        
        $route     = ltrim( $request->get_route(), '/' );
        $namespace = trim( self::namespace(), '/' );

        // Match if route starts with namespace
        if ( ! preg_match( '#^' . preg_quote( $namespace, '#' ) . '(/|$)#', $route ) ) {
            return $response;
        }

        if ( is_smliser_error( $response ) ) {
            remove_filter( 'rest_post_dispatch', 'rest_send_allow_header' ); // Prevents calling the permission callback again.
        }

        return $response;
    }
    
    /*
    |----------------
    |UTILITY METHODS
    |----------------
    */

    /**
     * Normalize the app_type:app-slug form input to associative array
     * 
     * @param array $app_ids
     */
    private static function normalize_app_ids_form_input( array $app_ids ) : array {
        $normalized = [];

        foreach ( $app_ids as $item ) {
            [ $type, $slug ] = explode( ':', $item, 2 );

            if ( empty( $type ) || empty( $slug ) ) {
                continue;
            }

            $normalized[] = array( 'app_type' => $type, 'app_slug' => $slug );
        }

        return $normalized;
    }

    /**
     * Initialize the WordPress environment.
     * 
     * @return self
     */
    public static function init() {
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
                return [__CLASS__, 'parse_public_package_download'];
            case 'document':
            case 'documents':
                return [__CLASS__, 'parse_license_document_download'];

            
        }

        return function () {
            smliser_abort_request( new Exception( 'unsupported_route', 'The rquested route is not supported', ['status' => 404 ] ) );
        };
    }

    /**
     * Resolves the bulk action handler
     * 
     * @param string $context
     * @return callable
     */
    private static function resolve_bulk_action_handler( $context ) {
        $context = (string) $context;

        switch( $context ) {
            case 'license':
                $handler = [Controller::class, 'license_bulk_action'];
                break;
            case 'repository':
                $handler    = [HostingController::class, 'app_bulk_action'];
                break;
            default:
            $handler = function() use( $context ) {
                smliser_abort_request( \sprintf( 'Bulk action cannot be handled for "%s"', $context ) );
            };
        }

        return $handler;
    }

    /**
     * Add WordPress-specific validation and sanitization to route arguments.
     * 
     * @param array $args Route arguments.
     * @return array Modified arguments with WordPress callbacks.
     */
    private function validate_rest_args( $args ) {
        foreach ( $args as $key => &$arg ) {
            // Add sanitization callbacks based on type
            if ( $arg['type'] === 'string' ) {
                if ( $key === 'domain' ) {
                    $arg['sanitize_callback'] = array( __CLASS__, 'sanitize_url' );
                    $arg['validate_callback'] = array( __CLASS__, 'is_url' );
                } else {
                    $arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
                    $arg['validate_callback'] = array( __CLASS__, 'not_empty' );
                }
            } elseif ( $arg['type'] === 'integer' ) {
                $arg['sanitize_callback'] = 'absint';
            } elseif ( $arg['type'] === 'array' ) {
                $arg['sanitize_callback'] = array( __CLASS__, 'sanitize' );
                if ( ! isset( $arg['validate_callback'] ) ) {
                    $arg['validate_callback'] = '__return_true';
                }
            }
        }

        return $args;
    }

    /**
     * Encapsulted sanitization function for REST param.
     * 
     * @param mixed $value The value to sanitize.
     */
    public static function sanitize( $value ) {
        if ( is_string( $value ) ) {
            $value = sanitize_text_field( unslash( $value ) );
        } elseif ( is_array( $value ) ) {
            $value = array_map( 'sanitize_text_field', unslash( $value ) );
        } elseif ( is_int( $value ) ) {
            $value = absint( $value );
        } elseif ( is_float( $value ) ) {
            $value = floatval( $value );
        }

        return $value;
    }

    /**
     * Sanitize a URL for REST API validation.
     * 
     * @param string $url The URL to sanitize.
     * @return string Sanitized URL.
     */
    public static function sanitize_url( $url ) {
        $url = esc_url_raw( $url );
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }
        return $url;
    }

    /**
     * Check whether a value is empty for REST API validation.
     * @param string $value The value to check.
     * @return bool true if not empty, false otherwise.
     */
    public static function not_empty( $value ) {
        if ( empty( $value ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value cannot be empty.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Validate if the given URL is an HTTP or HTTPS URL.
     *
     * @param string $url The URL to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $param The parameter name.
     * @return true|WP_Error
     */
    public static function is_url( $url, $request, $param ) {
        if ( empty( $url ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The domain parameter is required.', 'smliser' ), array( 'status' => 400 ) );
        }

        $url        = new URL( $url );

        if ( ! $url->has_scheme() ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid URL format.', 'smliser' ), array( 'status' => 400 ) );
        }

        if ( ! $url->is_ssl() ) {
            return new WP_Error( 'rest_invalid_param', __( 'Only HTTPS URLs are allowed.', 'smliser' ), array( 'status' => 400 ) );
        }

        if ( ! $url->validate( true ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The URL does not resolve to a valid host.', 'smliser' ), array( 'status' => 400 ) );
        }

        return true;
    }

    /**
     * Validate if a given REST API parameter is an integer.
     * 
     * @param mixed $value The value to validate.
     * @param WP_REST_Request $request The request object.
     * @param string $key The parameter name.
     * @return true|WP_Error Returns true if the value is an integer, otherwise returns a WP_Error object.
     */
    public static function is_int( $value, $request, $key ) {
        if ( ! is_numeric( $value ) || intval( $value ) != $value ) {
            return new WP_Error( 'rest_invalid_param', __( 'The value must be an integer.', 'smliser' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
    |---------------------------------------
    | Concrete implementation of contracts
    |---------------------------------------
    */

    /**
     * Auto register monetization providers
     */
    public static function auto_register_monetization_providers() {
        ProviderCollection::auto_load();
    }

}