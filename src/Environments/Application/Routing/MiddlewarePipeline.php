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
 */
final class MiddlewarePipeline {

	/**
	 * Resolved middleware items.
	 *
	 * @var array<int,callable|string|object>
	 */
	private array $middleware;

	/**
	 * The final route handler.
	 *
	 * @var callable
	 */
	private $handler;

	/**
	 * Constructor.
	 *
	 * @param array<int,callable|string|object> $middleware Resolved middleware stack.
	 * @param callable                          $handler    Final route handler.
	 */
	private function __construct( array $middleware, callable $handler ) {
		$this->middleware = $middleware;
		$this->handler    = $handler;
	}

	/**
	 * Static helper to instantiate and execute the pipeline in one call.
	 *
	 * @param array<int,callable|string|object> $middleware Stack to execute.
	 * @param callable                          $handler    Route handler.
	 * @param Request                           $request    Request instance.
	 * @return mixed
	 */
	public static function run( array $middleware, callable $handler, Request $request ): mixed {
		return ( new self( $middleware, $handler ) )->dispatch( $request );
	}

	/**
	 * Dispatch the request through the middleware stack.
	 *
	 * @param Request $request Framework request instance.
	 * @return mixed
	 */
	public function dispatch( Request $request ): mixed {
		$pipeline = array_reduce(
			array_reverse( $this->middleware ),
			array( $this, 'carry' ),
			array( $this, 'finalDestination' )
		);

		return $pipeline( $request );
	}

	/**
	 * Creates a closure layer wrapping a single middleware step.
	 *
	 * @param callable $next Next closure in the stack.
	 * @param mixed    $mw   Current middleware item to execute.
	 * @return callable
	 */
	private function carry( callable $next, mixed $mw ): callable {
		return function ( Request $request ) use ( $mw, $next ): mixed {
			$resolved = $this->resolveMiddleware( $mw );

			return $resolved( $request, $next );
		};
	}

	/**
	 * The terminal closure invoked at the bottom of the middleware stack.
	 *
	 * @param Request $request Framework request instance.
	 * @return mixed
	 */
	private function finalDestination( Request $request ): mixed {
		return ( $this->handler )( $request );
	}

	/**
	 * Resolves a middleware item to an invokable callable.
	 *
	 * @param mixed $middleware Item to resolve (callable, class-string, or object).
	 * @return callable
	 * @throws \InvalidArgumentException If the middleware cannot be resolved.
	 */
	private function resolveMiddleware( mixed $middleware ): callable {
		if ( is_callable( $middleware ) ) {
			return $middleware;
		}

		if ( is_string( $middleware ) && class_exists( $middleware ) ) {
			$middleware = new $middleware();
		}

		if ( $middleware instanceof MiddlewareInterface ) {
			return array( $middleware, 'handle' );
		}

		if ( is_object( $middleware ) && is_callable( $middleware ) ) {
			return $middleware;
		}

		throw new \InvalidArgumentException(
			sprintf(
				'Middleware must implement MiddlewareInterface, be callable, or be an invokable class name. Given: %s',
				get_debug_type( $middleware )
			)
		);
	}
}