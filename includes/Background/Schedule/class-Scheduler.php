<?php
/**
 * Scheduler class file.
 *
 * Central recurring task manager for Smart License Server.
 * Environment-agnostic — the runner (WP cron, system cron, CLI)
 * simply calls run_due_tasks() and the scheduler handles the rest.
 *
 * ## Registering tasks (at bootstrap or on demand)
 *
 *   // Closure
 *   smliser_scheduler()
 *       ->call( function() {
 *           smliser_db()->query( 'DELETE FROM ...' );
 *       })
 *       ->daily_at( '02:00' )
 *       ->label( 'Prune old records' )
 *       ->id( 'prune_old_records' );
 *
 *   // Static method
 *   smliser_scheduler()
 *       ->call( [MyClass::class, 'cleanup'] )
 *       ->every_hours( 4 )
 *       ->label( 'Cleanup' );
 *
 *   // Dispatch a queued job on a schedule
 *   smliser_scheduler()
 *       ->dispatch( PruneAnalyticsLogsJob::class, ['retention_days' => 90] )
 *       ->weekly_on( 'sunday', '03:00' )
 *       ->label( 'Weekly analytics prune' );
 *
 * ## Running due tasks (called by cron/CLI)
 *
 *   smliser_scheduler()->run_due_tasks();
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
use RuntimeException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Scheduler — manages and runs recurring scheduled tasks.
 *
 * Tasks are registered in memory at first access (lazy loaded).
 * Only execution state (last_ran_at, next_run_at) is persisted
 * via the Settings API — no new database table required.
 */
class Scheduler {

    /*
    |----------------------
    | SINGLETON
    |----------------------
    */

    /**
     * Singleton instance.
     *
     * @var static|null
     */
    protected static ?self $instance = null;

    /*
    |----------------------
    | DEPENDENCIES
    |----------------------
    */

    /**
     * The settings API for persisting task execution state.
     *
     * @var Settings
     */
    private Settings $settings;

    /*
    |----------------------
    | REGISTRY
    |----------------------
    */

    /**
     * Registered tasks keyed by task ID.
     *
     * @var array<string, ScheduledTask>
     */
    private array $tasks = [];

    /**
     * Settings key prefix for task state storage.
     *
     * @var string
     */
    const STATE_KEY_PREFIX = 'smliser_scheduler_task_';

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * Private constructor — use instance().
     *
     * @param Settings $settings The settings API instance.
     */
    private function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /*
    |----------------------
    | SINGLETON ACCESS
    |----------------------
    */

    /**
     * Return the singleton instance, creating it on first access.
     *
     * Tasks are lazy-loaded here — the scheduler is never instantiated
     * unless something actually calls it. This keeps the bootstrap cost
     * of every non-cron request at zero.
     *
     * @param Settings|null $settings Required on first call.
     * @return static
     * @throws RuntimeException If called before Settings is available.
     */
    public static function instance( ?Settings $settings = null ): static {
        if ( static::$instance === null ) {
            if ( $settings === null ) {
                throw new RuntimeException(
                    'Scheduler: Settings instance is required on first initialisation.'
                );
            }

            static::$instance = new static( $settings );
            static::$instance->load_core_tasks();
        }

        return static::$instance;
    }

    /*
    |--------------------------------------------
    | TASK REGISTRATION
    |--------------------------------------------
    */

    /**
     * Register a callable as a scheduled task.
     *
     * Returns the ScheduledTask so the fluent schedule methods
     * can be chained immediately:
     *
     *   smliser_scheduler()->call( $fn )->daily_at( '02:00' );
     *
     * @param callable $callable Any PHP callable.
     * @return ScheduledTask Fluent — chain schedule methods on the returned object.
     */
    public function call( callable $callable ): ScheduledTask {
        $task = new ScheduledTask( $callable );
        $this->register( $task );
        return $task;
    }

