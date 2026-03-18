<?php
/**
 * Job queue manager class file.
 *
 * Central background job API for Smart License Server.
 * Wraps the active job storage adapter and exposes a clean,
 * self-documenting dispatch and management interface.
 *
 * Basic usage — dispatch a job:
 *
 *   smliser_job_queue()->dispatch(
 *       JobDTO::make(
 *           job_class : SendLicenseExpiryEmailJob::class,
 *           payload   : [
 *               'license_id' => 42,
 *               'recipient'  => 'user@example.com',
 *               'days_left'  => 7,
 *           ],
 *       )
 *   );
 *
 * Dispatch on a specific queue:
 *
 *   smliser_job_queue()->dispatch(
 *       JobDTO::make(
 *           job_class : GenerateMonthlyReportJob::class,
 *           payload   : ['month' => '2025-01'],
 *           queue     : JobDTO::QUEUE_LOW,
 *       )
 *   );
 *
 * Dispatch with a delay (seconds):
 *
 *   smliser_job_queue()->dispatch(
 *       JobDTO::make(
 *           job_class : SendWelcomeEmailJob::class,
 *           payload   : ['user_id' => 10],
 *           delay     : 300, // available in 5 minutes
 *       )
 *   );
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Queue
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Queue;

use SmartLicenseServer\Background\Queue\Adapters\JobStorageAdapterInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Job queue manager.
 *
 * Acts as a proxy to the active job storage adapter, providing a
 * clean public API for dispatching, inspecting, and maintaining
 * the background job queue.
 *
 * Follows the same singleton + adapter proxy pattern as Cache,
 * Database, and Mailer — one instance per runtime, constructed
 * with a concrete adapter in Config::setGlobalQueueAdapter().
 *
 * @method JobDTO          dispatch( JobDTO $job )                                                  Enqueue a job for background processing.
 * @method JobDTO|null     claim_next_job( ?string $queue = null )                                  Claim and return the next available job.
 * @method JobDTO          record_job_completed( JobDTO $job, mixed $result = null )                Mark a running job as completed.
 * @method JobDTO          record_job_failed( JobDTO $job, string $error_message )                  Mark a running job as failed and handle retry/archive logic.
 * @method JobDTO|null     find_job( int $id )                                                      Retrieve a job by its storage ID.
 * @method JobDTO[]        get_jobs_by_status( string $status, ?string $queue, int $limit, int $offset ) Retrieve jobs filtered by status.
 * @method int             count_jobs_by_status( string $status, ?string $queue = null )            Count jobs by status.
 * @method bool            remove_job( JobDTO $job )                                                Remove a job from the queue.
 * @method int             release_stale_running_jobs( int $timeout_seconds = 300 )                 Release jobs stuck in running state.
 * @method int             purge_completed_jobs( int $older_than_days = 7 )                         Purge old completed jobs.
 * @method string          get_adapter_id()                                                         Return the active adapter identifier.
 */
class JobQueue {

    /**
     * The active job storage adapter.
     *
     * @var JobStorageAdapterInterface
     */
    protected JobStorageAdapterInterface $adapter;

    /**
     * Constructor.
     *
     * @param JobStorageAdapterInterface $adapter The storage adapter instance.
     */
    public function __construct( JobStorageAdapterInterface $adapter ) {
        $this->adapter = $adapter;
    }

    /*
    |--------------------
    | DISPATCH
    |--------------------
    */

    /**
     * Enqueue a job for background processing.
     *
     * The returned DTO has its storage ID populated — store it
     * if you need to track or inspect the job later.
     *
     * @param JobDTO $job The job envelope to dispatch.
     * @return JobDTO     The persisted envelope with its ID set.
     * @throws \RuntimeException On storage failure.
     */
    public function dispatch( JobDTO $job ): JobDTO {
        return $this->adapter->enqueue( $job );
    }

    /*
    |--------------------------------------------
    | WORKER-FACING API
    |
    | These methods are called by workers, not application code.
    | Application code dispatches; workers claim, complete, fail.
    |--------------------------------------------
    */

    /**
     * Claim and return the next available job from the queue.
     *
     * Atomically marks the job as 'running' so concurrent workers
     * cannot claim the same envelope. Returns null when the queue
     * is empty or no job is currently eligible.
     *
     * @param string|null $queue Restrict to a specific queue, or null
     *                           to process in priority order: critical → default → low.
     * @return JobDTO|null
     */
    public function claim_next_job( ?string $queue = null ): ?JobDTO {
        return $this->adapter->dequeue( $queue );
    }

