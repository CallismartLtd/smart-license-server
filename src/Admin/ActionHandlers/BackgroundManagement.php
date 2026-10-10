<?php
/**
 * The background management class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Background\Jobs\Updates\ApplyUpdateJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\CodeChangeDetector;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\PhpRuntime;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Runs scheduled tasks and queued jobs from the admin Tools pages
 * (admin/json/background/*).
 *
 * For sites where cron or the queue worker is not running yet, or to run
 * something now instead of waiting. Everything here runs inside the web
 * request, so:
 *
 *  - nothing runs while the application is being updated, or while an
 *    update waits for its finish step (see CodeChangeDetector);
 *  - the queue is not processed while an update job is queued: it runs
 *    the console command in a new PHP process, which a web request cannot
 *    start reliably (under PHP-FPM, PHP_BINARY is not the CLI binary), and
 *    a web request must never replace the code it is running;
 *  - queue processing stops within a time budget, never mid-job.
 *
 * Every action is for system administrators only.
 *
 * Responses: { success: true, data: { message, results? } } or
 * { success: false, data: { message } } with a 4xx status.
 */
class BackgroundManagement {

	/**
	 * Seconds of jobs processed per "Process queue" request.
	 *
	 * @var int
	 */
	private const QUEUE_BUDGET = 20;

	/**
	 * PHP time limit for a request that runs tasks or jobs.
	 *
	 * @var int
	 */
	private const TIME_LIMIT = 120;

	/**
	 * Most worker log lines returned to the page.
	 *
	 * @var int
	 */
	private const LOG_LIMIT = 50;

	public function __construct(
		protected Scheduler $scheduler,
		protected JobQueue $job_queue,
		protected QueueWorker $worker,
		protected CodeChangeDetector $code_changes,
		protected Guard $guard
	) {}

	/*
	|-----------
	| Schedules
	|-----------
	*/

	/**
	 * POST background/schedule/run
	 *
	 * Request: task_id=<id> runs that task now, due or not; without it,
	 * every due task runs.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function run_schedule( Request $request ) : Response {
		if ( $refused = $this->refused() ) {
			return $refused;
		}

		$task_id = (string) $request->get( 'task_id', '' );

		if ( '' !== $task_id && ! $this->scheduler->has_task( $task_id ) ) {
			return $this->fail( sprintf( 'No scheduled task "%s" is registered.', $task_id ), 404 );
		}

		$task_ids = '' !== $task_id ? array( $task_id ) : array_keys( $this->scheduler->get_due_tasks() );

		if ( empty( $task_ids ) ) {
			return $this->ok( 'No tasks are due.', array() );
		}

		$this->allow_time();

		$results = array();
		$stopped = false;

		foreach ( $task_ids as $id ) {
			// An update may start while tasks run; leave the rest due.
			if ( null !== $this->code_changes->reason() ) {
				$stopped = true;
				break;
			}

			$error     = $this->scheduler->run_task( $id );
			$results[] = array(
				'id'    => $id,
				'label' => $this->scheduler->get_task( $id )?->get_label() ?? $id,
				'ok'    => null === $error,
				'error' => $error,
			);
		}

		$failed = count( array_filter( $results, static fn( array $result ) : bool => ! $result['ok'] ) );
		$ran    = count( $results );

		$message = 1 === $ran && '' !== $task_id
			? ( 0 === $failed ? sprintf( '"%s" ran successfully.', $results[0]['label'] ) : sprintf( '"%s" failed: %s', $results[0]['label'], $results[0]['error'] ) )
			: sprintf( 'Ran %d task(s); %d failed.', $ran, $failed );

		if ( $stopped ) {
			$message .= ' An update started; the remaining tasks stay due.';
		}

		return 0 === $failed ? $this->ok( $message, $results ) : $this->fail( $message, 500, $results );
	}

	/*
	|--------
	| Queues
	|--------
	*/