    /**
     * Register a queued job dispatch as a scheduled task.
     *
     * Shorthand for the common pattern of dispatching a JobDTO on a
     * recurring schedule. The job is pushed onto the queue each time
     * the task fires — the worker processes it asynchronously.
     *
     *   smliser_scheduler()
     *       ->dispatch( PruneAnalyticsLogsJob::class, ['retention_days' => 90] )
     *       ->weekly_on( 'sunday', '03:00' );
     *
     * @param string               $job_class Fully-qualified job handler class name.
     * @param array<string, mixed> $payload   Payload passed to the job handler.
     * @param string               $queue     Queue to dispatch onto. Default: 'low'.
     * @return ScheduledTask Fluent — chain schedule methods on the returned object.
     */
    public function dispatch(
        string $job_class,
        array  $payload = [],
        string $queue   = JobDTO::QUEUE_LOW
    ): ScheduledTask {
        $task = new ScheduledTask(
            function() use ( $job_class, $payload, $queue ) {
                smliser_job_queue()->dispatch(
                    JobDTO::make(
                        job_class : $job_class,
                        payload   : $payload,
                        queue     : $queue,
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
     * @param string $task_id
     * @return bool True if found and removed, false otherwise.
     */
    public function unregister( string $task_id ): bool {
        if ( ! isset( $this->tasks[ $task_id ] ) ) {
            return false;
        }

        unset( $this->tasks[ $task_id ] );
        return true;
    }

    /*
    |--------------------------------------------
    | RUNNER
    |--------------------------------------------
    */

    /**
     * Evaluate all registered tasks and run any that are due.
     *
     * This is the single entry point called by any runner — WP cron,
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
    |--------------------------------------------
    | INSPECTION API
    |--------------------------------------------
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
     *     last_ran_at:  DateTimeImmutable|null,
     *     next_run_at:  DateTimeImmutable|null,
     *     last_error:   string|null,
     * }
     */
    public function get_task_state( string $task_id ): array {
        $raw = $this->settings->get( static::STATE_KEY_PREFIX . $task_id, null, true );

        if ( empty( $raw ) || ! is_array( $raw ) ) {
            return [
                'last_ran_at' => null,
                'next_run_at' => null,
                'last_error'  => null,
            ];
        }

        return [
            'last_ran_at' => ! empty( $raw['last_ran_at'] )
                ? new DateTimeImmutable( $raw['last_ran_at'] )
                : null,
            'next_run_at' => ! empty( $raw['next_run_at'] )
                ? new DateTimeImmutable( $raw['next_run_at'] )
                : null,
            'last_error'  => $raw['last_error'] ?? null,
        ];
    }

    /**
     * Return all registered tasks with their current execution state.
     *
     * Useful for building an admin dashboard showing task health.
     *
     * @return array<string, array{task: ScheduledTask, state: array}>
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
    |--------------------------------------------
    | STATE PERSISTENCE
    |--------------------------------------------
    */

    /**
     * Record a successful task execution and compute the next run time.
     *
     * @param string        $task_id
     * @param ScheduledTask $task
     * @return void
     */
    private function record_task_ran( string $task_id, ScheduledTask $task ): void {
        $now      = new DateTimeImmutable();
        $next_run = $task->compute_next_run( $now );

        $this->settings->set( static::STATE_KEY_PREFIX . $task_id, [
            'last_ran_at' => $now->format( 'Y-m-d H:i:s' ),
            'next_run_at' => $next_run->format( 'Y-m-d H:i:s' ),
            'last_error'  => null,
        ], true );
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
    private function record_task_failed( string $task_id, string $error_message ): void {
        $existing = $this->settings->get( static::STATE_KEY_PREFIX . $task_id, [], true );

        $this->settings->set( static::STATE_KEY_PREFIX . $task_id, array_merge(
            is_array( $existing ) ? $existing : [],
            [ 'last_error' => $error_message ]
        ), true );
    }

    /*
    |--------------------------------------------
    | CORE TASKS
    |--------------------------------------------
    */

    /**
     * Register all built-in scheduled tasks.
     *
     * Called once by instance() on first access — never at bootstrap.
     * Additional tasks can be registered afterward via call() or dispatch().
     *
     * @return void
     */
    protected function load_core_tasks(): void {
        // Prune raw analytics log entries weekly.
        $this->dispatch(
            \SmartLicenseServer\Background\Jobs\Analytics\PruneAnalyticsLogsJob::class,
            [ 'retention_days' => 90 ]
        )
        ->weekly_on( 'sunday', '03:00' )
        ->label( 'Prune Analytics Logs' )
        ->id( 'prune_analytics_logs' );

        // Prune license activity log entries nightly.
        $this->dispatch(
            \SmartLicenseServer\Background\Jobs\Analytics\PruneLicenseActivityLogsJob::class,
            [ 'retention_months' => 3 ]
        )
        ->daily_at( '02:00' )
        ->label( 'Prune License Activity Logs' )
        ->id( 'prune_license_activity_logs' );

        // Release stale running jobs every 15 minutes.
        $this->call( function() {
            smliser_job_queue()->release_stale_running_jobs();
        })
        ->every_minutes( 15 )
        ->label( 'Release Stale Running Jobs' )
        ->id( 'release_stale_running_jobs' );

        // Purge completed jobs older than 7 days, nightly.
        $this->call( function() {
            smliser_job_queue()->purge_completed_jobs( 7 );
        })
        ->daily_at( '01:00' )
        ->label( 'Purge Completed Jobs' )
        ->id( 'purge_completed_jobs' );

        // Mark licenses past their end_date as expired and notify licensees.
        $this->dispatch(
            \SmartLicenseServer\Background\Jobs\Licenses\ExpireLicensesJob::class,
            [ 'batch_size' => 100 ]
        )
        ->daily_at( '00:30' )
        ->label( 'Expire Licenses' )
        ->id( 'expire_licenses' );

        // Send 7-day expiry reminder to licensees.
        $this->dispatch(
            \SmartLicenseServer\Background\Jobs\Licenses\NotifyExpiringLicensesJob::class,
            [ 'days_before' => 7, 'batch_size' => 100 ]
        )
        ->daily_at( '08:00' )
        ->label( 'License Expiry Reminder — 7 Days' )
        ->id( 'notify_expiring_licenses_7d' );

        // Send 3-day expiry reminder to licensees.
        $this->dispatch(
            \SmartLicenseServer\Background\Jobs\Licenses\NotifyExpiringLicensesJob::class,
            [ 'days_before' => 3, 'batch_size' => 100 ]
        )
        ->daily_at( '08:00' )
        ->label( 'License Expiry Reminder — 3 Days' )
        ->id( 'notify_expiring_licenses_3d' );

        // Prune orphaned license meta rows weekly.
        $this->dispatch(
            \SmartLicenseServer\Background\Jobs\Licenses\PruneLicenseMetaJob::class,
            []
        )
        ->weekly_on( 'sunday', '04:00' )
        ->label( 'Prune License Meta' )
        ->id( 'prune_license_meta' );

        // Clean expired download tokens every 4 hours.
        $this->dispatch(
            \SmartLicenseServer\Background\Jobs\Monetization\CleanExpiredTokensJob::class,
            []
        )
        ->every_hours( 4 )
        ->label( 'Clean Expired Download Tokens' )
        ->id( 'clean_expired_tokens' );
    }
}