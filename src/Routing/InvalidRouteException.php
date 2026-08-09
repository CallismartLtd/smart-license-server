<?php
/**
 * InvalidRouteException class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * Thrown when a route pattern is malformed, or violates a routing invariant:
 * duplicate parameter names, an unknown/invalid constraint, a required
 * segment following an optional one, a reserved query variable name, an
 * unknown route name passed to Router::url(), etc.
 */
final class InvalidRouteException extends \InvalidArgumentException {}