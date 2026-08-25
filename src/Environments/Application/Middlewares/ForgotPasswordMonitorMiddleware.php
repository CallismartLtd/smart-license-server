<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

/**
 * Monitors password recovery requests.
 */
class ForgotPasswordMonitorMiddleware extends AuthenticationMonitorMiddleware {

	/**
	 * {@inheritdoc}
	 */
	protected function context(): string {
		return 'forgot_password';
	}
}