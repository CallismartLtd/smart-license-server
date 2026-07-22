<?php
/**
 * Request handling Contract file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environments;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge between a host PHP environment and the
 * SmartLicenseServer core.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface RequestBridgeInterface {

    /**
     * Parse public request to download a hoted app main zip file.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_public_package_download_request( Request $request ) : Response;

    /**
     * Parse public request to download a hosted app artifact file.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_public_artifact_download_request( Request $request ) : Response;

    /**
     * Parse admin download request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_admin_download_request( Request $request ) : Response;

    /**
     * Parse license document download request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_license_document_download_request( Request $request ) : Response;

    /**
     * Parse application asset request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_app_asset_request( Request $request ) : Response;

    /**
     * Parse uploads directory access request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_uploads_dir_request( Request $request ) : Response;

    /**
     * Parse proxy image request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_proxy_image_request( Request $request ) : Response;

    /**
     * Parse save application request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_save_app_request( Request $request ) : Response;

    /**
     * Parse application asset upload request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_app_asset_upload_request( Request $request ) : Response;

    /**
     * Parse application asset delete request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_app_asset_delete_request( Request $request ) : Response;

    /**
     * Parse application artifact upload request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_app_artifact_upload_request( Request $request ) : Response;

    /**
     * Parse application artifact delete request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_app_artifact_delete_request( Request $request ) : Response;

    /**
     * Parse save license request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_save_license_request( Request $request ) : Response;

    /**
     * Parse bulk action request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_bulk_action_request( Request $request ) : Response;

    /**
     * Parse monetization tier form submission.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_monetization_tier_form_request( Request $request ) : Response;

    /**
     * Parse save routes settings request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_save_routes_settings_request( Request $request ) : Response;

    /**
     * Parse download token generation request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_download_token_generation_request( Request $request ) : Response;

    /**
     * Parse application status action request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_app_status_action_request( Request $request ) : Response;

    /**
     * Parse database migration request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_database_migration_request( Request $request ) : Response;

    /**
     * Parse save provider options request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_save_provider_options_request( Request $request ) : Response;

    /**
     * Parse toggle monetization request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_toggle_monetization_request( Request $request ) : Response;

    /**
     * Parse monetization provider product request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_monetization_provider_product_request( Request $request ) : Response;

    /**
     * Parse monetization tier deletion request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_monetization_tier_deletion_request( Request $request ) : Response;

    /**
     * Parse licensed domain removal request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_licensed_domain_removal_request( Request $request ) : Response;

    /**
     * Parse bulk message publish request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_bulk_message_publish_request( Request $request ) : Response;

    /**
     * Parse access control save request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_access_control_save_request( Request $request ) : Response;

    /**
     * Parse access control delete request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_access_control_delete_request( Request $request ) : Response;

    /**
     * Parse admin security entity search request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_admin_security_entity_search_request( Request $request ) : Response;

    /**
     * Parse request to delete an organization member.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_smliser_delete_org_member_request( Request $request ) : Response;

    /**
     * Parse request to save default email settings.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_default_email_settings_request( Request $request ) : Response;

    /**
     * Parse email test request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_email_test_request( Request $request ) : Response;

    /**
     * Parse save email provider settings request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_save_email_provider_request( Request $request ) : Response;

    /**
     * Parse save email template toggle request.
     *
     * @param  Request $request
     * @return Response
     */
    public static function handle_save_email_template_toggle_request( Request $request ) : Response;

    /**
     * Parse save system settings request.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_save_system_settings_request( Request $request ) : Response;

    /**
     * Parse request to preview email template.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_preview_email_template_request(  Request $request ) : Response;

    /**
     * Parse request to save email template.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_save_email_template_request(  Request $request ) : Response;
    
    /**
     * Parse request to reset email template.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_reset_email_template_request(  Request $request ) : Response;
    
    /**
     * Parse license delete request.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_license_delete_request( Request $request ) : Response;

    /**
     * Parse request to save cache adapter settings.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_save_cache_adapter_settings_request( Request $request ) : Response;

    /**
     * Parse test cache adapter settings request.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_test_cache_adapter_settings_request( Request $request ) : Response;
    
    /**
     * Parse request to get cache stats.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_get_cache_stats_request( Request $request ) : Response;

    /**
     * Parse request to clear all cache data.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_clear_all_cache_request( Request $request ) : Response;
    
    /**
     * Parse request to delete cache by prefix.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_delete_cache_by_prefix_request( Request $request ) : Response;

    /**
     * Parse request to flush expired cache data.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_flush_expired_cache_request( Request $request ) : Response;

    /**
     * Parse request to get top cache keys.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_get_top_cache_keys_request( Request $request ) : Response;

    /**
     * Render the client dashboard shell.
     * 
     * @param Request $request
     * @return Response
     */
    public static function render_client_dashboard( Request $request ) : Response;   
}