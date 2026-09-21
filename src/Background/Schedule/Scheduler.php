<?php
/**
 * Scheduler class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Schedule
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Schedule;

use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\SettingsAPI\Settings;
use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Background\Jobs\Analytics\PruneAnalyticsLogsJob;
use SmartLicenseServer\Background\Jobs\Analytics\PruneLicenseActivityLogsJob;
use SmartLicenseServer\Background\Queue\JobQueue;

/**
 * Manages and runs recurring scheduled tasks.
 *
 * Tasks are registered in memory at first access (lazy loaded).
 * Only execution state (last_ran_at, next_run_at) is persisted
 * via the Settings API - no new database table required.
 *
 * All task execution state is stored in a single Settings API
 * entry and is synchronized against the currently registered
 * tasks. This prevents orphaned state when a task is removed.
 */
class Scheduler {

	/**
	 * Registered tasks keyed by task ID.
	 *
	 * @var array<string, ScheduledTask>
	 */
	private array $tasks = [];

	/**
	 * Settings key used for all scheduler execution state.
	 *
	 * @var string
	 */
	protected const STATE_KEY = 'smliser_scheduler_state';

	/*
	|----------------------
	| CONSTRUCTOR
	|----------------------
	*/

	/**
	 * Class constructor.
	 *
	 * @param Settings     $settings      The settings API instance.
	 * @param JobQueue     $queue         The job queue instance.
	 * @param CoreSchedules $core_schedules Core schedule callbacks.
	 */
	public function __construct(
		protected Settings $settings,
		protected JobQueue $queue,
		protected CoreSchedules $core_schedules
	) {
		$this->load_core_tasks();
		$this->synchronize_state();
	}

	/*
	|---------------------
	| TASK REGISTRATION
	|---------------------
	*/

	/**
	 * Register a callable as a scheduled task.
	 *
	 * Returns the ScheduledTask so the fluent schedule methods
	 * can be chained immediately:
	 *
	 *   $this->call( 'schedule_id', $fn )->daily_at( '02:00' );
	 *
	 * @param string   $id       The Schedule ID.
	 * @param callable $callable Any PHP callable.
	 * @return ScheduledTask Fluent - chain schedule methods on the returned object.
	 */
	public function call( string $id, callable $callable ): ScheduledTask {
		$task = new ScheduledTask( $id, $callable );
		$this->register( $task );

		return $task;
	}

	/**
	 * Register a queued job dispatch as a scheduled task.
	 *
	 * Shorthand for the common pattern of dispatching a JobDTO on a
	 * recurring schedule. The job is pushed onto the queue each time
	 * the task fires - the worker processes it asynchronously.
	 *
	 *   $this
	 *       ->dispatch( PruneAnalyticsLogsJob::class, ['retention_days' => 90] )
	 *       ->weekly_on( 'sunday', '03:00' );
	 *
	 * @param string               $job_class Fully-qualified job handler class name.
	 * @param array<string, mixed> $payload   Payload passed to the job handler.
	 * @param string               $queue     Queue to dispatch onto. Default: 'low'.
	 * @return ScheduledTask Fluent - chain schedule methods on the returned object.
	 */
	public function dispatch(
		string $job_class,
		array $payload = [],
		string $queue = JobDTO::QUEUE_LOW
	): ScheduledTask {
		$task = new ScheduledTask(
			$job_class,
			function() use ( $job_class, $payload, $queue ) {
				$this->queue->dispatch(
					JobDTO::make(
						job_class: $job_class,
						payload: $payload,
						queue: $queue,
					)
				);
			}
		);

		$this->register( $task );

		return $task;
	}

	/**
	 * Register a ScheduledTask instance directly.
	 *
	 * Replaces any previously registered task with the same ID.
	 *
	 * @param ScheduledTask $task
	 * @return void
	 */
	public function register( ScheduledTask $task ): void {
		$this->tasks[ $task->get_id() ] = $task;
	}

	/**
	 * Unregister a task by its ID.
	 *
	 * The task's persisted execution state is removed as well.
	 *
	 * @param string $task_id
	 * @return bool True if found and removed, false otherwise.
	 */
	public function unregister( string $task_id ): bool {
		if ( ! isset( $this->tasks[ $task_id ] ) ) {
			return false;
		}

		unset( $this->tasks[ $task_id ] );

		$this->remove_task_state( $task_id );

		return true;
	}

	/*
	|----------
	| RUNNER
	|----------
	*/

