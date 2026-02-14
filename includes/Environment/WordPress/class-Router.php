<?php
/**
 * WordPress environment router file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environment\WordPress;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environment\RouterInterface;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequest;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequestController;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Installer;
use SmartLicenseServer\Messaging\MessageController;
use SmartLicenseServer\Monetization\Controller;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\RequestController;

/**
 * Concrete implementation of routing in WordPress.
 */
class Router implements RouterInterface {

    /**
     *  Handle incoming requests for this application.
     */
    public static function init_request() : void {
        $trigger   = get_query_var( 'pagename' );

		if ( ! $trigger && isset( $_REQUEST['action'] ) ) {
			$trigger = sanitize_text_field( unslash( $_REQUEST['action'] ) );
		}

        if ( empty( $trigger ) || ! is_string( $trigger ) ) {
            return;
        }

        $handler_map    = [
            'smliser-downloads'                             => function() { ( self::resolve_download_request_parser() )(); },
            'smliser-repository-assets'                     => [__CLASS__, 'parse_app_asset_request'],
            'smliser-uploads'                               => [__CLASS__, 'parse_uploads_dir_request'],
            'smliser_admin_download'                        => [__CLASS__, 'parse_admin_download_request'],
            'smliser_download_image'                        => [__CLASS__, 'parse_proxy_image_request'],
            'smliser_save_plugin'                           => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_theme'                            => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_software'                         => [__CLASS__, 'parse_save_app_request'],
            'smliser_save_license'                          => [__CLASS__, 'parse_save_license_request'],
            'smliser_remove_licensed_domain'                => [__CLASS__, 'parse_licensed_domain_removal_request'],
            'smliser_app_asset_upload'                      => [__CLASS__, 'parse_app_asset_upload_request'],
            'smliser_app_asset_delete'                      => [__CLASS__, 'parse_app_asset_delete_request'],
            'smliser_save_monetization_tier'                => [__CLASS__, 'parse_monetization_tier_form'],
            'smliser_bulk_action'                           => [__CLASS__, 'parse_bulk_action_request'],
            'smliser_all_actions'                           => [__CLASS__, 'parse_bulk_action_request'],
            'smliser_generate_download_token'               => [__CLASS__, 'parse_download_token_generation_request'],
            'smliser_app_status_action'                     => [__CLASS__, 'parse_app_status_action_request'],
            'smliser_save_monetization_provider_options'    => [__CLASS__, 'parse_save_provider_options_request'],
            'smliser_upgrade'                               => [__CLASS__, 'parse_database_migration_request'],
            'smliser_publish_bulk_message'                  => [__CLASS__, 'parse_bulk_message_publish_request'],
            'smliser_get_product_data'                      => [__CLASS__, 'parse_monetization_provider_product_request'],
            'smliser_delete_monetization_tier'              => [__CLASS__, 'parse_monetization_tier_deletion_request'],
            'smliser_toggle_monetization'                   => [__CLASS__, 'parse_toggle_monetization_request'],
            'smliser_access_control_save'                   => [__CLASS__, 'parse_access_control_save_request'],
            'smliser_access_control_delete'                 => [__CLASS__, 'parse_access_control_delete_request'],
            'smliser_delete_org_member'                     => [__CLASS__, 'parse_smliser_delete_org_member_request'],
            'smliser_admin_security_entity_search'          => [__CLASS__, 'parse_admin_security_entity_search_request']
        ];

        if ( isset( $handler_map[$trigger] ) ) {
            $callback   = $handler_map[$trigger];
            is_callable( $callback ) && $callback();

        }        
    }

