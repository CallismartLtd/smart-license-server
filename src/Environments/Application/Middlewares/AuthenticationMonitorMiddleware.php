<?php
/**
 * Authentication monitoring middleware class file.
 *
 * @author Callistus Nwachukwu
 */
declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Authentication\AuthenticationMonitor;

/**
 * Base middleware for authentication abuse monitoring.
 *
 * Login failures are recorded by AuthManagement after the identity provider
 * returns its result. Signup and password-reset requests use separate
 * request budgets because they do not represent failed login attempts.
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
	 * Applies the relevant pre-flight policy before the action handler.
	 */
	public function handle( Request $request, callable $next ) : mixed {

		$account = $this->account_from_request( $request );
		$source  = $this->source_from_request( $request );

		try {
			$allowed = 'login' === $this->action()
				? ( ( null === $account || ! $this->monitor->is_account_under_attack( $account ) ) && ! $this->monitor->is_source_suspicious( $source ) )
				: $this->monitor->allow_action_attempt( $this->action(), $account, $source );
		} catch ( \Throwable $e ) {
			// Authentication remains available if the optional monitoring cache is down.
			\smliser_log_error( sprintf( '[Authentication Monitor] Could not check %s request: %s', $this->action(), $e->getMessage() ) );
			$allowed = true;
		}

		if ( ! $allowed ) {
			return $this->blocked_response();
		}

		return $next( $request );
	}

	/**
	 * Extract the account identifier from the submitted form, if present.
	 *
	 * @param Request $request
	 *
	 * @return string|null
	 */
	abstract protected function account_from_request( Request $request ) : ?string;

	/**
	 * Use the server's direct peer address. Forwarded headers are not trusted
	 * here because Request::ip() accepts them without a trusted-proxy check.
	 *
	 * @param Request $request
	 * @return string
	 */
	protected function source_from_request( Request $request ) : string {
		$remote = (string) $request->server( 'REMOTE_ADDR', '' );

		return false !== filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unresolved_ip';
	}

	/**
	 * The monitored auth action: login, signup, or forgot_password.
	 *
	 * @return string
	 */
	abstract protected function action() : string;

	/**
	 * Create a response for a blocked request.
	 *
	 * @return Response
	 */
	protected function blocked_response() : Response {

		return Response::json(
			[ 'success' => false, 'code' => 'too_many_attempts', 'message' => 'Too many attempts. Please wait and try again.' ],
			429,
			[ 'Retry-After' => (string) AuthenticationMonitor::WINDOW_SECONDS ]
		);
	}
}