	/**
	 * Evaluate all registered tasks and run any that are due.
	 *
	 * This is the single entry point called by any runner -
	 * system crontab, CLI script, or web hook. The runner provides
	 * the tick; the scheduler decides what fires.
	 *
	 * Each due task is executed, its last_ran_at is updated, and any
	 * exception is caught and recorded so one failing task never
	 * prevents the others from running.
	 *
	 * @return array<string, bool> Map of task_id => success for tasks that ran.
	 */
	public function run_due_tasks(): array {
		$results = [];

		foreach ( $this->tasks as $id => $task ) {
			$state = $this->get_task_state( $id );

			if ( ! $task->is_due( $state['last_ran_at'] ) ) {
				continue;
			}

			try {
				$task->execute();
				$this->record_task_ran( $id, $task );
				$results[ $id ] = true;
			} catch ( \Throwable $e ) {
				$this->record_task_failed( $id, $e->getMessage() );
				$results[ $id ] = false;
			}
		}

		return $results;
	}

	/*
	|--------------------
	| INSPECTION API
	|--------------------
	*/

	/**
	 * Return all registered tasks.
	 *
	 * @return array<string, ScheduledTask>
	 */
	public function get_tasks(): array {
		return $this->tasks;
	}

	/**
	 * Return a registered task by ID.
	 *
	 * @param string $task_id
	 * @return ScheduledTask|null
	 */
	public function get_task( string $task_id ): ?ScheduledTask {
		return $this->tasks[ $task_id ] ?? null;
	}

	/**
	 * Whether a task is registered.
	 *
	 * @param string $task_id
	 * @return bool
	 */
	public function has_task( string $task_id ): bool {
		return isset( $this->tasks[ $task_id ] );
	}

	/**
	 * Return all tasks that are currently due.
	 *
	 * @return array<string, ScheduledTask>
	 */
	public function get_due_tasks(): array {
		$due = [];

		foreach ( $this->tasks as $id => $task ) {
			$state = $this->get_task_state( $id );

			if ( $task->is_due( $state['last_ran_at'] ) ) {
				$due[ $id ] = $task;
			}
		}

		return $due;
	}

	/**
	 * Return the persisted execution state for a task.
	 *
	 * @param string $task_id
	 * @return array{
	 *     last_ran_at: DateTimeImmutable|null,
	 *     next_run_at: DateTimeImmutable|null,
	 *     last_error:  string|null,
	 * }
	 */
	public function get_task_state( string $task_id ): array {
		$state = $this->get_persisted_state();

		$raw = $state[ $task_id ] ?? null;

		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return [
				'last_ran_at' => null,
				'next_run_at' => null,
				'last_error'  => null,
			];
		}

