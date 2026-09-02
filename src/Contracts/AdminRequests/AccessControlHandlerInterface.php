<?php
/**
 * Access control request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage access
 * control rules and admin security entity lookups.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface AccessControlHandlerInterface {

	/**
	 * Parse access control save request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_access_control_save_request( Request $request ) : Response;

	/**
	 * Parse access control delete request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_access_control_delete_request( Request $request ) : Response;

	/**
	 * Parse admin security entity search request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_admin_security_entity_search_request( Request $request ) : Response;
}