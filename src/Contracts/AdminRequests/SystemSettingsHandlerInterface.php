<?php
/**
 * System settings request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\AdminRequests
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage
 * platform-level settings and system operations: routing configuration,
 * general system settings, database migrations, and site health checks.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface SystemSettingsHandlerInterface {
	/**
	 * Parse database migration request.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_database_migration_request( Request $request ) : Response;

	/*
	|-------------------
	| SITE HEALTH CHECKS
	|-------------------
	*/

	/**
	 * Check that the server can reach the update server.
	 *
	 * Response: { "checks": [ check ] }
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_remote_service_check( Request $request ) : Response;

	/**
	 * First half of the cache persistence check: write a probe value to
	 * the configured cache adapter and return its token.
	 *
	 * Response: { "token": string, "checks": [] } when the probe was
	 * written, or { "token": null, "checks": [ check ] } when the adapter
	 * is unusable.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_cache_seed( Request $request ) : Response;

	/**
	 * Second half of the cache persistence check: read back the probe
	 * written by handle_cache_seed() in an earlier request.
	 *
	 * Expects a "token" parameter. Response: { "checks": [ check ] }, or
	 * HTTP 400 { "message": string } when the token is missing or malformed.
	 *
	 * @param  Request $request
	 * @return Response
	 */
	public function handle_cache_verify( Request $request ) : Response;
}