<?php
/**
 * File delivery request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\Files
 */

namespace SmartLicenseServer\Contracts\Files;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that serve files:
 * hosted app packages/artifacts, license documents, app assets,
 * uploads, proxied images, and download tokens.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface FileDeliveryHandlerInterface {

	/**
	 * Parse public request to download a hosted app main zip file.
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
	 * Parse download token generation request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public static function handle_download_token_generation_request( Request $request ) : Response;
}