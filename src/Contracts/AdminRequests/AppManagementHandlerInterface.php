<?php
/**
 * Application management request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\Apps
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage hosted
 * applications: saving app metadata, asset/artifact upload and
 * deletion, and status transitions.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface AppManagementHandlerInterface {

	/**
	 * Parse save application request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_save_app_request( Request $request ) : Response;

	/**
	 * Parse application asset upload request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_app_asset_upload_request( Request $request ) : Response;

	/**
	 * Parse application asset delete request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_app_asset_delete_request( Request $request ) : Response;

	/**
	 * Parse application artifact upload request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_app_artifact_upload_request( Request $request ) : Response;

	/**
	 * Parse application artifact delete request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_app_artifact_delete_request( Request $request ) : Response;

	/**
	 * Parse application status action request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_app_status_action_request( Request $request ) : Response;
}