<?php
/**
 * Cache adapter request handling contract.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts\System
 */

namespace SmartLicenseServer\Contracts\AdminRequests;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Defines the request bridge for environments that manage the cache
 * adapter: configuration, connection testing, statistics, and
 * clearing/flushing cached data.
 *
 * Implementations adapt environment-specific requests into core Request
 * objects and delegate them to the corresponding core controllers,
 * returning the resulting Response.
 */
interface CacheAdapterHandlerInterface {

	/**
	 * Parse request to save cache adapter settings.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_save_cache_adapter_settings_request( Request $request ) : Response;

	/**
	 * Parse test cache adapter settings request.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_test_cache_adapter_settings_request( Request $request ) : Response;

	/**
	 * Handle admin request to reset cache adapter settings.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_reset_cache_adapter_settings_request( Request $request ) : Response;

	/**
	 * Parse request to get cache stats.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_get_cache_stats_request( Request $request ) : Response;

	/**
	 * Parse request to clear all cache data.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_clear_all_cache_request( Request $request ) : Response;

	/**
	 * Parse request to flush expired cache data.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_flush_expired_cache_request( Request $request ) : Response;
}