	/**
	 * POST background/queue/process
	 *
	 * Processes queued jobs for up to QUEUE_BUDGET seconds.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function process_queue( Request $request ) : Response {
		if ( $refused = $this->refused() ) {
			return $refused;
		}

		if ( $this->update_job_queued() ) {
			return $this->fail( 'An update is queued. It can only be installed by the queue worker running from the console (`queue work`), so the queue is not processed from here until it has run.', 409 );
		}

		$this->allow_time();

		$log = array();

		$this->worker->set_logger(
			static function ( string $line ) use ( &$log ) : void {
				$log[] = $line;
			}
		);
		$this->worker->set_stop_checker( fn() : bool => null !== $this->code_changes->reason() );

		try {
			$processed = $this->worker->process_within_time_budget( self::QUEUE_BUDGET );
		} finally {
			$this->worker->set_logger( null );
			$this->worker->set_stop_checker( null );
		}

		$pending = $this->job_queue->count_jobs_by_status( 'pending' );

		return $this->ok(
			0 === $processed
				? 'No jobs were ready to run.'
				: sprintf( 'Processed %d job(s); %d still pending.', $processed, $pending ),
			array_slice( $log, -self::LOG_LIMIT )
		);
	}

	/**
	 * POST background/queue/release-stale
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function release_stale( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		$released = $this->job_queue->release_stale_running_jobs();

		return $this->ok( sprintf( 'Released %d stale job(s) back to the queue.', $released ) );
	}

	/**
	 * POST background/queue/purge
	 *
	 * Request: days=<n> (default 7).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function purge_completed( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		$days   = max( 0, (int) $request->get( 'days', 7 ) );
		$purged = $this->job_queue->purge_completed_jobs( $days );

		return $this->ok( sprintf( 'Purged %d completed job(s) older than %d day(s).', $purged, $days ) );
	}

	/**
	 * POST background/queue/purge-failed
	 *
	 * Request: days=<n> (default 30).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function purge_failed( Request $request ) : Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		$days   = max( 0, (int) $request->get( 'days', 30 ) );
		$purged = $this->job_queue->purge_failed_jobs( $days );

		return $this->ok( sprintf( 'Purged %d failed job record(s) older than %d day(s).', $purged, $days ) );
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
			return $this->fail( 'Only system administrators can run background tasks.', 403 );
		}

		return null;
	}

	/**
	 * Refuse a non-administrator, or any run while the code is being updated.
	 *
	 * @return Response|null
	 */
	private function refused() : ?Response {
		if ( $denied = $this->denied() ) {
			return $denied;
		}

		return match ( $this->code_changes->reason() ) {
			CodeChangeDetector::REASON_UPDATING => $this->fail( 'The application is being updated. Try again once the update has finished.', 409 ),
			CodeChangeDetector::REASON_CHANGED  => $this->fail( 'An update has not finished: the files are installed but the finish step has not run. Run `smliser update finish` (or roll back) first.', 409 ),
			default                             => null,
		};
	}

	/**
	 * Whether an update job is waiting in the queue.
	 *
	 * @return bool
	 */
	private function update_job_queued() : bool {
		foreach ( array( 'pending', JobDTO::STATUS_RETRYING ) as $status ) {
			if ( $this->job_queue->count_jobs( null, $status, ApplyUpdateJob::class ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Give a request that runs tasks or jobs room to finish them.
	 *
	 * @return void
	 */
	private function allow_time() : void {
		PhpRuntime::allow_long_work( self::TIME_LIMIT );
	}

	/**
	 * A success response.
	 *
	 * @param string     $message Message for the admin.
	 * @param array|null $results Per-task results or worker log lines.
	 * @return Response
	 */
	private function ok( string $message, ?array $results = null ) : Response {
		return Response::json(
			data: array(
				'success' => true,
				'data'    => array( 'message' => $message ) + ( null === $results ? array() : array( 'results' => $results ) ),
			),
			status_code: 200
		);
	}

	/**
	 * A failure response.
	 *
	 * @param string     $message What went wrong.
	 * @param int        $status  HTTP status.
	 * @param array|null $results Per-task results, when some ran.
	 * @return Response
	 */
	private function fail( string $message, int $status, ?array $results = null ) : Response {
		return Response::json(
			data: array(
				'success' => false,
				'data'    => array( 'message' => $message ) + ( null === $results ? array() : array( 'results' => $results ) ),
			),
			status_code: $status
		);
	}
}