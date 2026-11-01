<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;

/**
 * Monitors account registration requests.
 */
class SignupMonitorMiddleware extends AuthenticationMonitorMiddleware {

	protected function account_from_request( Request $request ) : ?string {
		$email = $request->post( 'email' );
		return is_string( $email ) && '' !== trim( $email ) ? trim( $email ) : null;
	}

	protected function action() : string {
		return 'signup';
	}
}
