<?php
/**
 * Middleware class file.
 * 
 * @author Callistus NWachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Routing;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Environments\Application\Middlewares\MiddlewareInterface;
use SmartLicenseServer\Security\Context\Guard;

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
 * @package SmartLicenseServer\Environments\Application\Routing
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

	protected Guard $guard;

	/**
	 * Constructor.
	 *
	 * @param array<int,mixed> $middleware
	 * @param callable         $handler
	 */
	private function __construct( array $middleware, callable $handler, Guard $guard ) {
		foreach ( $middleware as $item ) {
			$this->middleware[] = $this->resolveMiddleware( $item );
		}

		$this->handler	= $handler;
		$this->guard	= $guard;
	}

	/**
	 * Run a middleware pipeline.
	 *
	 * @param array<int,mixed> $middleware
	 * @param callable         $handler
	 * @param Request           $request
	 * @return mixed
	 */
	public static function run( array $middleware, callable $handler, Request $request, Guard $guard ): mixed {
		return ( new self( $middleware, $handler, $guard ) )->dispatch( $request );
	}

	/**
	 * Dispatch the request through the middleware stack.
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function dispatch( Request $request ): mixed {
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

	/**
	 * Resolve a middleware definition to an executable callable.
	 *
	 * @param mixed $middleware
	 * @return callable
	 */
	private function resolveMiddleware( mixed $middleware ): callable {
		if ( is_string( $middleware ) && class_exists( $middleware ) ) {
			$middleware = new $middleware();
		}

		if ( $middleware instanceof MiddlewareInterface ) {
			return [ $middleware, 'handle' ];
		}

		if ( is_callable( $middleware ) ) {
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