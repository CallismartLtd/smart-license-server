<?php
/**
 * The update management class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Background\Jobs\Updates\ApplyUpdateJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Update\UpdateException;
use SmartLicenseServer\Environments\Application\Update\Updater;
use SmartLicenseServer\Environments\Application\Update\UpdateService;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Handles the update page's actions (admin/json/update/*).
 *
 * Installing and rolling back never run in the web request: they are
 * queued for ApplyUpdateJob, which runs the console command in a separate
 * process. A web request must not replace the code it is running, and a
 * closed tab or a PHP-FPM timeout must not be able to stop an update
 * halfway. Checking and dry runs change nothing on the live site, so they
 * run here.
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
		protected Updater $updater,
		protected JobQueue $job_queue,
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
		@set_time_limit( self::DRY_RUN_TIME_LIMIT );
		ignore_user_abort( true );

		$result = $this->updates->dry_run();

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
				: sprintf( 'Version %s is downloaded, verified and ready to install.', $result['version'] )
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

		if ( $busy = $this->busy( $overview ) ) {
			return $busy;
		}

		if ( ! empty( $overview['blockers'] ) ) {
			return $this->fail( implode( ' ', $overview['blockers'] ), 409 );
		}

		$check = $overview['check'];

		if ( ! $reinstall && ! $check['available'] ) {
			return $this->fail( sprintf( '%s %s is up to date. Check for updates first, or reinstall.', \SMLISER_APP_NAME, \SMLISER_VER ), 409 );
		}

		$version = $reinstall ? \SMLISER_VER : (string) $check['latest'];

		$this->dispatch( array( 'action' => 'install', 'reinstall' => $reinstall ) );
		$this->updates->mark_queued( $version, 'admin' );

		return $this->ok( sprintf( 'Version %s will be installed by the queue worker shortly. This page follows its progress.', $version ) );
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

		$overview = $this->updates->overview();

		if ( $busy = $this->busy( $overview ) ) {
			return $busy;
		}

		$backup = $overview['backup'];

		if ( null === $backup ) {
			return $this->fail( 'There is no backup to restore.', 409 );
		}

		$this->dispatch( array( 'action' => 'rollback' ) );
		$this->updates->mark_queued( $backup['version'], 'admin' );

		return $this->ok( sprintf( 'Version %s will be restored by the queue worker shortly.', $backup['version'] ) );
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
	 * Refuse while an update is queued or running.
	 *
	 * @param array $overview UpdateService::overview().
	 * @return Response|null
	 */
	private function busy( array $overview ) : ?Response {
		if ( $overview['in_progress'] ) {
			return $this->fail( 'An update is being installed.', 409 );
		}

		if ( null !== $overview['queued'] ) {
			return $this->fail( sprintf( 'An update to %s is already queued.', $overview['queued']['version'] ?? '' ), 409 );
		}

		return null;
	}

	/**
	 * Queue the update job.
	 *
	 * @param array $payload ApplyUpdateJob payload.
	 * @return void
	 */
	private function dispatch( array $payload ) : void {
		$this->job_queue->dispatch(
			JobDTO::make(
				job_class : ApplyUpdateJob::class,
				payload   : $payload,
				queue     : JobDTO::QUEUE_LOW,
			)
		);
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