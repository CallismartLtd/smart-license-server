<?php
/**
 * Monetization request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\Monetization
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage monetization:
 * tiers, provider products, provider options, and the monetization
 * on/off toggle.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface MonetizationHandlerInterface {

	/**
	 * Parse monetization tier form submission.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_monetization_tier_form_request( Request $request ) : Response;

	/**
	 * Parse save provider options request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_save_provider_options_request( Request $request ) : Response;

	/**
	 * Parse toggle monetization request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_toggle_monetization_request( Request $request ) : Response;

	/**
	 * Parse monetization provider product request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_monetization_provider_product_request( Request $request ) : Response;

	/**
	 * Parse monetization tier deletion request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_monetization_tier_deletion_request( Request $request ) : Response;
}