    /**
     * Mark a running job as successfully completed.
     *
     * Stores the handler's return value on the DTO, sets status to
     * 'completed', records completed_at, and persists the update.
     *
     * @param JobDTO $job    The job that was just handled.
     * @param mixed  $result The return value from JobHandlerInterface::handle().
     * @return JobDTO        The updated envelope.
     */
    public function record_job_completed( JobDTO $job, mixed $result = null ): JobDTO {
        $job = $job
            ->set( JobDTO::KEY_STATUS,       JobDTO::STATUS_COMPLETED )
            ->set( JobDTO::KEY_RESULT,        $result )
            ->set( JobDTO::KEY_COMPLETED_AT,  new \DateTimeImmutable() )
            ->set( JobDTO::KEY_ERROR_MESSAGE, null );

        return $this->adapter->update_job( $job );
    }

    /**
     * Mark a running job as failed and handle retry or archive logic.
     *
     * If the job has not exceeded max_attempts, status is set to
     * 'retrying' so the next worker cycle picks it up again.
     *
     * If max_attempts is exhausted, the job is archived to the
     * failed jobs table and removed from the active queue.
     *
     * @param JobDTO $job           The job that failed.
     * @param string $error_message The error or exception message.
     * @return JobDTO               The updated envelope.
     */
    public function record_job_failed( JobDTO $job, string $error_message ): JobDTO {
        $job = $job->set( JobDTO::KEY_ERROR_MESSAGE, $error_message );

        if ( $job->has_exceeded_max_attempts() ) {
            $job = $job->set( JobDTO::KEY_STATUS, JobDTO::STATUS_FAILED );
            $this->adapter->archive_failed_job( $job );
            return $job;
        }

        $job = $job->set( JobDTO::KEY_STATUS, JobDTO::STATUS_RETRYING );
        return $this->adapter->update_job( $job );
    }

    /*
    |--------------------
    | INSPECTION
    |--------------------
    */

    /**
     * Retrieve a single job by its storage ID.
     *
     * @param int $id
     * @return JobDTO|null
     */
    public function find_job( int $id ): ?JobDTO {
        return $this->adapter->get_job_by_id( $id );
    }

    /**
     * Retrieve jobs filtered by status, optionally restricted to a queue.
     *
     * @param string      $status One of the JobDTO::STATUS_* constants.
     * @param string|null $queue  Optionally restrict to a specific queue.
     * @param int         $limit  Maximum number of records to return. Default 50.
     * @param int         $offset Pagination offset. Default 0.
     * @return JobDTO[]
     */
    public function get_jobs_by_status(
        string  $status,
        ?string $queue  = null,
        int     $limit  = 50,
        int     $offset = 0
    ): array {
        return $this->adapter->get_jobs_by_status( $status, $queue, $limit, $offset );
    }

    /**
     * Count jobs matching the given status, optionally filtered by queue.
     *
     * @param string      $status One of the JobDTO::STATUS_* constants.
     * @param string|null $queue  Optionally restrict to a specific queue.
     * @return int
     */
    public function count_jobs_by_status( string $status, ?string $queue = null ): int {
        return $this->adapter->count_jobs_by_status( $status, $queue );
    }

    /**
     * Remove a job from the queue permanently.
     *
     * For failed jobs, prefer archive_failed_job() via record_job_failed()
     * so the failure is preserved for inspection. Use this only for
     * completed jobs or manual admin purges.
     *
     * @param JobDTO $job
     * @return bool
     */
    public function remove_job( JobDTO $job ): bool {
        return $this->adapter->remove_job( $job );
    }

    /*
    |--------------------
    | MAINTENANCE
    |--------------------
    */

    /**
     * Release jobs that have been stuck in 'running' beyond the timeout.
     *
     * Should be called periodically by the scheduler or a cron hook
     * to recover from worker crashes without manual intervention.
     *
     * @param int $timeout_seconds Default 300 (5 minutes).
     * @return int Number of jobs released.
     */
    public function release_stale_running_jobs( int $timeout_seconds = 300 ): int {
        return $this->adapter->release_stale_running_jobs( $timeout_seconds );
    }

    /**
     * Purge completed jobs older than the given number of days.
     *
     * Should be called periodically by the scheduler to keep the
     * jobs table lean without manual intervention.
     *
     * @param int $older_than_days Default 7.
     * @return int Number of jobs purged.
     */
    public function purge_completed_jobs( int $older_than_days = 7 ): int {
        return $this->adapter->purge_completed_jobs( $older_than_days );
    }

    /**
     * Return the active storage adapter identifier.
     *
     * @return string e.g. 'database', 'redis'
     */
    public function get_adapter_id(): string {
        return $this->adapter->get_adapter_id();
    }
}