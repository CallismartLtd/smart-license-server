<?php
/**
 * WordPress environment router file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Email\RequestController as EmailRequestController;
use SmartLicenseServer\Environments\RouterInterface;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequest;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequestController;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Environments\WordPress\Installer;
use SmartLicenseServer\Messaging\MessageController;
use SmartLicenseServer\Monetization\Controller;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\RequestController;
use SmartLicenseServer\SettingsAPI\SettingsController;

/**
 * Concrete implementation of routing in WordPress.
 */
class Router implements RouterInterface {

    /**
     * Handle incoming requests for this application.
     */
    public static function init_request(): void {
        $trigger = get_query_var( 'pagename' );

        if ( ! $trigger && isset( $_REQUEST['action'] ) ) {
            $trigger = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
        }

        if ( empty( $trigger ) || ! is_string( $trigger ) ) {
            return;
        }

        // Single Request instance — all parsers read from this.
        $request = new Request();

        $handler_map = [
            'smliser-downloads'                             => function( Request $r ) { ( self::resolve_download_request_parser() )( $r ); },
            'smliser-repository-assets'                     => [ __CLASS__, 'parse_app_asset_request' ],
            'smliser-uploads'                               => [ __CLASS__, 'parse_uploads_dir_request' ],
            'smliser_admin_download'                        => [ __CLASS__, 'parse_admin_download_request' ],
            'smliser_download_image'                        => [ __CLASS__, 'parse_proxy_image_request' ],
            'smliser_save_plugin'                           => [ __CLASS__, 'parse_save_app_request' ],
            'smliser_save_theme'                            => [ __CLASS__, 'parse_save_app_request' ],
            'smliser_save_software'                         => [ __CLASS__, 'parse_save_app_request' ],
            'smliser_save_license'                          => [ __CLASS__, 'parse_save_license_request' ],
            'smliser_remove_licensed_domain'                => [ __CLASS__, 'parse_licensed_domain_removal_request' ],
            'smliser_app_asset_upload'                      => [ __CLASS__, 'parse_app_asset_upload_request' ],
            'smliser_app_asset_delete'                      => [ __CLASS__, 'parse_app_asset_delete_request' ],
            'smliser_save_monetization_tier'                => [ __CLASS__, 'parse_monetization_tier_form_request' ],
            'smliser_bulk_action'                           => [ __CLASS__, 'parse_bulk_action_request' ],
            'smliser_all_actions'                           => [ __CLASS__, 'parse_bulk_action_request' ],
            'smliser_generate_download_token'               => [ __CLASS__, 'parse_download_token_generation_request' ],
            'smliser_app_status_action'                     => [ __CLASS__, 'parse_app_status_action_request' ],
            'smliser_save_monetization_provider_options'    => [ __CLASS__, 'parse_save_provider_options_request' ],
            'smliser_upgrade'                               => [ __CLASS__, 'parse_database_migration_request' ],
            'smliser_publish_bulk_message'                  => [ __CLASS__, 'parse_bulk_message_publish_request' ],
            'smliser_get_product_data'                      => [ __CLASS__, 'parse_monetization_provider_product_request' ],
            'smliser_delete_monetization_tier'              => [ __CLASS__, 'parse_monetization_tier_deletion_request' ],
            'smliser_toggle_monetization'                   => [ __CLASS__, 'parse_toggle_monetization_request' ],
            'smliser_access_control_save'                   => [ __CLASS__, 'parse_access_control_save_request' ],
            'smliser_access_control_delete'                 => [ __CLASS__, 'parse_access_control_delete_request' ],
            'smliser_delete_org_member'                     => [ __CLASS__, 'parse_smliser_delete_org_member_request' ],
            'smliser_admin_security_entity_search'          => [ __CLASS__, 'parse_admin_security_entity_search_request' ],
            'smliser_save_default_email_settings'           => [ __CLASS__, 'parse_default_email_settings_request' ],
            'smliser_send_test_email'                       => [ __CLASS__, 'parse_email_test_request' ],
            'smliser_save_email_provider_settings'          => [ __CLASS__, 'parse_save_email_provider_request' ],
            'smliser_save_system_options'                   => [ __CLASS__, 'parse_save_system_settings_request' ],
            'smliser_save_route_options'                    => [ __CLASS__, 'parse_save_routes_settings_request' ],
            'smliser_toggle_email_template'                 => [ __CLASS__, 'parse_save_email_template_toggle_request' ],
        ];

        if ( isset( $handler_map[ $trigger ] ) ) {
            $callback = $handler_map[ $trigger ];
            is_callable( $callback ) && $callback( $request );
        }
    }

