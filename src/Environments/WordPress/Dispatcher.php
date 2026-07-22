<?php
/**
 * Request dispatcher for WordPress.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Cache\CacheRequestController;
use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Email\RequestController as EmailRequestController;
use SmartLicenseServer\Environments\RequestHandlingContract;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequest;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequestController;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Environments\WordPress\Installer;
use SmartLicenseServer\FileSystem\DownloadsApi\FileResponse;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\Messaging\MessageController;
use SmartLicenseServer\Monetization\Controller;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\RequestController;
use SmartLicenseServer\SettingsAPI\SettingsController;

/**
 * WordPress request dispatcher class
 */
class Dispatcher implements RequestHandlingContract {
    /**
     * Map of registered request triggers to callback.
     * 
     * @var array<string, callable(Request):Response> $registered_handlers
     */
    public static array $registered_handlers = [
        'smliser-dashboard'                             => [ __CLASS__, 'render_client_dashboard' ],
        'smliser-downloads'                             => [ __CLASS__, 'handle_public_downloads' ],
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
        'smliser_app_artifact_upload'                   => [ __CLASS__, 'parse_app_artifact_upload_request' ],
        'smliser_delete_artifact'                       => [ __CLASS__, 'parse_app_artifact_delete_request' ],
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
        'smliser_preview_email_template'                => [ __CLASS__, 'parse_preview_email_template_request' ],
        'smliser_save_email_template'                   => [ __CLASS__, 'parse_save_email_template_request' ],
        'smliser_reset_email_template'                  => [ __CLASS__, 'parse_reset_email_template_request' ],
        'smliser_delete_license'                        => [ __CLASS__, 'parse_license_delete_request' ],
        
        'smliser_save_cache_adapter_settings'           => [ __CLASS__, 'parse_save_cache_adapter_settings_request' ],
        'smliser_test_cache_adapter_settings'           => [ __CLASS__, 'parse_test_cache_adapter_settings_request' ],
        'smliser_cache_get_stats'                       => [ __CLASS__, 'parse_get_cache_stats_request' ],
        'smliser_cache_clear_all'                       => [ __CLASS__, 'parse_clear_all_cache_request' ],
        'smliser_cache_delete_by_prefix'                => [ __CLASS__, 'parse_delete_cache_by_prefix_request' ],
        'smliser_cache_flush_expired'                   => [ __CLASS__, 'parse_flush_expired_cache_request' ],
        'smliser_cache_get_top_keys'                    => [ __CLASS__, 'parse_get_top_cache_keys_request' ],
    ];

