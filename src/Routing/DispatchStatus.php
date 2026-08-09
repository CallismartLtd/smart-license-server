<?php
/**
 * DispatchStatus enum file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * The three possible outcomes of Router::dispatch().
 */
enum DispatchStatus {
	/** A route matched both the path and the method. */
	case Found;

	/** No registered route's pattern matches this path, for any method. */
	case NotFound;

	/** At least one route's pattern matches this path, but not for this method. */
	case MethodNotAllowed;
}