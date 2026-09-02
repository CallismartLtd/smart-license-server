<?php
/**
 * Organization request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\Organization
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage
 * multi-tenant organization membership.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface OrganizationHandlerInterface {

	/**
	 * Parse request to delete an organization member.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_smliser_delete_org_member_request( Request $request ) : Response;
}