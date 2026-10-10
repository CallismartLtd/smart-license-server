<?php
/**
 * Background runner class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Background
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Background;

use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\CodeChangeDetector;
use SmartLicenseServer\Background\Workers\QueueWorker;
use Throwable;

/**
 * Runs the background work that is due: scheduled tasks, then a slice of
 * the queue. Shared by every periodic trigger, so they all behave alike:
 *
 *  - `smliser cron`, from crontab or a hosting panel's cron jobs;
 *  - the cron URL, called by an external service such as cron-job.org.
 *
 * Only due work runs: a trigger can make it happen sooner, never more
 * often or differently. On top of that:
 *
 *  - one run at a time (a non-blocking lock; a second trigger returns at once);
 *  - for the public cron URL, at most one run per MIN_URL_INTERVAL;
 *  - nothing while the application is being updated (CodeChangeDetector);
 *  - the queue stops within a time budget, never mid-job.
 *
 * Each run records a heartbeat (BackgroundStatus) for the configuration
 * page and site health.
 */
final class BackgroundRunner {

	/**
	 * Results.
	 */
	public const RAN       = 'ran';
	public const BUSY      = 'busy';       // Another run holds the lock.
	public const THROTTLED = 'throttled';  // The cron URL was called again too soon.
	public const UPDATING  = 'updating';   // An update is in progress or not finished.

	/**
	 * Minimum seconds between two runs started by the public cron URL.
	 *
	 * @var int
	 */
	public const MIN_URL_INTERVAL = 55;

	/**
	 * Queue time budget for a console run, in seconds (cron starts one a minute).
	 *
	 * @var int
	 */
	private const CLI_QUEUE_BUDGET = 45;

	/**
	 * Queue time budget for a web run, in seconds.
	 *
	 * @var int
	 */
	private const WEB_QUEUE_BUDGET = 25;

	/**
	 * Constructor.
	 *
	 * @param Scheduler          $scheduler    Scheduled tasks.
	 * @param QueueWorker        $worker       Processes queued jobs.
	 * @param CodeChangeDetector $code_changes Pauses everything during updates.
	 * @param BackgroundStatus   $status       Heartbeats.
	 * @param string             $lock_file    Absolute path of the run lock.
	 */
	public function __construct(
		private Scheduler $scheduler,
		private QueueWorker $worker,
		private CodeChangeDetector $code_changes,
		private BackgroundStatus $status,
		private string $lock_file
	) {}

	/**
	 * Run the due scheduled tasks, then queued jobs for the time budget.
	 *
	 * @param string               $source One of BackgroundStatus::SOURCE_*.
	 * @param array<string, mixed> $meta   Extra heartbeat data (e.g. how the client was released).
	 * @param int|null             $budget Queue seconds; defaults by SAPI.
	 * @param callable|null        $log    fn( string $line ): void for progress lines.
	 * @return array{result: string, tasks?: int, failed?: int, jobs?: int, seconds?: float}
	 */
	public function run( string $source, array $meta = array(), ?int $budget = null, ?callable $log = null ) : array {
		$lock = @fopen( $this->lock_file, 'c' );

		if ( false === $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( false !== $lock ) {
				fclose( $lock );
			}

			return array( 'result' => self::BUSY );
		}

		try {
			if ( BackgroundStatus::SOURCE_CRON_URL === $source && $this->ran_recently() ) {
				return array( 'result' => self::THROTTLED );
			}

			if ( null !== $this->code_changes->reason() ) {
				$this->status->record( $source, $meta + array( 'skipped' => 'updating' ) );

				return array( 'result' => self::UPDATING );
			}

			return $this->run_locked( $source, $meta, $budget ?? ( \is_cli() ? self::CLI_QUEUE_BUDGET : self::WEB_QUEUE_BUDGET ), $log );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/**
	 * The run itself, under the lock.
	 *
	 * @param string        $source
	 * @param array         $meta
	 * @param int           $budget
	 * @param callable|null $log
	 * @return array
	 */
	private function run_locked( string $source, array $meta, int $budget, ?callable $log ) : array {
		$started = microtime( true );
		$tasks   = 0;
		$failed  = 0;

		foreach ( array_keys( $this->scheduler->get_due_tasks() ) as $id ) {
			// An update may start while tasks run; leave the rest due.
			if ( null !== $this->code_changes->reason() ) {
				break;
			}

			try {
				$error = $this->scheduler->run_task( (string) $id );
			} catch ( Throwable $e ) {
				$error = $e->getMessage();
			}

			++$tasks;

			if ( null !== $error ) {
				++$failed;
			}

			$log && $log( sprintf( 'Task %s %s%s', $id, null === $error ? 'ran' : 'failed', null === $error ? '' : ': ' . $error ) );
		}

		$jobs = 0;

		$this->worker->set_logger( $log );
		$this->worker->set_stop_checker( fn() : bool => null !== $this->code_changes->reason() );

		try {
			$jobs = $this->worker->process_within_time_budget( max( 1, $budget ), null );
		} catch ( Throwable $e ) {
			$log && $log( 'The queue stopped: ' . $e->getMessage() );
		} finally {
			$this->worker->set_logger( null );
			$this->worker->set_stop_checker( null );
		}

		$summary = array(
			'tasks'   => $tasks,
			'failed'  => $failed,
			'jobs'    => $jobs,
			'seconds' => round( microtime( true ) - $started, 2 ),
		);

		$this->status->record( $source, $meta + $summary );

		return array( 'result' => self::RAN ) + $summary;
	}

	/**
	 * Whether the schedules ran within MIN_URL_INTERVAL, from any trigger.
	 *
	 * The queue worker's heartbeat does not count: it does not run the
	 * schedules, so a busy worker must not hold the cron URL back.
	 *
	 * @return bool
	 */
	private function ran_recently() : bool {
		foreach ( array( BackgroundStatus::SOURCE_CRON_URL, BackgroundStatus::SOURCE_CLI_CRON, BackgroundStatus::SOURCE_SCHEDULE_RUN ) as $source ) {
			$age = $this->status->age( $source );

			if ( null !== $age && $age < self::MIN_URL_INTERVAL ) {
				return true;
			}
		}

		return false;
	}
}