		return [
			'last_ran_at' => ! empty( $raw['last_ran_at'] )
				? new DateTimeImmutable( $raw['last_ran_at'], new DateTimeZone( 'UTC' ) )
				: null,

			'next_run_at' => ! empty( $raw['next_run_at'] )
				? new DateTimeImmutable( $raw['next_run_at'], new DateTimeZone( 'UTC' ) )
				: null,

			'last_error' => $raw['last_error'] ?? null,
		];
	}

	/**
	 * Return all registered tasks with their current execution state.
	 *
	 * Useful for building an admin dashboard showing task health.
	 *
	 * @return array<string, array{
	 *     task: ScheduledTask,
	 *     state: array{
	 *         last_ran_at: DateTimeImmutable|null,
	 *         next_run_at: DateTimeImmutable|null,
	 *         last_error: string|null
	 *     }
	 * }>
	 */
	public function get_tasks_with_state(): array {
		$result = [];

		foreach ( $this->tasks as $id => $task ) {
			$result[ $id ] = [
				'task'  => $task,
				'state' => $this->get_task_state( $id ),
			];
		}

		return $result;
	}

	/*
	|------------------------
	| STATE PERSISTENCE
	|------------------------
	*/

	/**
	 * Return all persisted scheduler execution state.
	 *
	 * @return array<string, array{
	 *     last_ran_at?: string|null,
	 *     next_run_at?: string|null,
	 *     last_error?: string|null
	 * }>
	 */
	protected function get_persisted_state(): array {
		$state = $this->settings->get( static::STATE_KEY, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * Persist all scheduler execution state.
	 *
	 * @param array<string, array<string, mixed>> $state
	 * @return void
	 */
	protected function set_persisted_state( array $state ): void {
		$this->settings->set( static::STATE_KEY, $state );
	}

	/**
	 * Synchronize persisted execution state with registered tasks.
	 *
	 * State belonging to tasks that are no longer registered is removed.
	 * Registered tasks that have never executed do not receive an empty
	 * state entry.
	 *
	 * @return void
	 */
	protected function synchronize_state(): void {
		$state = $this->get_persisted_state();

		foreach ( array_keys( $state ) as $task_id ) {
			if ( ! isset( $this->tasks[ $task_id ] ) ) {
				unset( $state[ $task_id ] );
			}
		}

		$this->set_persisted_state( $state );
	}

	/**
	 * Remove the persisted execution state for a task.
	 *
	 * @param string $task_id
	 * @return void
	 */
	protected function remove_task_state( string $task_id ): void {
		$state = $this->get_persisted_state();

		if ( ! array_key_exists( $task_id, $state ) ) {
			return;
		}

		unset( $state[ $task_id ] );

		$this->set_persisted_state( $state );
	}

	/**
	 * Record a successful task execution and compute the next run time.
	 *
	 * @param string        $task_id
	 * @param ScheduledTask $task
	 * @return void
	 */
	public function record_task_ran( string $task_id, ScheduledTask $task ): void {
		$now        = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$next_run   = $task->compute_next_run( $now );

		$state = $this->get_persisted_state();

		$state[ $task_id ] = [
			'last_ran_at' => $now->format( 'Y-m-d H:i:s' ),
			'next_run_at' => $next_run->format( 'Y-m-d H:i:s' ),
			'last_error'  => null,
		];

		$this->set_persisted_state( $state );
	}

	/**
	 * Record a failed task execution.
	 *
	 * Preserves the last_ran_at and next_run_at from the previous
	 * successful run so the task remains scheduled for retry.
	 *
	 * @param string $task_id
	 * @param string $error_message
	 * @return void
	 */
	public function record_task_failed( string $task_id, string $error_message ): void {
		$state = $this->get_persisted_state();

		$existing = $state[ $task_id ] ?? [];

		$state[ $task_id ] = array_merge(
			is_array( $existing ) ? $existing : [],
			[
				'last_error' => $error_message,
			]
		);

		$this->set_persisted_state( $state );
	}

	/*
	|----------------
	| CORE TASKS
	|----------------
	*/

	/**
	 * Register all built-in scheduled tasks.
	 *
	 * Called once by instance() on first access - never at bootstrap.
	 * Additional tasks can be registered afterward via call() or dispatch().
	 *
	 * @return void
	 */
	protected function load_core_tasks(): void {
		// Prune raw analytics log entries weekly.
		$this->call(
			'prune_analytics_logs',
			[ $this->core_schedules, 'pruneAnalyticsJob' ]
		)
			->weekly_on( 'sunday', '03:00' )
			->label( PruneAnalyticsLogsJob::get_job_name() );

		// Prune license activity log entries nightly.
		$this->call(
			'prune_license_activity_logs',
			[ $this->core_schedules, 'pruneLicenseActivityJob' ]
		)
			->daily_at( '02:00' )
			->label( PruneLicenseActivityLogsJob::get_job_name() );

		// Mark licenses past their end_date as expired and notify licensees.
		$this->call(
			'expired_licenses_job',
			[ $this->core_schedules, 'markExpiredLicenses' ]
		)
			->daily_at( '00:30' )
			->label( 'Expire Licenses' );

		// Send 7-day expiry reminder to licensees.
		$this->call(
			'notify_expiring_licenses_7d',
			[ $this->core_schedules, 'sendExpiringLicense7Days' ]
		)
			->daily_at( '08:00' )
			->label( 'License Expiry Reminder - 7 Days' );

		// Send 3-day expiry reminder to licensees.
		$this->call(
			'notify_expiring_licenses_3d',
			[ $this->core_schedules, 'sendExpiringLicense3Days' ]
		)
			->daily_at( '08:00' )
			->label( 'License Expiry Reminder - 3 Days' );

		// Prune orphaned license meta rows weekly.
		$this->call(
			'prune_license_meta',
			[ $this->core_schedules, 'pruneOphanedLicenseMeta' ]
		)
			->weekly_on( 'sunday', '04:00' )
			->label( 'Prune License Meta' );

		// Clean expired download tokens every 4 hours.
		$this->call(
			'clean_expired_tokens',
			[ $this->core_schedules, 'pruneExpiredDownloadtoken' ]
		)
			->every_hours( 4 )
			->label( 'Clean Expired Download Tokens' );

		// Permanently delete trashed apps older than 30 days - weekly.
		$this->call(
			'clean_trashed_apps',
			[ $this->core_schedules, 'deleteTrashedApps' ]
		)
			->weekly_on( 'sunday', '05:00' )
			->label( 'Clean Trashed Apps' );

		// Release stale running jobs every 15 minutes.
		$this->call(
			'release_stale_running_jobs',
			[ $this->core_schedules, 'releaseStaleJobs' ]
		)
			->every_minutes( 15 )
			->label( 'Release Stale Running Jobs' );

		// Purge completed jobs older than 7 days, nightly.
		$this->call(
			'purge_completed_jobs',
			[ $this->core_schedules, 'purgeCompletedJobs' ]
		)
			->daily_at( '01:00' )
			->label( 'Purge Completed Jobs' );
	}
}