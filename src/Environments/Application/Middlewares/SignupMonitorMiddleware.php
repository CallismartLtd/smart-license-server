<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;

/**
 * Monitors account registration requests.
 */
class SignupMonitorMiddleware extends AuthenticationMonitorMiddleware {

	protected function account_from_request( Request $request ): ?string {
		return $request->get( 'email' );
	}

	protected function source_from_request( Request $request ): string {
		return $request->ip();
	}
}