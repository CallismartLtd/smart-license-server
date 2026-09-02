<?php
/**
 * System settings request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\System
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage
 * platform-level settings: routing configuration, general system
 * settings, and database migrations.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface SystemSettingsHandlerInterface {

	/**
	 * Parse save routes settings request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_save_routes_settings_request( Request $request ) : Response;

	/**
	 * Parse database migration request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_database_migration_request( Request $request ) : Response;

	/**
	 * Parse save system settings request.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_save_system_settings_request( Request $request ) : Response;
}