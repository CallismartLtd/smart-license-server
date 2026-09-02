<?php
/**
 * Email settings and template request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\Email
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage email
 * delivery: default settings, provider configuration, test sends,
 * and template editing.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface EmailHandlerInterface {

	/**
	 * Parse request to save default email settings.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_default_email_settings_request( Request $request ) : Response;

	/**
	 * Parse email test request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_email_test_request( Request $request ) : Response;

	/**
	 * Parse save email provider settings request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_save_email_provider_request( Request $request ) : Response;

	/**
	 * Parse save email template toggle request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_save_email_template_toggle_request( Request $request ) : Response;

	/**
	 * Parse request to preview email template.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_preview_email_template_request( Request $request ) : Response;

	/**
	 * Parse request to save email template.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_save_email_template_request( Request $request ) : Response;

	/**
	 * Parse request to reset email template.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_reset_email_template_request( Request $request ) : Response;
}