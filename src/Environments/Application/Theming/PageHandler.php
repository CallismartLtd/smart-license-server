<?php
/**
 * Page handler class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

/**
 * Marks a route's handler as a page.
 *
 * PageRoutes stores this on the route instead of the bare handler, so the
 * dispatcher knows before calling it that the route is a themed page: the
 * handler must return a View and must not print. Every other route keeps
 * its bare handler and never reaches the theme layer.
 */
final class PageHandler {

	/**
	 * Constructor.
	 *
	 * @param mixed $handler The page handler, in any form CallableResolver accepts
	 *                       ([Class::class, 'method'], "Class@method", a closure...).
	 */
	public function __construct( public readonly mixed $handler ) {}

	/**
	 * A readable name of the handler, for error messages.
	 *
	 * @return string
	 */
	public function describe() : string {
		$handler = $this->handler;

		if ( is_array( $handler ) && 2 === count( $handler ) ) {
			$class = is_object( $handler[0] ) ? $handler[0]::class : (string) $handler[0];

			return $class . '::' . (string) $handler[1];
		}

		if ( is_string( $handler ) ) {
			return $handler;
		}

		return get_debug_type( $handler );
	}
}
