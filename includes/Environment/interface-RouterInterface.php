<?php
/**
 * Router interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environment;

/**
 * Defines the contracts for routing request parsers.
 */
interface RouterInterface {

    /**
     * Parse public package download request.
     *
     * @return void
     */
    public static function parse_public_package_download_request() : void;

    /**
     * Parse admin download request.
     *
     * @return void
     */
    public static function parse_admin_download_request() : void;

    /**
     * Parse license document download request.
     *
     * @return void
     */
    public static function parse_license_document_download_request() : void;

    /**
     * Parse application asset request.
     *
     * @return void
     */
    public static function parse_app_asset_request() : void;

    /**
     * Parse uploads directory access request.
     *
     * @return void
     */
    public static function parse_uploads_dir_request() : void;

    /**
     * Parse proxy image request.
     *
     * @return void
     */
    public static function parse_proxy_image_request() : void;

    /**
     * Parse save application request.
     *
     * @return void
     */
    public static function parse_save_app_request() : void;

    /**
     * Parse application asset upload request.
     *
     * @return void
     */
    public static function parse_app_asset_upload_request() : void;

    /**
     * Parse application asset delete request.
     *
     * @return void
     */
    public static function parse_app_asset_delete_request() : void;

    /**
     * Parse save license request.
     *
     * @return void
     */
    public static function parse_save_license_request() : void;

    /**
     * Parse bulk action request.
     *
     * @return void
     */
    public static function parse_bulk_action_request() : void;

    /**
     * Parse monetization tier form submission.
     *
     * @return void
     */
    public static function parse_monetization_tier_form_request() : void;

    /**
     * Handle options form submission.
     *
     * @return void
     */
    public static function parse_options_form_request() : void;

    /**
     * Load authentication template.
     * 
     * @param string $template
     * @return void
     */
    public static function load_auth_template( string $template ) : string;

    /**
     * Parse download token generation request.
     *
     * @return void
     */
    public static function parse_download_token_generation_request() : void;

    /**
     * Parse application status action request.
     *
     * @return void
     */
    public static function parse_app_status_action_request() : void;

    /**
     * Parse database migration request.
     *
     * @return void
     */
    public static function parse_database_migration_request() : void;

    /**
     * Parse save provider options request.
     *
     * @return void
     */
    public static function parse_save_provider_options_request() : void;

    /**
     * Parse toggle monetization request.
     *
     * @return void
     */
    public static function parse_toggle_monetization_request() : void;

    /**
     * Parse monetization provider product request.
     *
     * @return void
     */
    public static function parse_monetization_provider_product_request() : void;

    /**
     * Parse monetization tier deletion request.
     *
     * @return void
     */
    public static function parse_monetization_tier_deletion_request() : void;

    /**
     * Parse licensed domain removal request.
     *
     * @return void
     */
    public static function parse_licensed_domain_removal_request() : void;

    /**
     * Parse bulk message publish request.
     *
     * @return void
     */
    public static function parse_bulk_message_publish_request() : void;

    /**
     * Parse access control save request.
     *
     * @return void
     */
    public static function parse_access_control_save_request() : void;

    /**
     * Parse access control delete request.
     *
     * @return void
     */
    public static function parse_access_control_delete_request() : void;

    /**
     * Parse admin security entity search request.
     *
     * @return void
     */
    public static function parse_admin_security_entity_search_request() : void;

    /**
     * Parse request to delete an organization member.
     *
     * @return void
     */
    public static function parse_smliser_delete_org_member_request() : void;
}
