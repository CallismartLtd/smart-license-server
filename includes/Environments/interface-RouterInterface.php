<?php
/**
 * Router interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environments;

use SmartLicenseServer\Core\Request;

/**
 * Defines the contracts for routing request parsers.
 */
interface RouterInterface {

    /**
     * Parse public package download request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_public_package_download_request( Request $request ): void;

    /**
     * Parse admin download request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_admin_download_request( Request $request ): void;

    /**
     * Parse license document download request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_license_document_download_request( Request $request ): void;

    /**
     * Parse application asset request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_app_asset_request( Request $request ): void;

    /**
     * Parse uploads directory access request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_uploads_dir_request( Request $request ): void;

    /**
     * Parse proxy image request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_proxy_image_request( Request $request ): void;

    /**
     * Parse save application request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_save_app_request( Request $request ): void;

    /**
     * Parse application asset upload request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_app_asset_upload_request( Request $request ): void;

    /**
     * Parse application asset delete request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_app_asset_delete_request( Request $request ): void;

    /**
     * Parse save license request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_save_license_request( Request $request ): void;

    /**
     * Parse bulk action request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_bulk_action_request( Request $request ): void;

    /**
     * Parse monetization tier form submission.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_monetization_tier_form_request( Request $request ): void;

    /**
     * Parse save routes settings request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_save_routes_settings_request( Request $request ): void;

    /**
     * Load authentication template.
     *
     * @param  string $template
     * @return string
     */
    public static function load_auth_template( string $template ): string;

    /**
     * Parse download token generation request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_download_token_generation_request( Request $request ): void;

    /**
     * Parse application status action request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_app_status_action_request( Request $request ): void;

    /**
     * Parse database migration request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_database_migration_request( Request $request ): void;

    /**
     * Parse save provider options request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_save_provider_options_request( Request $request ): void;

    /**
     * Parse toggle monetization request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_toggle_monetization_request( Request $request ): void;

    /**
     * Parse monetization provider product request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_monetization_provider_product_request( Request $request ): void;

    /**
     * Parse monetization tier deletion request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_monetization_tier_deletion_request( Request $request ): void;

    /**
     * Parse licensed domain removal request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_licensed_domain_removal_request( Request $request ): void;

    /**
     * Parse bulk message publish request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_bulk_message_publish_request( Request $request ): void;

    /**
     * Parse access control save request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_access_control_save_request( Request $request ): void;

    /**
     * Parse access control delete request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_access_control_delete_request( Request $request ): void;

    /**
     * Parse admin security entity search request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_admin_security_entity_search_request( Request $request ): void;

    /**
     * Parse request to delete an organization member.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_smliser_delete_org_member_request( Request $request ): void;

    /**
     * Parse request to save default email settings.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_default_email_settings_request( Request $request ): void;

    /**
     * Parse email test request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_email_test_request( Request $request ): void;

    /**
     * Parse save email provider settings request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_save_email_provider_request( Request $request ): void;

    /**
     * Parse save email template toggle request.
     *
     * @param  Request $request
     * @return void
     */
    public static function parse_save_email_template_toggle_request( Request $request ): void;

    /**
     * Parse save system settings request.
     * 
     * @param Request $request
     * @return void
     */
    public static function parse_save_system_settings_request( Request $request ) : void;

    /**
     * Parse request to preview email template.
     * 
     * @param Request $request
     * @return void
     */
    public static function parse_preview_email_template_request(  Request $request ) : void;

    /**
     * Parse request to save email template.
     * 
     * @param Request $request
     * @return void
     */
    public static function parse_save_email_template_request(  Request $request ) : void;
    
    /**
     * Parse request to reset email template.
     * 
     * @param Request $request
     * @return void
     */
    public static function parse_reset_email_template_request(  Request $request ) : void;
    
}