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
 * Performs a cheap pre-flight check only: it asks whether the account
 * and/or source associated with this request are already known to be
 * under attack, based on events recorded by a prior AuthenticationHandler
 * run. It does not itself record anything — it has no way to know the
 * outcome of a request that hasn't been authenticated yet — and it does
 * not compute severity or a retry-after value, since AuthenticationMonitor
 * exposes neither pre-flight.
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
	 * Blocks the request outright if the account or source is already
	 * flagged from prior attempts. Does not record this request either
	 * way — that happens downstream, once the outcome is known.
	 */
	public function handle( Request $request, callable $next ): mixed {

		$account = $this->account_from_request( $request );
		$source  = $this->source_from_request( $request );

		if (
			( null !== $account && $this->monitor->is_account_under_attack( $account ) )
			|| $this->monitor->is_source_suspicious( $source )
		) {
			return $this->blocked_response();
		}

		return $next( $request );
	}

	/**
	 * Extract the account identifier this request is authenticating as,
	 * if one is present at this stage (e.g. a username/email field on a
	 * login request). Return null when the request doesn't carry one.
	 *
	 * NOTE: adapt this to Request's actual accessor methods — I haven't
	 * seen Request's API, so this is illustrative only.
	 *
	 * @param Request $request
	 *
	 * @return string|null
	 */
	abstract protected function account_from_request( Request $request ): ?string;

	/**
	 * Extract the source identifier (normally an IP) for this request.
	 *
	 * NOTE: adapt this to Request's actual accessor methods.
	 *
	 * @param Request $request
	 *
	 * @return string
	 */
	abstract protected function source_from_request( Request $request ): string;

	/**
	 * Create a response for a blocked request.
	 *
	 * @return Response
	 */
	protected function blocked_response(): Response {

		return Response::make(
			'Authentication temporarily blocked.',
			429
		);
	}
}