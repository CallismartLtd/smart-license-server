<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;

/**
 * Monitors login authentication requests.
 */
class LoginMonitorMiddleware extends AuthenticationMonitorMiddleware {

	protected function account_from_request( Request $request ): ?string {
		return $request->get( 'user_login' );
	}

	protected function source_from_request( Request $request ): string {
		return $request->ip();
	}
}