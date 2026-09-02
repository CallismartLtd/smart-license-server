<?php
/**
 * License request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\Licensing
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage licenses:
 * saving, deleting, and removing licensed domains.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface LicenseHandlerInterface {

	/**
	 * Parse save license request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_save_license_request( Request $request ) : Response;

	/**
	 * Parse license delete request.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_license_delete_request( Request $request ) : Response;

	/**
	 * Parse licensed domain removal request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_licensed_domain_removal_request( Request $request ) : Response;
}