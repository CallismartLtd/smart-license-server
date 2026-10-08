<?php
/**
 * Middleware class file.
 * 
 * @author Callistus NWachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Web;

use InvalidArgumentException;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Environments\Application\Middlewares\CallableResolver;
use SmartLicenseServer\Environments\Application\Middlewares\MiddlewareInterface;

/**
 * Executes a middleware stack around a final route handler.
 *
 * The pipeline resolves and normalizes all middleware definitions into
 * executable callables before dispatching the request, allowing the same
 * prepared pipeline to be dispatched multiple times without resolving the
 * middleware again.
 *
 * Middleware are executed in the order in which they are provided. Each
 * middleware receives the current request and a callable representing the
 * next step in the pipeline. The final step invokes the route handler.
 *
 * @package SmartLicenseServer
 */
final class MiddlewarePipeline {
	/**
	 * @var callable[]
	 */
	private array $middleware = [];

	/**
	 * @var callable
	 */
	private $handler;

	/**
	 * Constructor.
	 *
	 * @param CallableResolver	$resolver
	 * @param array<int,mixed>  $middleware
	 * @param mixed             $handler
	 */
	private function __construct(
		protected CallableResolver $resolver,
		array $middleware, mixed $handler
	) {
		foreach ( $middleware as $item ) {
			$this->middleware[] = $this->resolver->resolveMiddleware( $item );
		}

		$this->handler	= $this->resolver->resolve( $handler );
	}

	/**
	 * Run a middleware pipeline.
	 *
	 * @param CallableResolver	$resolver
	 * @param array<int,mixed> $middleware
	 * @param mixed            $handler
	 * @param Request          $request
	 * @return mixed
	 */
	public static function run(
		CallableResolver $resolver,
		array $middleware,
		mixed $handler,
		Request $request
	): mixed {
		return ( new self( $resolver, $middleware, $handler ) )->dispatch( $request );
	}

	/**
	 * Dispatch the request through the middleware stack.
	 *
	 * @param Request $request
	 * @return mixed
	 */
	private function dispatch( Request $request ): mixed {
		return $this->handleStep( 0, $request );
	}

	/**
	 * Execute a middleware step.
	 *
	 * @param int     $index
	 * @param Request $request
	 * @return mixed
	 */
	private function handleStep( int $index, Request $request ): mixed {
		if ( ! isset( $this->middleware[ $index ] ) ) {
			return ( $this->handler )( $request );
		}

		$next = function ( Request $request ) use ( $index ): mixed {
			return $this->handleStep( $index + 1, $request );
		};

		return ( $this->middleware[ $index ] )( $request, $next );
	}
}