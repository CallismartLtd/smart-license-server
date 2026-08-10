<?php
/**
 * MiddlewarePipeline class file.
 *
 * @package SmartLicenseServer\Environments\Application\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Routing;

/**
 * Executes a resolved middleware stack around a final handler.
 *
 * This is the piece core's Router deliberately does not do — it only ever
 * assembles and returns the middleware list as data (see
 * SmartLicenseServer\Routing\Route's docblock). Something has to actually
 * call it, and that's an environment decision, which is why this class
 * lives here rather than in core.
 *
 * Classic "onion" order: the first middleware in the array runs first and
 * wraps everything after it, including every later middleware and the
 * handler itself. Each middleware decides whether to call $next (continue
 * inward) or return its own result without calling $next (short-circuit —
 * e.g. an auth check rejecting the request before the handler ever runs).
 *
 * Calling convention every middleware and the final handler must follow:
 *
 *   middleware( $request, array $params, callable $next ): mixed
 *   handler(    $request, array $params ): mixed
 *
 * $next itself has signature `($request, array $params): mixed` — calling
 * it continues to the next middleware, or to the handler once the stack is
 * exhausted.
 */
final class MiddlewarePipeline {

	/**
	 * @param array<int,callable> $middleware Resolved stack, outer to inner —
	 *                                        typically DispatchResult::$middleware.
	 * @param callable             $handler    The route's handler, called last if
	 *                                        every middleware calls $next.
	 * @param array<string,string> $params
	 */
	public static function run( array $middleware, callable $handler, mixed $request, array $params ): mixed {
		$next = array_reduce(
			array_reverse( $middleware ),
			static function ( callable $next, callable $mw ): callable {
				return static function ( $request, array $params ) use ( $mw, $next ): mixed {
					return $mw( $request, $params, $next );
				};
			},
			static function ( $request, array $params ) use ( $handler ): mixed {
				return $handler( $request, $params );
			}
		);

		return $next( $request, $params );
	}
}