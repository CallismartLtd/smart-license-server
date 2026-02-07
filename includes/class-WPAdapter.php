<?php
/**
 * File for the WordPress adapter
 * 
 * @author Callistus
 * @package SmartLicenseServer\classes
 * @since 1.0.0
 */

namespace SmartLicenseServer;

use SmartLicenseServer\Admin\Menu;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequestController;
use SmartLicenseServer\FileSystem\DownloadsApi\FileRequest;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Messaging\MessageController;
use SmartLicenseServer\Monetization\Controller;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\ProviderCollection;
use SmartLicenseServer\RESTAPI\Versions\V1;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\RequestController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use const WP_CONTENT_DIR, ABSPATH;
use function wp_upload_dir, compact, smliser_get_request_param, smliser_get_query_param, time, is_string,
smliser_get_post_param, smliser_get_client_ip, smliser_get_authorization_header, smliser_get_user_agent, smliser_abort_request,
sanitize_text_field, unslash, current_user_can, get_query_var, smliser_send_json_error, wp_get_referer,
wp_safe_redirect, check_ajax_referer, is_callable, sprintf, is_super_admin;

defined( 'ABSPATH'  ) || exit;

/**
 * Wordpress adapter bridges the gap beween Smart License Server and request from
 * WP environments.
 */
class WPAdapter extends Config {

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

    }




}