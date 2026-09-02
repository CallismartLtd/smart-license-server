<?php
/**
 * Bulk action request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\Admin
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that support bulk
 * admin actions, including bulk message publishing.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface BulkActionHandlerInterface {

	/**
	 * Parse bulk action request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_bulk_action_request( Request $request ) : Response;

	/**
	 * Parse bulk message publish request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_bulk_message_publish_request( Request $request ) : Response;
}