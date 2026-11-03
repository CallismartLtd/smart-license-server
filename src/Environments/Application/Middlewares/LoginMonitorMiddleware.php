<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;

/**
 * Monitors login authentication requests.
 */
class LoginMonitorMiddleware extends AuthenticationMonitorMiddleware {

	protected function account_from_request( Request $request ) : ?string {
		$account = $request->post( 'email' );
		return is_string( $account ) && '' !== trim( $account ) ? trim( $account ) : null;
	}

	protected function action() : string {
		return 'login';
	}
}