    /*
    |----------------------------
    | Security guard helpers
    |----------------------------
    */

    /**
     * Verify WordPress nonce from the request.
     *
     * Checks the `security` param first (AJAX convention), then the
     * `_wpnonce` param (form convention), then the X-WP-Nonce header.
     *
     * @param  Request $request
     * @param  string  $action   Expected nonce action.
     * @return bool
     */
    private static function verify_nonce( Request $request, string $action = 'smliser_nonce' ): bool {
        $nonce = $request->get( 'security' )
            ?? $request->get( '_wpnonce' )
            ?? $request->get_header( 'X-WP-Nonce' );

        return (bool) wp_verify_nonce( (string) $nonce, $action );
    }

    /**
     * Verify the current user has the required capability.
     *
     * @param  string $capability
     * @return bool
     */
    private static function verify_capability( string $capability ): bool {
        if ( 'super_admin' === $capability ) {
            return is_super_admin();
        }

        return current_user_can( $capability );
    }

    /**
     * Guard a request with nonce + capability check.
     *
     * Sends a JSON 401 and stops execution if either check fails.
     *
     * @param  Request $request
     * @param  string  $capability   WordPress capability or 'super_admin'.
     * @param  string  $nonce_action Nonce action string.
     * @return void
     */
    private static function guard(
        Request $request,
        string $capability  = 'manage_options',
        string $nonce_action = 'smliser_nonce'
    ): void {
        if ( ! static::verify_nonce( $request, $nonce_action ) ) {
            smliser_send_json_error( [ 'message' => __( 'Security check failed.', 'smliser' ) ], 401 );
        }

        if ( ! static::verify_capability( $capability ) ) {
            smliser_send_json_error( [ 'message' => __( 'You do not have the required permission to perform this action.', 'smliser' ) ], 401 );
        }
    }

    /*
    |----------------------------
    | Concrete implementations
    |----------------------------
    */

    /**
     * Parse public package download request.
     */
    public static function parse_public_package_download_request( Request $request ): void {
        $app_type = get_query_var( 'smliser_app_type' );
        $app_slug = smliser_sanitize_path( get_query_var( 'smliser_app_slug' ) );

        if ( is_smliser_error( $app_slug ) ) {
            smliser_abort_request(
                __( 'Please provide the correct application slug', 'smliser' ),
                'Bad Request',
                [ 'response' => 400 ]
            );
        }

        $file_request = new FileRequest( [
            'app_type'       => $app_type,
            'app_slug'       => $app_slug,
            'download_token' => $request->get( 'download_token' ),
            'authorization'  => $request->get_header( 'Authorization' ),
            'user_agent'     => $request->get_header( 'User-Agent' ),
            'request_time'   => $request->startTime(),
            'client_ip'      => smliser_get_client_ip(),
        ] );

        $response = FileRequestController::get_application_zip_file( $file_request );

        if ( ! $response->is_valid_zip_file() && $response->ok() ) {
            $response->set_exception( new FileRequestException( 'file_corrupted' ) );
        }

        $response->send();
    }

