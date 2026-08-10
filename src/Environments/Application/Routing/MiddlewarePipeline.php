<?php
/**
 * MiddlewarePipeline class file.
 *
 * @package SmartLicenseServer\Environments\Application\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Routing;

use SmartLicenseServer\Core\Request;

/**
 * Executes a resolved middleware stack around a final handler.
 *
 * This is the piece core's Router deliberately does not do — it only ever
 * assembles and returns the middleware list as data (see
 * SmartLicenseServer\Routing\Route's docblock). Something has to actually
 * call it, and that's an environment decision, which is why this class
 * lives here rather than in core.
 */
final class MiddlewarePipeline {

	/**
	 * Run the middleware stack around the final handler.
	 * 
	 * @param array<int,callable> $middleware Resolved stack, outer to inner —
	 *                                        typically DispatchResult::$middleware.
	 * @param callable             $handler    The route's handler, called last if
	 *                                        every middleware calls $next.
	 * @param Request			   $request    The request object, passed through to every
	 *                                        middleware and the handler.
	 */
	public static function run( array $middleware, callable $handler, Request $request ): mixed {
		$next = array_reduce(
			array_reverse( $middleware ),
			static function ( callable $next, callable $mw ): callable {
				return static function ( Request $request ) use ( $mw, $next ): mixed {
					return $mw( $request, $next );
				};
			},
			static function ( Request $request ) use ( $handler ): mixed {
				return $handler( $request );
			}
		);

		return $next( $request );
	}
}