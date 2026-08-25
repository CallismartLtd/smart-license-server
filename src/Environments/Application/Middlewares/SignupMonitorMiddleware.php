<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

/**
 * Monitors account registration requests.
 */
class SignupMonitorMiddleware extends AuthenticationMonitorMiddleware {

	/**
	 * {@inheritdoc}
	 */
	protected function context(): string {
		return 'signup';
	}
}