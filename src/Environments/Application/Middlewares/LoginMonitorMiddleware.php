<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

/**
 * Monitors login authentication requests.
 */
class LoginMonitorMiddleware extends AuthenticationMonitorMiddleware {

	/**
	 * {@inheritdoc}
	 */
	protected function context(): string {
		return 'login';
	}
}