    /**
     * Handle incoming requests for this application.
     * 
     * If a request matches any of this application action or registered rewrite rules,
     * we pass such request to Smart License Server, prempt futher WordPress execution and
     * terminate the request.
     */
    public static function init_request() : void {
        // Single Request instance — all parsers read from this.
        $request    = \smliser_request();
        $trigger    = get_query_var( 'pagename' );

        if ( $trigger ) {
            $request->set_params(
                $GLOBALS['wp']->query_vars
            );
        }

        if ( ! $trigger && $request->hasValue( 'action' ) ) {
            $trigger = $request->get( 'action' );
        }

        if ( empty( $trigger ) || ! is_string( $trigger ) ) {
            return;
        }

        $callback = static::$registered_handlers[ $trigger ] ?? null;

        if ( $callback ) {
            
            $callback( $request )->send();
            exit;
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
    protected static function verify_nonce( Request $request, string $action = 'smliser_nonce' ): bool {
        $nonce = $request->get( 'security', null, false )
            ?? $request->get( 'smliser_nonce', null, false )
            ?? $request->get( '_wpnonce', null, false )
            ?? $request->get_header( 'X-WP-Nonce' );

        return (bool) wp_verify_nonce( (string) $nonce, $action );
    }

    /**
     * Verify the current user has the required capability.
     *
     * @param  string $capability
     * @return bool
     */
    protected static function verify_capability( string $capability ): bool {
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
    protected static function guard(
        Request $request,
        string $capability  = 'manage_options',
        string $nonce_action = 'smliser_nonce'
    ) : void {
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

    public static function parse_public_package_download_request( Request $request ) : Response {
        $app_type           = $request->get( 'download_type' );
        $app_slug_filename  = smliser_sanitize_path( $request->get( 'app_slug_filename', '', false ) );

        if ( $app_slug_filename instanceof Exception ) {
            smliser_abort_request(
                $app_slug_filename->get_error_message(),
                'Bad Request',
                [ 'response' => 400 ]
            );
        }

        $ext    = FileSystemHelper::get_extension( $app_slug_filename );

        if ( '' !== $ext && 'zip' !== $ext ) {
            smliser_abort_request(
                sprintf(
                    '%s file extension must be ".zip".',
                    $app_type
                ),
                'Bad Request',
                [ 'response' => 400 ]
            );
        }

        $app_slug   = FileSystemHelper::remove_extension( $app_slug_filename );

        $file_request = new FileRequest( [
            'app_type'       => $app_type,
            'app_slug'       => $app_slug,
            'download_token' => $request->get( 'download_token' ),
            'authorization'  => $request->get_header( 'Authorization' ),
        ], $request->get_headers(), $request->method(), $request->uri() );

        $response = FileRequestController::get_application_zip_file( $file_request );

        if ( $response->ok() && ! $response->is_valid_zip_file() ) {
            $response->set_exception( new FileRequestException( 'file_corrupted' ) );
        }

        return $response;
    }

    public static function parse_public_artifact_download_request( Request $request ) : Response {

        $file_request   = new FileRequest( 
            $request->get_params(),
            $request->get_headers(),
            $request->method(),
            $request->uri()
        );

        $file_request->set( 'artifact_filename', $request->get( 'filename' ) );

        $file_request->remove( 'filename' );

        $response   = FileRequestController::get_application_artifact_file( $file_request );

        return $response;
    }

    public static function parse_admin_download_request( Request $request ) : Response {
        $download_token = (string) $request->get( 'download_token', '', false );

        if ( ! wp_verify_nonce( $download_token, 'smliser_download_token' ) ) {
            smliser_abort_request(
                __( 'Expired download link, please refresh current page.', 'smliser' ),
                'Expired Link',
                [ 'response' => 400 ]
            );
        }

        if ( ! current_user_can( 'install_plugins' ) ) {
            smliser_abort_request(
                __( 'You are not authorized to perform this action.', 'smliser' ),
                'Unauthorized Download',
                [ 'response' => 403 ]
            );
        }

        $type = $request->get( 'type' );
        $id   = $request->get( 'id' );

        if ( empty( $type ) ) {
            smliser_abort_request( 
                __( 'Please specify the download type.', 'smliser' ),
                'Download Type Missing', 
                [ 'response' => 400 ]
            );
        }

        if ( 'license_document' === $type ) {
            $method = 'get_admin_license_document';
        } else {
            $method = 'get_admin_application_zip_file';
        }

        $file_request = new FileRequest( [
            'app_type'      => $type,
            'app_id'        => $id,
            'license_id'    => $id,
        ], $request->get_headers(), $request->method(), $request->uri() );

        return FileRequestController::$method( $file_request );
    }

    public static function parse_license_document_download_request( Request $request ) : Response {
        $file_request = new FileRequest( [
            'license_id'     => get_query_var( 'license_id', 0 ),
            'download_token' => $request->get( 'download_token' ),
        ], $request->get_headers(), $request->method(), $request->uri() );

        return FileRequestController::get_license_document( $file_request );
    }

    public static function parse_app_asset_request( Request $request ) : Response {
        $file_request = new FileRequest( [
            'app_type'    => $request->get( 'app_type' ),
            'app_slug'    => $request->get( 'app_slug' ),
            'asset_name'  => $request->get( 'asset_name' ),
        ], $request->get_headers(), $request->method(), $request->uri() );

        return FileRequestController::get_app_static_asset( $file_request );
    }

    public static function parse_uploads_dir_request( Request $request ) : Response {
        $path = smliser_sanitize_path( get_query_var( 'smliser_upload_path' ) );

        if ( $path instanceof Exception ) {
            smliser_abort_request(
                __( 'Please provide a valid file path', 'smliser' ),
                'Bad Request',
                [ 'response' => 400 ]
            );
        }

        $file_request = new FileRequest( [
            'file_path'    => $path,
        ], $request->get_headers(), $request->method(), $request->uri() );

        return FileRequestController::get_uploads_dir_asset( $file_request );
    }

    public static function parse_proxy_image_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        $image_url = $request->get( 'image_url' )
            ?: smliser_abort_request( 'Image URL is required' );

        $file_request = new FileRequest( [
            'asset_url'    => $image_url,
            'asset_name'   => $request->get( 'asset_name', '' ),
        ], $request->get_headers(), $request->method(), $request->uri() );

        $response = FileRequestController::get_proxy_asset( $file_request );

        $response->register_after_serve_callback( function( $r ) {
            @unlink( $r->get_file() );
        } );

        return $response;
    }

    public static function parse_save_app_request( Request $request ) : Response {
        static::guard( $request, 'install_plugins' );

        return HostingController::save_app( $request );
    }

    public static function parse_app_asset_upload_request( Request $request ) : Response {
        static::guard( $request, 'install_plugins' );

        if ( $request->isPatch() || $request->isPut() ) {
            $request->get_file( 'asset_file' )?->set_new_name( (string) $request->get( 'asset_name' ) );
        }

        return HostingController::app_asset_upload( $request );
    }

    public static function parse_app_asset_delete_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return HostingController::app_asset_delete( $request );
    }

    public static function parse_app_artifact_upload_request( Request $request ) : Response {
        static::guard( $request, 'install_plugins' );

        return HostingController::app_artifact_upload( $request );
    }

    public static function parse_app_artifact_delete_request( Request $request ) : Response {
        static::guard( $request, 'install_plugins' );

        return HostingController::app_artifact_delete( $request );
    }

    public static function parse_save_license_request( Request $request ) : Response {
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

        return $response;
    }

    public static function parse_bulk_action_request( Request $request ) : Response {
        $table_nonce_verified = wp_verify_nonce( (string) $request->get( 'smliser_table_nonce', '', false ), 'smliser_table_nonce' );
        $nonce_verified       = static::verify_nonce( $request );

        if ( ! $table_nonce_verified && ! $nonce_verified ) {
            wp_safe_redirect( wp_get_referer() );

        }

        $context = $request->get( 'context' )
            ?? smliser_abort_request( 'Bulk action context is required', 'Context Required', [ 'status_code' => 400 ] );

        $handler = self::resolve_bulk_action_controller( $context );

        if ( 'repository' === $context ) {
            $app_ids = self::normalize_app_ids_form_input( (array) $request->get( 'ids', [] ) );

            $request->set( 'ids', $app_ids  );
        }

        return call_user_func( $handler, $request );
    }

    public static function parse_monetization_tier_form_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return Controller::save_monetization( $request );
    }

    public static function parse_save_routes_settings_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        $response   = SettingsController::save_routing_settings( $request );

        if ( $response->ok() ) {
            \flush_rewrite_rules();
        }

        return $response;
    }