    /**
     * Parse admin download request.
     */
    public static function parse_admin_download_request( Request $request ): void {
        if ( ! wp_verify_nonce( (string) $request->get( 'download_token' ), 'smliser_download_token' ) ) {
            smliser_abort_request(
                __( 'Expired download link, please refresh current page.', 'smliser' ),
                'Expired Link',
                [ 'response' => 400 ]
            );
        }

        if ( ! is_admin() || ! current_user_can( 'install_plugins' ) ) {
            smliser_abort_request(
                __( 'You are not authorized to perform this action.', 'smliser' ),
                'Unauthorized Download',
                [ 'response' => 403 ]
            );
        }

        $type = $request->get( 'type' );
        $id   = $request->get( 'id' );

        if ( empty( $type ) || empty( $id ) ) {
            smliser_abort_request( __( 'Invalid download request.', 'smliser' ), 'Invalid Request', [ 'response' => 400 ] );
        }

        $file_request = new FileRequest( [
            'app_type' => $type,
            'app_id'   => $id,
        ] );

        FileRequestController::get_admin_application_zip_file( $file_request )->send();
    }

    /**
     * Parse license document download request.
     */
    public static function parse_license_document_download_request( Request $request ): void {
        $file_request = new FileRequest( [
            'license_id'     => absint( get_query_var( 'license_id' ) ),
            'download_token' => $request->get( 'download_token' ),
            'user_agent'     => $request->get_header( 'User-Agent' ),
            'request_time'   => $request->startTime(),
            'client_ip'      => smliser_get_client_ip(),
        ] );

        FileRequestController::get_license_document( $file_request )->send();
    }

    /**
     * Parse application asset request.
     */
    public static function parse_app_asset_request( Request $request ): void {
        $file_request = new FileRequest( [
            'app_type'    => sanitize_text_field( get_query_var( 'smliser_app_type' ) ),
            'app_slug'    => sanitize_text_field( get_query_var( 'smliser_app_slug' ) ),
            'asset_name'  => sanitize_text_field( get_query_var( 'smliser_asset_name' ) ),
            'user_agent'  => $request->get_header( 'User-Agent' ),
            'request_time'=> $request->startTime(),
            'client_ip'   => smliser_get_client_ip(),
        ] );

        FileRequestController::get_app_static_asset( $file_request )->send();
    }

    /**
     * Parse uploads directory access request.
     */
    public static function parse_uploads_dir_request( Request $request ): void {
        $path = smliser_sanitize_path( get_query_var( 'smliser_upload_path' ) );

        if ( is_smliser_error( $path ) ) {
            smliser_abort_request(
                __( 'Please provide a valid file path', 'smliser' ),
                'Bad Request',
                [ 'response' => 400 ]
            );
        }

        $file_request = new FileRequest( [
            'file_path'    => $path,
            'user_agent'   => $request->get_header( 'User-Agent' ),
            'request_time' => $request->startTime(),
            'client_ip'    => smliser_get_client_ip(),
        ] );

        FileRequestController::get_uploads_dir_asset( $file_request )->send();
    }

    /**
     * Parse proxy image request.
     */
    public static function parse_proxy_image_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        $image_url = $request->get( 'image_url' )
            ?: smliser_abort_request( 'Image URL is required' );

        $file_request = new FileRequest( [
            'asset_url'    => $image_url,
            'asset_name'   => $request->get( 'asset_name', '' ),
            'user_agent'   => $request->get_header( 'User-Agent' ),
            'request_time' => $request->startTime(),
            'client_ip'    => smliser_get_client_ip(),
        ] );

        $response = FileRequestController::get_proxy_asset( $file_request );

        $response->register_after_serve_callback( function( $r ) {
            @unlink( $r->get_file() );
        } );