    /*
    |----------------------------
    | Concrete implementations
    |----------------------------
    */
    /**
     * Parses request meant for an application zip file download.
     * 
     * @return void
     */
    public static function parse_public_package_download_request() : void {
        $app_type = get_query_var( 'smliser_app_type' );
        $app_slug = smliser_sanitize_path( get_query_var( 'smliser_app_slug' ) );

        if ( is_smliser_error( $app_slug ) ) {
            smliser_abort_request(
                __( 'Please provide the correct application slug', 'smliser' ),
                'Bad Request',
                array( 'response' => 400 )
            );
        }

        // Construct the FileRequest object.
        $request = new FileRequest([
            'app_type'          => $app_type,
            'app_slug'          => $app_slug,
            'download_token'    => smliser_get_query_param( 'download_token' ),
            'authorization'     => smliser_get_authorization_header(),
            'user_agent'        => smliser_get_user_agent(),
            'request_time'      => time(),
            'client_ip'         => smliser_get_client_ip(),
            'is_authorized'     => true // For public download, monetized app download permission checked by controller.
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
    public static function parse_admin_download_request() : void {
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
            'app_type'      => $type,
            'app_id'        => $id,
            'is_authorized' => current_user_can( 'install_plugins' )
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
    public static function parse_license_document_download_request() : void {
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
    public static function parse_app_asset_request() : void {
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
            'is_authorized' => true
        ]);

        $response = FileRequestController::get_app_static_asset( $request );

        $response->send();
    }

    /**
     * Parse requests for files in the smliser-uploads directory.
     */
    public static function parse_uploads_dir_request() : void {
        $path   = smliser_sanitize_path( get_query_var( 'smliser_upload_path' ) );
        if ( is_smliser_error( $path ) ) {
            smliser_abort_request(
                __( 'Please provide a valid file path', 'smliser' ),
                'Bad Request',
                array( 'response' => 400 )
            );
        }
        
        $request    = new FileRequest([
            'file_path'     => $path,
            'user_agent'    => smliser_get_user_agent(),
            'request_time'  => time(),
            'client_ip'     => smliser_get_client_ip(),
            'is_authorized' => true // Public access to uploads dir files
        ]);

        $response = FileRequestController::get_uploads_dir_asset( $request );

        $response->send();
    }

    /**
     * Proxy image download
     */
    public static function parse_proxy_image_request() : void {
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
            'is_authorized' => current_user_can( 'manage_options' )
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
    public static function parse_save_app_request() : void {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        // Construct the FileRequest object.
        $request = new Request([
            'is_authorized'                 => current_user_can( 'manage_options' ),
            'app_type'                      => smliser_get_post_param( 'app_type', null ),
            'app_id'                        => smliser_get_post_param( 'app_id', 0 ),
            'app_name'                      => smliser_get_post_param( 'app_name', null ),
            'app_author'                    => smliser_get_post_param( 'app_author', null ),
            'app_author_url'                => smliser_get_post_param( 'app_author_url', null ),
            'app_version'                   => smliser_get_post_param( 'app_version', null ),
            'app_required_php_version'      => smliser_get_post_param( 'app_required_php_version', null ),
            'app_required_wp_version'       => smliser_get_post_param( 'app_required_wp_version', null ),
            'app_tested_wp_version'         => smliser_get_post_param( 'app_tested_wp_version', null ),
            'app_download_url'              => smliser_get_post_param( 'app_download_url', null ),
            'app_support_url'               => smliser_get_post_param( 'app_support_url', null ),
            'app_homepage_url'              => smliser_get_post_param( 'app_homepage_url', null ),
            'app_preview_url'               => smliser_get_post_param( 'app_preview_url', null ),
            'app_documentation_url'         => smliser_get_post_param( 'app_documentation_url', null ),
            'app_external_repository_url'   => smliser_get_post_param( 'app_external_repository_url', null ),
            'app_zip_file'                  => isset( $_FILES['app_zip_file'] ) && UPLOAD_ERR_OK === $_FILES['app_zip_file']['error'] ? $_FILES['app_zip_file'] : null,
            'app_json_file'                 => $_FILES['app_json_file'] ?? null,
            'user_agent'                    => smliser_get_user_agent(),
            'request_time'                  => time(),
            'client_ip'                     => smliser_get_client_ip(),
        ]);

        $response   = HostingController::save_app( $request );
        
        $response->send();
    }

    /**
     * Parses the WP request for application asset upload, builds the Request object,
     * and calls the core controller.
     * * @throws RequestException On security or basic validation failure.
     */
    public static function parse_app_asset_upload_request() : void {
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
    public static function parse_app_asset_delete_request() : void {
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
    public static function parse_save_license_request() : void {
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
    public static function parse_bulk_action_request() : void {
        $table_nonce_verified   = wp_verify_nonce( smliser_get_post_param( 'smliser_table_nonce' ), 'smliser_table_nonce' );
        $smliser_nonce_verified = wp_verify_nonce( smliser_get_request_param( 'smliser_nonce', '' ), 'smliser_nonce' );
        if ( ! $table_nonce_verified && ! $smliser_nonce_verified ) {
            \wp_safe_redirect( \wp_get_referer() );
            exit;
        }

        $context    = smliser_get_request_param( 'context', null ) ?? \smliser_abort_request( 'Bulk action context is required', 'Context Required', array( 'status_code' => 400 ) );
        $handler    = self::resolve_bulk_action_controller( $context );

        $request    = new Request([
            'ids'           => smliser_get_request_param( 'ids', [] ),
            'bulk_action'   => smliser_get_request_param( 'bulk_action', '' ),
            'is_authorized' => current_user_can( 'manage_options' ),
            'user_agent'    => smliser_get_user_agent(),
            'request_time'  => time(),
            'client_ip'     => smliser_get_client_ip(),
        ]);

        if ( 'repository' === $context ) {
            $ids    = $request->get( 'ids', [] );
            $request->set( 'ids', self::normalize_app_ids_form_input( (array) $ids ) );
        }

        /** @var Response $response */
        $response   = \call_user_func( $handler, $request );

        if ( $response->ok() ) {
            $target = $request->get( 'redirect_url' ) ?? \wp_get_referer();
            $url    = new URL( $target );
            $url->add_query_param( 'message', $response->get_response_data()->get( 'message' ) );
            wp_safe_redirect( $url->get_href() );
            exit;
        }

        $target = wp_get_referer();
        $url    = new URL( $target );
        $error_message   = $response->has_errors() ? $response->get_exception()->get_error_message() : 'Bulk action failed';
        $url->add_query_param( 'message', $error_message );
        wp_safe_redirect( $url->get_href() );
        exit;
    }

    /**
     * Parses the WP request for saving a monetization tier, builds the Request object,
     * and calls the refactored core controller.
     */
    public static function parse_monetization_tier_form_request() : void {
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
    public static function parse_options_form_request() : void {
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
     * 
     * @param string $template
     */
    public static function load_auth_template( string $template ) : string {
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
    public static function parse_download_token_generation_request() : void {
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
    public static function parse_app_status_action_request() : void {
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
    public static function parse_database_migration_request() : void {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            \smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! \current_user_can( 'manage_options' ) ) {
            \smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $repo_version = \smliser_settings_adapter()->get( 'smliser_repo_version', 0 );
        if ( version_compare( $repo_version, SMLISER_VER, '>' ) ) {
            \smliser_send_json_error( array( 'message' => 'No upgrade needed' ) );
        }

        if ( Installer::install() )  {
            Installer::db_migrate( $repo_version );   
        }

        \smliser_settings_adapter()->set( 'smliser_repo_version', SMLISER_VER );

        \smliser_send_json_success( array( 'message' => 'The repository has been migrated from version "' . $repo_version . '" to version "' . SMLISER_VER ) );
    }

    /**
     * Parse the monetization provider settings form submision.
     */
    public static function parse_save_provider_options_request() : void {
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
    public static function parse_toggle_monetization_request() : void {
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
    public static function parse_monetization_provider_product_request() : void {
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
    public static function parse_monetization_tier_deletion_request() : void {
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
     * Parse licensed domain removal request.
     */
    public static function parse_licensed_domain_removal_request() : void {
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
     * Parse bulk message publish request.
     */
    public static function parse_bulk_message_publish_request() : void {
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
            'subject'       => \smliser_get_post_param( 'subject' ),
            'message_body'  => \wp_kses_post( $_POST['message_body'] ?? '' ),
            'message_id'    => \smliser_get_post_param( 'message_id', 0 ),
            'is_authorized' => true

        ]);

        $assocs_apps    = smliser_get_post_param( 'associated_apps', [] );

        $apps       = [];
        foreach( $assocs_apps as $app_data ) {

            try {
                list( $type, $slug ) = explode( ':', $app_data );

                if ( ! empty( $type ) && ! empty( $slug ) ) {
                    $apps[$type][]  = $slug;
                }
            } catch ( \Throwable $th ) {}

        }

        $request->set( 'associated_apps', $apps );

        $response   = MessageController::save_bulk_message( $request );

        if ( $response->ok() && $response->is_json_response() ) {
            $body = \json_decode( $response->get_body(), true );

            $body['data']['redirect_url'] =  admin_url( 'admin.php?page=smliser-bulk-message&tab=edit&msg_id=' . $body['data']['message_id'] ?? '' );

            $response->set_body( \smliser_safe_json_encode( $body ) );
        }

        $response->send();
    }

    /**
     * Parse security and access control save request for users, organizations, service accounts,
     * resource owners.
     */
    public static function parse_access_control_save_request() : void {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
            ), 401 );
        }

        if ( ! is_super_admin() ) {
            smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $entity    = smliser_get_request_param( 'entity', false );
        if ( ! $entity  ) {
            smliser_send_json_error( array( 'message' => 'Please provide security entity.' ), 400 );
        }

        $request    = new Request([
            'id'                => smliser_get_request_param( 'id' ),
            'name'              => smliser_get_request_param( 'name' ),
            'display_name'      => smliser_get_request_param( 'display_name' ),
            'description'       => smliser_get_request_param( 'description' ),
            'email'             => smliser_get_request_param( 'email' ),
            'password_1'        => $_REQUEST[ 'password_1' ] ?? '', // phpcs:ignore
            'password_2'        => $_REQUEST[ 'password_2' ] ?? '', // phpcs:ignore
            'status'            => smliser_get_request_param( 'status' ),
            'subject_id'        => smliser_get_request_param( 'subject_id' ),
            'owner_id'          => smliser_get_request_param( 'owner_id' ),
            'owner_type'        => smliser_get_request_param( 'owner_type' ),
            'role_label'        => smliser_get_request_param( 'role_label' ),
            'role_slug'         => smliser_get_request_param( 'role_slug' ),
            'org_slug'          => smliser_get_request_param( 'org_slug' ),
            'organization_id'   => smliser_get_request_param( 'organization_id' ),
            'role_id'           => smliser_get_request_param( 'role_id' ),
            'member_id'         => smliser_get_request_param( 'member_id' ),
            'user_id'           => smliser_get_request_param( 'user_id' ),
            'capabilities'      => smliser_get_request_param( 'capabilities', [] ),
            'entity'            => $entity,
            'avatar'            => isset( $_FILES['avatar'] ) && UPLOAD_ERR_OK === $_FILES['avatar']['error'] ? $_FILES['avatar'] : null,
            'is_authorized'     => true,
        ]);

        $method = 'organization_member' === $request->get( 'entity' ) ? 'save_organization_member' : 'save_entity';

        /** @var Response $response */
        $response   = RequestController::$method( $request );

        $response->send();
    }

    /**
     * Parse access control request to delete a security entity.
     */
    public static function parse_access_control_delete_request() : void {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
            ), 401 );
        }

        if ( ! is_super_admin() ) {
            smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $entity    = smliser_get_request_param( 'entity_type', false );
        if ( ! $entity  ) {
            smliser_send_json_error( array( 'message' => 'Please provide security entity type.' ), 400 );
        }

        $request = new Request([
            'entity'    => $entity,
            'id'        => smliser_get_request_param( 'id' )
        ]);

        $response   = RequestController::delete_entity( $request );

        $response->send();

    }

    /**
     * Parse admin security entity search request.
     * 
     * This request searches for users and organizations.
     */
    public static function parse_admin_security_entity_search_request() : void {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
            ), 401 );
        }

        if ( ! is_super_admin() ) {
            smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $entity    = smliser_get_request_param( 'entity_type', 'user' );

        $request    = new Request([
            'search_term'   => smliser_get_request_param( 'search' ),
            'status'        => smliser_get_request_param( 'status', 'active' ),
            'types'         => smliser_get_request_param( 'types', Owner::get_allowed_owner_types() ),
            'is_authorized' => is_super_admin()
        ]);

        if ( 'owner_subjects' === $entity ) {
            $response   = RequestController::search_users_orgs( $request );
        } else {
            $response   = RequestController::search_resource_owners( $request );
        }

        $response->send();
    }
    
    /**
     * Parse request to delete a member of an organization
     */
    public static function parse_smliser_delete_org_member_request() : void {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
            ), 401 );
        }

        if ( ! is_super_admin() ) {
            smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this action!' ), 401 );
        }

        $request    = new Request([
            'organization_id'   => smliser_get_query_param( 'organization_id' ),
            'member_id'         => smliser_get_query_param( 'member_id' )
        ]);

        $response   = RequestController::delete_org_member( $request );

        $response->send();

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
    public static function normalize_app_ids_form_input( array $app_ids ) : array {
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
     * Get the download request parser.
     *  
     * @return callable
     */
    public static function resolve_download_request_parser() {
        switch ( get_query_var( 'smliser_app_type' ) ) {
            case 'plugin':
            case 'plugins':
            case 'theme':
            case 'themes':
            case 'software':
                return [__CLASS__, 'parse_public_package_download_request'];
            case 'document':
            case 'documents':
                return [__CLASS__, 'parse_license_document_download_request'];

            
        }

        return function () {
            smliser_abort_request( new Exception( 'unsupported_route', 'The rquested route is not supported', ['status' => 404 ] ) );
        };
    }

    /**
     * Resolves the bulk action request controller.
     * 
     * @param string $context
     * @return callable
     */
    public static function resolve_bulk_action_controller( $context ) {
        $context = (string) $context;

        switch( $context ) {
            case 'license':
                $handler = [Controller::class, 'license_bulk_action'];
                break;
            case 'repository':
                $handler    = [HostingController::class, 'app_bulk_action'];
                break;
            case 'bulk-message':
                $handler    = [MessageController::class, 'bulk_message_action'];
                break;
            default:
            $handler = function() use( $context ) {
                smliser_abort_request( sprintf( 'Bulk action cannot be handled for "%s"', $context ) );
            };
        }

        return $handler;
    }
}