    public static function parse_save_email_template_toggle_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        $response   = SettingsController::toggle_email_template( $request );

        if ( $response->ok() ) {
            \flush_rewrite_rules();
        }

        return $response;
    }

    public static function parse_download_token_generation_request( Request $request ) : Response {
        static::guard( $request, 'install_plugins' );

        return Controller::generate_app_download_token( $request );

    }

    public static function parse_app_status_action_request( Request $request ) : Response {
        static::guard( $request, 'install_plugins' );

        return HostingController::change_app_status( $request );
    }

    public static function parse_database_migration_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        $repo_version = smliser_settings()->get( 'smliser_repo_version', 0 );

        if ( version_compare( $repo_version, SMLISER_VER, '>' ) ) {
            smliser_send_json_error( [ 'message' => 'No upgrade needed' ] );
        }

        if ( Installer::install() ) {
            Installer::db_migrate( $repo_version );
        }

        smliser_settings()->set( 'smliser_repo_version', SMLISER_VER );

        smliser_send_json_success( [
            'message' => sprintf(
                'The repository has been migrated from version "%s" to version "%s".',
                $repo_version,
                SMLISER_VER
            ),
        ] );
    }

    public static function parse_save_provider_options_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return Controller::save_provider_options( $request );
    }

    public static function parse_toggle_monetization_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return Controller::toggle_monetization( $request );
    }

    public static function parse_monetization_provider_product_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return Controller::get_provider_product( $request );
    }

    public static function parse_monetization_tier_deletion_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return Controller::delete_monetization_tier( $request );
    }

    public static function parse_licensed_domain_removal_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return Controller::uninstall_domain_from_license( $request );
    }

    public static function parse_bulk_message_publish_request( Request $request ) : Response {
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
            $body = $response->get_body();
            $body['data']['redirect_url'] = smliser_bulk_messages_url()
            ->add_query_params( ['tab' => 'edit', 'msg_id' => $request->get( 'message_id' )] );

            $response->set_body( $body );
        }

        return $response;
    }

    public static function parse_access_control_save_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        $entity = $request->get( 'entity' );

        if ( ! $entity ) {
            smliser_send_json_error( [ 'message' => 'Please provide security entity.' ], 400 );
        }

        $method   = 'organization_member' === $entity ? 'save_organization_member' : 'save_entity';
        $response = RequestController::$method( $request );

        return $response;
    }

    public static function parse_access_control_delete_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        if ( ! $request->hasValue( 'entity_type' ) ) {
            smliser_send_json_error( [ 'message' => 'Please provide security entity type.' ], 400 );
        }

        // Core controller expects 'entity' key.
        $request->set( 'entity', $request->get( 'entity_type' ) );

        return RequestController::delete_entity( $request );

    }

    public static function parse_admin_security_entity_search_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        $entity = $request->get( 'entity_type', 'user' );

        $request->set( 'search_term', $request->get( 'search' ) );
        $request->set( 'status',      $request->get( 'status', 'active' ) );
        $request->set( 'types',       $request->get( 'types', Owner::get_allowed_owner_types() ) );

        $response = 'owner_subjects' === $entity
            ? RequestController::search_users_orgs( $request )
            : RequestController::search_resource_owners( $request );

        return $response;
    }

    public static function parse_smliser_delete_org_member_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        return RequestController::delete_org_member( $request );
    }

    public static function parse_default_email_settings_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        return EmailRequestController::save_default_email_options( $request );
    }

    public static function parse_email_test_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        return EmailRequestController::send_test_email( $request );
    }

    public static function parse_save_email_provider_request( Request $request ) : Response {
        static::guard( $request, 'super_admin' );

        return EmailRequestController::save_provider_settings( $request );
    }

    public static function parse_save_system_settings_request( Request $request ) : Response {
        static::guard( $request );
        
        return SettingsController::save_system_settings( $request );
    }

    public static function parse_preview_email_template_request( Request $request ) : Response {
        static::guard( $request );
        return SettingsController::preview_email_template( $request );
    }


    public static function parse_save_email_template_request( Request $request ) : Response {
        static::guard( $request );
        return SettingsController::save_email_template( $request );

    }

    public static function parse_reset_email_template_request( Request $request ) : Response {
        static::guard( $request );
        return SettingsController::reset_email_template( $request );

    }

    public static function parse_license_delete_request( Request $request ) : Response {
        static::guard( $request, 'manage_options', 'smliser_delete_license_nonce' );

        return Controller::delete_license( $request );

    }

    public static function parse_save_cache_adapter_settings_request( Request $request ) : Response {
        static::guard( $request );

        return CacheRequestController::save_adapter_settings( $request );

    }

    public static function parse_test_cache_adapter_settings_request( Request $request ) : Response {
        static::guard( $request );

        return CacheRequestController::test_cache_adapter_settings( $request );

    }

    public static function parse_get_cache_stats_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return CacheRequestController::get_cache_stats( $request );

    }

    public static function parse_clear_all_cache_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return CacheRequestController::clear_all_cache( $request );

    }

    public static function parse_delete_cache_by_prefix_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return CacheRequestController::delete_cache_by_prefix( $request );

    }

    public static function parse_flush_expired_cache_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return CacheRequestController::flush_expired_cache( $request );

    }

    public static function parse_get_top_cache_keys_request( Request $request ) : Response {
        static::guard( $request, 'manage_options' );

        return CacheRequestController::get_top_cache_keys( $request );
    }

    public static function render_client_dashboard( Request $request ) : Response {
        $registry       = smliserFrontendTemplate();
        $locator        = smliser_template_locator();

        $renderer       = new ClientDashboardRenderer( $registry, $locator );
        $rest_base      = restAPIUrl( 'client-dashboard' );
        
        return $renderer->asResponse ( $rest_base->url() );
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
    protected static function normalize_app_ids_form_input( array $app_ids ): array {
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
     * @param Request $request The current request object.
     * @return null|callable(Request):Response
     */
    protected static function resolve_download_request_parser( Request $request ): ?callable {

        switch ( $request->get( 'download_type' ) ) {
            case 'plugin':
            case 'plugins':
            case 'theme':
            case 'themes':
            case 'software':
                return [ __CLASS__, 'parse_public_package_download_request' ];
            case 'document':
            case 'documents':
                return [ __CLASS__, 'parse_license_document_download_request' ];
            case 'artifacts':
            case 'artifact':
                return [__CLASS__, 'parse_public_artifact_download_request'];
            default:
            return null;
        }
    }

    /**
     * Handle public downloads.
     * 
     * @param Request $request
     * @return Response
     */
    protected static function handle_public_downloads( Request $request ) : Response {
        $resolved_callback  = static::resolve_download_request_parser( $request );

        if ( ! $resolved_callback ) {
            return new FileResponse( new FileRequestException(
                'file_not_found',
                'Download type was not found.',
                [ 'status' => 404 ]
            ));
        }

        return $resolved_callback( $request );
    }

    /**
     * Resolve the bulk action controller for the given context.
     *
     * @param  string $context
     * @return callable
     */
    protected static function resolve_bulk_action_controller( $context ): callable {
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