        $response->send();
    }

    /**
     * Parse save application request.
     */
    public static function parse_save_app_request( Request $request ): void {
        static::guard( $request, 'install_plugins' );

        HostingController::save_app( $request )->send();
    }

    /**
     * Parse application asset upload request.
     */
    public static function parse_app_asset_upload_request( Request $request ): void {
        static::guard( $request, 'install_plugins' );

        if ( $request->isPatch() || $request->isPut() ) {
            $request->get_file( 'asset_file' )?->set_new_name( (string) $request->get( 'asset_name' ) );
        }

        HostingController::app_asset_upload( $request )->send();
    }

    /**
     * Parse application asset delete request.
     */
    public static function parse_app_asset_delete_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        HostingController::app_asset_delete( $request )->send();
    }

    /**
     * Parse save license request.
     */
    public static function parse_save_license_request( Request $request ): void {
        static::guard( $request, 'install_plugins' );

        $app_prop = (string) $request->get( 'app_prop' );

        if ( str_contains( $app_prop, ':' ) ) {
            [ $app_type, $app_slug ] = explode( ':', $app_prop, 2 );
            $request->set( 'app_type', $app_type );
            $request->set( 'app_slug', $app_slug );
        }

        $response = Controller::save_license( $request );

        if ( $response->ok() ) {
            $redirect_url = $response->get_header( 'Location' );
            $response->remove_header( 'Location' );

            $body                 = (array) $response->get_body();
            $body['redirect_url'] = $redirect_url;
            $response->set_body( $body );
        }

        $response->send();
        exit;
    }

    /**
     * Parse bulk action request.
     */
    public static function parse_bulk_action_request( Request $request ): void {
        $table_nonce_verified = wp_verify_nonce( (string) $request->get( 'smliser_table_nonce' ), 'smliser_table_nonce' );
        $nonce_verified       = static::verify_nonce( $request );

        if ( ! $table_nonce_verified && ! $nonce_verified ) {
            wp_safe_redirect( wp_get_referer() );
            exit;
        }

        $context = $request->get( 'context' )
            ?? smliser_abort_request( 'Bulk action context is required', 'Context Required', [ 'status_code' => 400 ] );

        $handler = self::resolve_bulk_action_controller( $context );

        if ( 'repository' === $context ) {
            $request->set( 'ids', self::normalize_app_ids_form_input( (array) $request->get( 'ids', [] ) ) );
        }

        call_user_func( $handler, $request )->send();
        exit;
    }

    /**
     * Parse monetization tier form request.
     */
    public static function parse_monetization_tier_form_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        Controller::save_monetization( $request )->send();
        exit;
    }

    /**
     * Parse route settings request.
     */
    public static function parse_save_routes_settings_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        $response   = SettingsController::save_routing_settings( $request );

        if ( $response->ok() ) {
            \flush_rewrite_rules();
        }

        $response->send();
        exit;
    }

    /**
     * Parse save email template toggle request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_save_email_template_toggle_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        $response   = SettingsController::toggle_email_template( $request );

        if ( $response->ok() ) {
            \flush_rewrite_rules();
        }

        $response->send();
        exit;
    }

    /**
     * Load authentication template.
     *
     * @param  string $template
     * @return string
     */
    public static function load_auth_template( string $template ): string {
        global $wp_query;

        if ( isset( $wp_query->query_vars['smliser_auth'] ) ) {
            $template = SMLISER_PATH . 'templates/auth/auth-controller.php';
        }

        return $template;
    }

    /**
     * Parse download token generation request.
     */
    public static function parse_download_token_generation_request( Request $request ): void {
        static::guard( $request, 'install_plugins' );

        $license_id = $request->get( 'license_id' )
            ?? smliser_send_json_error( [ 'message' => 'License ID is required.' ] );

        $expiry  = $request->get( 'expiry', 10 * DAY_IN_SECONDS ) ?: 10 * DAY_IN_SECONDS;
        $license = License::get_by_id( $license_id );

        if ( ! $license ) {
            smliser_send_json_error( [ 'message' => 'This license does not exist.' ] );
        }

        if ( is_string( $expiry ) ) {
            $expiry = strtotime( $expiry ) - time();
            if ( $expiry < 0 ) {
                $expiry = 10 * DAY_IN_SECONDS;
            }
        }

        $token = smliser_generate_item_token( $license, $expiry );

        smliser_send_json_success( [
            'token'                 => $token,
            'licensee_fullname'     => $license->get_licensee_fullname(),
            'document_download_url' => smliser_document_download_url( $license->get_id() )
                ->add_query_param( 'download_token', $token )
                ->get_href(),
            'expiry'                => gmdate( smliser_datetime_format(), time() + $expiry ),
        ] );
    }

    /**
     * Parse application status action request.
     */
    public static function parse_app_status_action_request( Request $request ): void {
        static::guard( $request, 'install_plugins' );

        HostingController::change_app_status( $request )->send();
    }

    /**
     * Parse database migration request.
     */
    public static function parse_database_migration_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        $repo_version = smliser_settings_adapter()->get( 'smliser_repo_version', 0 );

        if ( version_compare( $repo_version, SMLISER_VER, '>' ) ) {
            smliser_send_json_error( [ 'message' => 'No upgrade needed' ] );
        }

        if ( Installer::install() ) {
            Installer::db_migrate( $repo_version );
        }

        smliser_settings_adapter()->set( 'smliser_repo_version', SMLISER_VER );

        smliser_send_json_success( [
            'message' => sprintf(
                'The repository has been migrated from version "%s" to version "%s".',
                $repo_version,
                SMLISER_VER
            ),
        ] );
    }

    /**
     * Parse save provider options request.
     */
    public static function parse_save_provider_options_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        Controller::save_provider_options( $request )->send();
    }

    /**
     * Parse toggle monetization request.
     */
    public static function parse_toggle_monetization_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        Controller::toggle_monetization( $request )->send();
    }

    /**
     * Parse monetization provider product request.
     */
    public static function parse_monetization_provider_product_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        Controller::get_provider_product( $request )->send();
    }

    /**
     * Parse monetization tier deletion request.
     */
    public static function parse_monetization_tier_deletion_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        Controller::delete_monetization_tier( $request )->send();
    }

    /**
     * Parse licensed domain removal request.
     */
    public static function parse_licensed_domain_removal_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        Controller::uninstall_domain_from_license( $request )->send();
    }

    /**
     * Parse bulk message publish request.
     */
    public static function parse_bulk_message_publish_request( Request $request ): void {
        static::guard( $request, 'manage_options' );

        // message_body requires HTML preservation — sanitize here before passing to core.
        $request->set( 'message_body', wp_kses_post( (string) $request->get( 'message_body', '' ) ) );

        $apps = [];
        foreach ( (array) $request->get( 'associated_apps', [] ) as $app_data ) {
            try {
                [ $type, $slug ] = explode( ':', $app_data );
                if ( ! empty( $type ) && ! empty( $slug ) ) {
                    $apps[ $type ][] = $slug;
                }
            } catch ( \Throwable $th ) {}
        }

        $request->set( 'associated_apps', $apps );

        $response = MessageController::save_bulk_message( $request );

        if ( $response->ok() && $response->is_json_response() ) {
            $body = json_decode( $response->get_body(), true );
            $body['data']['redirect_url'] = admin_url(
                'admin.php?page=smliser-bulk-message&tab=edit&msg_id=' . ( $body['data']['message_id'] ?? '' )
            );
            $response->set_body( smliser_safe_json_encode( $body ) );
        }

        $response->send();
    }

    /**
     * Parse access control save request.
     */
    public static function parse_access_control_save_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        $entity = $request->get( 'entity' );

        if ( ! $entity ) {
            smliser_send_json_error( [ 'message' => 'Please provide security entity.' ], 400 );
        }

        $method   = 'organization_member' === $entity ? 'save_organization_member' : 'save_entity';
        $response = RequestController::$method( $request );

        $response->send();
    }

    /**
     * Parse access control delete request.
     */
    public static function parse_access_control_delete_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        if ( ! $request->hasValue( 'entity_type' ) ) {
            smliser_send_json_error( [ 'message' => 'Please provide security entity type.' ], 400 );
        }

        // Core controller expects 'entity' key.
        $request->set( 'entity', $request->get( 'entity_type' ) );

        RequestController::delete_entity( $request )->send();
    }

    /**
     * Parse admin security entity search request.
     */
    public static function parse_admin_security_entity_search_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        $entity = $request->get( 'entity_type', 'user' );

        $request->set( 'search_term', $request->get( 'search' ) );
        $request->set( 'status',      $request->get( 'status', 'active' ) );
        $request->set( 'types',       $request->get( 'types', Owner::get_allowed_owner_types() ) );

        $response = 'owner_subjects' === $entity
            ? RequestController::search_users_orgs( $request )
            : RequestController::search_resource_owners( $request );

        $response->send();
    }

    /**
     * Parse request to delete an organization member.
     */
    public static function parse_smliser_delete_org_member_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        RequestController::delete_org_member( $request )->send();
    }

    /**
     * Parse request to save default email settings.
     */
    public static function parse_default_email_settings_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        EmailRequestController::save_default_email_options( $request )->send();
        exit;
    }

    /**
     * Parse email test request.
     */
    public static function parse_email_test_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        EmailRequestController::send_test_email( $request )->send();
        exit;
    }

    /**
     * Parse save email provider settings request.
     */
    public static function parse_save_email_provider_request( Request $request ): void {
        static::guard( $request, 'super_admin' );

        EmailRequestController::save_provider_settings( $request )->send();
        exit;
    }

    /**
     * Parse save system settings request
     * 
     * @param Request $request
     */
    public static function parse_save_system_settings_request( Request $request ) : void {
        static::guard( $request );
        
        $response   = SettingsController::save_system_settings( $request );

        $response->send();
        exit;
    }

    /*
    |----------------
    | UTILITY METHODS
    |----------------
    */

    /**
     * Normalize app_type:app-slug form input to associative array.
     *
     * @param  array $app_ids
     * @return array
     */
    public static function normalize_app_ids_form_input( array $app_ids ): array {
        $normalized = [];

        foreach ( $app_ids as $item ) {
            [ $type, $slug ] = explode( ':', $item, 2 );

            if ( ! empty( $type ) && ! empty( $slug ) ) {
                $normalized[] = [ 'app_type' => $type, 'app_slug' => $slug ];
            }
        }

        return $normalized;
    }

    /**
     * Resolve the correct download request parser based on app type.
     *
     * @return callable
     */
    public static function resolve_download_request_parser(): callable {
        switch ( get_query_var( 'smliser_app_type' ) ) {
            case 'plugin':
            case 'plugins':
            case 'theme':
            case 'themes':
            case 'software':
                return [ __CLASS__, 'parse_public_package_download_request' ];
            case 'document':
            case 'documents':
                return [ __CLASS__, 'parse_license_document_download_request' ];
        }

        return function( Request $request ) {
            smliser_abort_request( new Exception( 'unsupported_route', 'The requested route is not supported', [ 'status' => 404 ] ) );
        };
    }

    /**
     * Resolve the bulk action controller for the given context.
     *
     * @param  string $context
     * @return callable
     */
    public static function resolve_bulk_action_controller( $context ): callable {
        switch ( (string) $context ) {
            case 'license':
                return [ Controller::class, 'license_bulk_action' ];
            case 'repository':
                return [ HostingController::class, 'app_bulk_action' ];
            case 'bulk-message':
                return [ MessageController::class, 'bulk_message_action' ];
            default:
                return function() use ( $context ) {
                    smliser_abort_request( sprintf( 'Bulk action cannot be handled for "%s"', $context ) );
                };
        }
    }
}