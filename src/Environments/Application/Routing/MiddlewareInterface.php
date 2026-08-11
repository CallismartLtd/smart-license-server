<?php
/**
 * MiddlewareInterface contract file.
 *
 * @package SmartLicenseServer\Environments\Application\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Routing;

use SmartLicenseServer\Core\Request;

/**
 * Contract for application middleware layers.
 */
interface MiddlewareInterface {

	/**
	 * Process an incoming request and pass execution to the next layer.
	 *
	 * @param Request  $request Framework request object.
	 * @param callable $next    Next middleware or route handler closure in the pipeline.
	 * @return mixed
	 */
	public function handle( Request $request, callable $next ): mixed;
}