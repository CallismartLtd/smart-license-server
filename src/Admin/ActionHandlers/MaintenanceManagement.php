<?php
/**
 * Maintenance management action handler class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Admin\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Boot\BootModeResolver;
use SmartLicenseServer\Environments\Application\Maintenance\MaintenanceBypass;
use SmartLicenseServer\Environments\Application\Maintenance\MaintenanceMode;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Admin actions of the Tools > Maintenance page (admin/json/maintenance/*).
 *
 * System administrators only. Switching maintenance on (or making a new
 * bypass link) also gives the administrator's browser the bypass cookie, so
 * they keep using the site while visitors get the maintenance page; the
 * link is returned once, to open in another browser or share.
 *
 * Response: { success, data: { message, bypass_url? } }
 */
class MaintenanceManagement {

	/**
	 * Constructor.
	 *
	 * @param MaintenanceMode $maintenance Switches maintenance mode.
	 * @param Guard           $guard       The signed-in principal.
	 */
	public function __construct(
		protected MaintenanceMode $maintenance,
		protected Guard $guard
	) {}

	/**
	 * POST maintenance/enable
	 *
	 * Fields: message (optional), retry_after (seconds, optional),
	 * keep_background ("1" keeps background work running).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function enable( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		$message     = $request->get( 'message', '' );
		$retry_after = $request->get( 'retry_after', BootModeResolver::DEFAULT_RETRY_AFTER );
		$keep        = '1' === (string) $request->get( 'keep_background', '' );

		if ( ! \is_string( $message ) ) {
			return $this->fail( 'The visitor message must be text.', 400 );
		}

		if ( '' === (string) $retry_after ) {
			$retry_after = BootModeResolver::DEFAULT_RETRY_AFTER;
		}

		if ( ! \is_scalar( $retry_after ) || ! \ctype_digit( (string) $retry_after ) ) {
			return $this->fail( 'Retry after must be a whole number of seconds.', 400 );
		}

		try {
			$token = $this->maintenance->enable( $message, (int) $retry_after, ! $keep, $this->operator() );
		} catch ( \RuntimeException $e ) {
			return $this->fail( $e->getMessage(), 409 );
		}

		return $this->with_bypass(
			$token,
			\sprintf( 'Maintenance mode is on. Visitors get the maintenance page; this browser keeps access.%s', $keep ? '' : ' Background work is paused.' )
		);
	}

	/**
	 * POST maintenance/disable
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function disable( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		try {
			$was_on = $this->maintenance->disable();
		} catch ( \RuntimeException $e ) {
			return $this->fail( $e->getMessage(), 409 );
		}

		$response = $this->ok( $was_on ? 'Maintenance mode is off; the site is open.' : 'Maintenance mode was already off.' );
		$response->set_header( 'Set-Cookie', MaintenanceBypass::expired_cookie( \url()->is_ssl() ) );

		return $response;
	}

	/**
	 * POST maintenance/bypass
	 *
	 * Make a new bypass link; earlier links and cookies stop working, except
	 * this browser's, which gets the new one.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function renew_bypass( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		try {
			$token = $this->maintenance->renew_bypass();
		} catch ( \RuntimeException $e ) {
			return $this->fail( $e->getMessage(), 409 );
		}

		return $this->with_bypass( $token, 'New bypass link made; earlier links stop working.' );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * A success response carrying the bypass link, and the cookie for this browser.
	 *
	 * @param string $token
	 * @param string $message
	 * @return Response
	 */
	private function with_bypass( string $token, string $message ) : Response {
		$response = $this->ok( $message, [ 'bypass_url' => $this->maintenance->bypass_url( $token ) ] );
		$response->set_header( 'Set-Cookie', MaintenanceBypass::cookie( $token, \url()->is_ssl() ) );

		return $response;
	}

	/**
	 * Who switches maintenance on, for the status.
	 *
	 * @return string
	 */
	private function operator() : string {
		$principal = $this->guard->get_principal();

		return null === $principal ? 'admin' : \sprintf( 'admin (%s)', $principal->get_display_name() );
	}

	/**
	 * Refuse anyone but a system administrator.
	 *
	 * @return Response|null
	 */
	private function denied() : ?Response {
		$principal = $this->guard->get_principal();

		if ( null === $principal || ! $principal->is( 'system_admin' ) ) {
			return $this->fail( 'Only system administrators can switch maintenance mode.', 403 );
		}

		return null;
	}

	/**
	 * A success response.
	 *
	 * @param string               $message
	 * @param array<string, mixed> $data
	 * @return Response
	 */
	private function ok( string $message, array $data = [] ) : Response {
		return Response::json( data: [ 'success' => true, 'data' => [ 'message' => $message ] + $data ], status_code: 200 );
	}

	/**
	 * A failure response.
	 *
	 * @param string $message
	 * @param int    $status
	 * @return Response
	 */
	private function fail( string $message, int $status ) : Response {
		return Response::json( data: [ 'success' => false, 'data' => [ 'message' => $message ] ], status_code: $status );
	}
}