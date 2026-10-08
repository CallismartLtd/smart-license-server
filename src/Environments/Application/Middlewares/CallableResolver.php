<?php
/**
 * Middleware resolver class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
namespace SmartLicenseServer\Environments\Application\Middlewares;

use InvalidArgumentException;
use SmartLicenseServer\Core\Container\Container;

/**
 * Attempts to resolve a mixed data to a callable.
 */
final class CallableResolver {
    public function __construct( protected Container $container ) {}

	/**
	 * Resolve a middleware definition to an executable callable.
	 *
	 * @param mixed $middleware
	 * @return callable
	 * @throws InvalidArgumentException
	 */
	public function resolveMiddleware( mixed $middleware ): callable {
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
		return $this->resolve( $middleware );
	}

	/**
	 * Resolve a mixed data type to an executable callable.
	 *
	 * @param mixed $data
	 * @return callable
	 * @throws InvalidArgumentException
	 */
	public function resolve( mixed $data ): callable {
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
	public function resolveInstanceMethod( string $class, string $method ): callable {
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
	public function resolveStaticMethod( string $class, string $method ): callable {
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