<?php
/**
 * Authentication monitoring middleware class file.
 *
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Authentication\AuthenticationMonitor;

/**
 * Base middleware for authentication abuse monitoring.
 *
 * Provides common request monitoring and enforcement behavior for
 * authentication-sensitive endpoints.
 */
abstract class AuthenticationMonitorMiddleware implements MiddlewareInterface {

	/**
	 * Authentication monitor.
	 *
	 * @var AuthenticationMonitor
	 */
	protected AuthenticationMonitor $monitor;

	/**
	 * Constructor.
	 *
	 * @param AuthenticationMonitor $monitor Authentication behavior monitor.
	 */
	public function __construct( AuthenticationMonitor $monitor ) {
		$this->monitor = $monitor;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Inspect the request before allowing the authentication operation
	 * to proceed.
	 */
	public function handle( Request $request, callable $next ): mixed {

		$decision = $this->monitor->inspect(
			$this->context(),
			$request
		);

		if ( $decision->is_blocked() ) {
			return $this->blocked_response( $decision );
		}

		if ( $decision->is_throttled() ) {
			return $this->throttled_response( $decision );
		}

		return $next( $request );
	}

	/**
	 * Get the authentication context being monitored.
	 *
	 * @return string
	 */
	abstract protected function context(): string;

	/**
	 * Create a response for a blocked request.
	 *
	 * @param mixed $decision Monitoring decision.
	 *
	 * @return Response
	 */
	protected function blocked_response( $decision ): Response {

		$response = Response::make(
			'Authentication temporarily blocked.',
			429
		);

		$retry_after = $decision->retry_after();

		if ( null !== $retry_after ) {
			$response->set_header(
				'Retry-After',
				(string) $retry_after
			);
		}

		return $response;
	}

	/**
	 * Create a response for a throttled request.
	 *
	 * @param mixed $decision Monitoring decision.
	 *
	 * @return Response
	 */
	protected function throttled_response( $decision ): Response {

		$response = Response::make(
			'Too many authentication requests. Please try again later.',
			429
		);

		$retry_after = $decision->retry_after();

		if ( null !== $retry_after ) {
			$response->set_header(
				'Retry-After',
				(string) $retry_after
			);
		}

		return $response;
	}
}