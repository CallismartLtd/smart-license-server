<?php
/**
 * Middleware class file.
 * 
 * @author Callistus NWachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Routing;

use InvalidArgumentException;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
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
	 * The DI container.
	 * 
	 * @var Container $container
	 */
	private Container $container;

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
	 * @param Container         $container
	 * @param array<int,mixed>  $middleware
	 * @param mixed             $handler
	 */
	private function __construct( Container $container, array $middleware, mixed $handler ) {
		$this->container	= $container;
		
		foreach ( $middleware as $item ) {
			$this->middleware[] = $this->resolveMiddleware( $item );
		}

		$this->handler	= $this->resolveCallable( $handler );
	}

	/**
	 * Run a middleware pipeline.
	 *
	 * @param Container        $container
	 * @param array<int,mixed> $middleware
	 * @param mixed            $handler
	 * @param Request          $request
	 * @return mixed
	 */
	public static function run(
		Container $container,
		array $middleware,
		mixed $handler,
		Request $request
	): mixed {
		return ( new self( $container, $middleware, $handler ) )->dispatch( $request );
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

	/**
	 * Resolve a middleware definition to an executable callable.
	 *
	 * @param mixed $middleware
	 * @return callable
	 * @throws InvalidArgumentException
	 */
	private function resolveMiddleware( mixed $middleware ): callable {
		$resolved = $middleware;

		if ( is_string( $middleware ) && class_exists( $middleware ) ) {
			$resolved = $this->container->get( $middleware );
		}

		if ( $resolved instanceof MiddlewareInterface ) {
			return [ $resolved, 'handle' ];
		}

		if ( is_callable( $resolved ) ) {
			return $resolved;
		}

		// Fall back to the general resolver for "Class@method", "Class::method",
		// array notation, etc. — operates on the original, unmutated input.
		return $this->resolveCallable( $middleware );
	}

	/**
	 * Resolve a mixed data type to an executable callable.
	 *
	 * @param mixed $data
	 * @return callable
	 * @throws InvalidArgumentException
	 */
	private function resolveCallable( mixed $data ): callable {
		// Direct callable check (Closures, functions, valid array [$obj, 'method'], etc.).
		if ( is_callable( $data ) ) {
			return $data;
		}

		// String representation of "Class@method" or "Class::method".
		if ( is_string( $data ) ) {
			if ( str_contains( $data, '@' ) ) {
				[ $class, $method ] = explode( '@', $data, 2 );
				return $this->resolveInstanceMethod( $class, $method );
			}

			if ( str_contains( $data, '::' ) ) {
				[ $class, $method ] = explode( '::', $data, 2 );
				return $this->resolveStaticMethod( $class, $method );
			}

			// Invokable class name (e.g., 'App\Handlers\MyHandler')
			if ( class_exists( $data ) ) {
				return $this->resolveInstanceMethod( $data, '__invoke' );
			}
		}

		// Array notation with class string: ['Class', 'method'].
		if ( is_array( $data ) && count( $data ) === 2 ) {
			[ $class, $method ] = $data;
			if ( is_string( $class ) && class_exists( $class ) ) {
				return $this->resolveInstanceMethod( $class, $method );
			}
		}

		// Object implementing __invoke.
		if ( is_object( $data ) && method_exists( $data, '__invoke' ) ) {
			return $data;
		}

		throw new InvalidArgumentException(
			sprintf( 'Unable to resolve supplied data of type [%s] to a valid callable.', get_debug_type( $data ) )
		);
	}

	/**
	 * Resolve a class/method pair to a callable via the container, verifying
	 * the method exists and is actually callable on the resolved instance.
	 *
	 * @param string $class
	 * @param string $method
	 * @return callable
	 * @throws InvalidArgumentException
	 */
	private function resolveInstanceMethod( string $class, string $method ): callable {
		if ( ! class_exists( $class ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Class [%s] does not exist.', $class )
			);
		}

		if ( ! method_exists( $class, $method ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Method [%s] does not exist on class [%s].', $method, $class )
			);
		}

		$instance = $this->container->get( $class );
		$callable = [ $instance, $method ];

		if ( ! is_callable( $callable ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Method [%s] on class [%s] is not callable.', $method, $class )
			);
		}

		return $callable;
	}

	/**
	 * Resolve a class/method pair to a static callable, verifying the
	 * method exists and is actually callable.
	 *
	 * @param string $class
	 * @param string $method
	 * @return callable
	 * @throws InvalidArgumentException
	 */
	private function resolveStaticMethod( string $class, string $method ): callable {
		if ( ! class_exists( $class ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Class [%s] does not exist.', $class )
			);
		}

		if ( ! method_exists( $class, $method ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Method [%s] does not exist on class [%s].', $method, $class )
			);
		}

		$callable = [ $class, $method ];

		if ( ! is_callable( $callable ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Method [%s] on class [%s] is not callable (must be static).', $method, $class )
			);
		}

		return $callable;
	}
}