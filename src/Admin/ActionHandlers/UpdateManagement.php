<?php
/**
 * The update management class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\PhpRuntime;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Update\UpdateException;
use SmartLicenseServer\Environments\Application\Update\Updater;
use SmartLicenseServer\Environments\Application\Update\UpdateRunner;
use SmartLicenseServer\Environments\Application\Update\UpdateService;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Handles the update page's actions (admin/json/update/*).
 *
 * Everything runs in the request, through UpdateRunner, the same update
 * API the console and the queue worker use. Installing swaps the files
 * in and finishes the update in a new PHP process when the server
 * allows; otherwise the page's next request (it reloads) boots on the new
 * code and finishes it. A closed tab cannot stop an update halfway
 * (ignore_user_abort), and the slow part (download, verify, extract) is
 * the dry run's, so after a dry run installing takes seconds.
 *
 * Every action is for system administrators only.
 *
 * Responses: { success: true, data: { message?, overview } } or
 * { success: false, data: { message } } with a 4xx status.
 */
class UpdateManagement {

	/**
	 * Seconds a dry run may take (it downloads the package).
	 *
	 * @var int
	 */
	private const DRY_RUN_TIME_LIMIT = 300;

	public function __construct(
		protected UpdateService $updates,
		protected UpdateRunner $runner,
		protected Updater $updater,
		protected Guard $guard
	) {}

	/**
	 * GET update/status
	 *
	 * Polled by the page while an update is queued or running.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function status( Request $request ) : Response {
		return $this->denied() ?? $this->ok();
	}

	/**
	 * POST update/check
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function check( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		$check = $this->updates->check( true );

		if ( null !== $check['error'] ) {
			return $this->fail( $check['error'], 502 );
		}

		return $this->ok(
			$check['available']
				? sprintf( 'Version %s is available.', $check['latest'] )
				: sprintf( '%s %s is up to date.', \SMLISER_APP_NAME, \SMLISER_VER )
		);
	}

	/**
	 * POST update/dry-run
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function dry_run( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		// Downloading may outlast the default limit; an interrupted dry run
		// leaves nothing on the live site, and the next attempt cleans up.
		$this->allow_time();

		try {
			$result = $this->runner->dry_run();
		} catch ( UpdateException $e ) {
			return $this->fail( $e->getMessage(), 409 );
		}

		if ( ! $result['ok'] ) {
			$problems = array_values( $result['blockers'] );

			if ( null !== $result['error'] ) {
				$problems[] = $result['error'];
			}

			return $this->fail( implode( ' ', $problems ), 409 );
		}

		return $this->ok(
			null === $result['version']
				? sprintf( '%s %s is up to date; nothing would be installed.', \SMLISER_APP_NAME, \SMLISER_VER )
				: sprintf(
					'Version %s is downloaded from %s, verified%s and ready to install.',
					$result['version'],
					$result['package']['source'] ?? 'the update server',
					empty( $result['package']['signed_by'] ) ? '' : sprintf( ' (signed by %s)', $result['package']['signed_by'] )
				)
		);
	}

	/**
	 * POST update/install
	 *
	 * Request: reinstall=1 to install the current version again.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function install( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		$reinstall = (bool) filter_var( $request->get( 'reinstall', false ), FILTER_VALIDATE_BOOLEAN );
		$overview  = $this->updates->overview();

		if ( $overview['in_progress'] ) {
			return $this->fail( 'An update is being installed.', 409 );
		}

		if ( ! $reinstall && ! $overview['check']['available'] ) {
			return $this->fail( sprintf( '%s %s is up to date. Check for updates first, or reinstall.', \SMLISER_APP_NAME, \SMLISER_VER ), 409 );
		}

		$this->allow_time();

		try {
			$result = $this->runner->install( null, $reinstall );
		} catch ( UpdateException $e ) {
			return $this->fail( $e->getMessage(), 409 );
		}

		$version = isset( $result['prepared'] ) ? $result['prepared']['manifest']->version : \SMLISER_VER;

		return match ( $result['status'] ) {
			UpdateRunner::UP_TO_DATE => $this->ok( sprintf( '%s %s is up to date.', \SMLISER_APP_NAME, \SMLISER_VER ) ),
			UpdateRunner::PENDING    => $this->ok( sprintf( 'Version %s is installed. It finishes as this page reloads.', $version ) ),
			default                  => $this->ok( sprintf( 'Version %s was installed.', $version ) ),
		};
	}

	/**
	 * POST update/rollback
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function rollback( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		if ( null === $this->updater->backup() ) {
			return $this->fail( 'There is no backup to restore.', 409 );
		}

		$this->allow_time();

		try {
			$version = $this->runner->rollback();
		} catch ( UpdateException $e ) {
			return $this->fail( $e->getMessage(), 409 );
		}

		return $this->ok( sprintf( 'Version %s was restored.', $version ) );
	}

	/**
	 * POST update/auto
	 *
	 * Request: mode=off|security|all.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function auto( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		try {
			$this->updates->set_auto_mode( (string) $request->get( 'mode', '' ) );
		} catch ( UpdateException $e ) {
			return $this->fail( $e->getMessage(), 400 );
		}

		return $this->ok( 'Automatic update setting saved.' );
	}

	/**
	 * POST update/backup-delete
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function delete_backup( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		try {
			$deleted = $this->updater->delete_backup();
		} catch ( UpdateException $e ) {
			return $this->fail( $e->getMessage(), 409 );
		}

		return $this->ok( $deleted ? 'The backup was deleted.' : 'There is no backup.' );
	}

	/*
	|---------
	| Helpers
	|---------
	*/

	/**
	 * Refuse anyone but a system administrator.
	 *
	 * @return Response|null The refusal, or null when allowed.
	 */
	private function denied() : ?Response {
		$principal = $this->guard->get_principal();

		if ( null === $principal || ! $principal->is( 'system_admin' ) ) {
			return $this->fail( 'Only system administrators can manage updates.', 403 );
		}

		return null;
	}

	/**
	 * Give a request that downloads or installs room to finish, even if the tab closes.
	 *
	 * @return void
	 */
	private function allow_time() : void {
		PhpRuntime::allow_long_work( self::DRY_RUN_TIME_LIMIT );
	}

	/**
	 * A success response with the page's current state.
	 *
	 * @param string|null $message Message for the admin.
	 * @return Response
	 */
	private function ok( ?string $message = null ) : Response {
		return Response::json(
			data: array(
				'success' => true,
				'data'    => array_filter( array( 'message' => $message ) ) + array( 'overview' => $this->updates->overview() ),
			),
			status_code: 200
		);
	}

	/**
	 * A failure response.
	 *
	 * @param string $message What went wrong.
	 * @param int    $status  HTTP status.
	 * @return Response
	 */
	private function fail( string $message, int $status ) : Response {
		return Response::json(
			data: array( 'success' => false, 'data' => array( 'message' => $message ) ),
			status_code: $status
		);
	}
}