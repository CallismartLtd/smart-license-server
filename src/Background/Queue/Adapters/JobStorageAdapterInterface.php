<?php
/**
 * Job storage adapter interface file.
 *
 * @package SmartLicenseServer\Background
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Queue\Adapters;

use SmartLicenseServer\Background\Queue\JobDTO;

/**
 * Contract for all job queue storage backends.
 * 
 * Provides CRUD API for background job operations.
 */
interface JobStorageAdapterInterface {

    /*
    |------------------
    | Write operations
    |------------------
    */

    /**
     * Persist a new job to the queue.
     *
     * The adapter assigns an ID to the job and returns the same DTO
     * with the ID populated. The incoming DTO must not be mutated —
     * return a new/updated instance.
     *
     * @param JobDTO $job The job envelope to persist.
     * @return JobDTO     The persisted envelope with its storage ID set.
     * @throws \RuntimeException On storage failure.
     */
    public function enqueue( JobDTO $job ): JobDTO;

    /**
     * Retrieve and atomically claim the next available job from the queue.
     *
     * "Claim" means immediately marking the job as 'running' in storage
     * so that concurrent workers cannot pick up the same job.
     *
     * Returns null when the queue is empty or no job is currently eligible
     * (e.g. all pending jobs have a future available_at).
     *
     * @param string|null $queue Restrict to a specific queue, or null for any queue
     *                           processed in priority order: critical → default → low.
     * @return JobDTO|null       The claimed job envelope, or null if queue is empty.
     */
    public function dequeue( ?string $queue = null ): ?JobDTO;

    /**
     * Persist updated state for an existing job.
     *
     * Called by the worker to record status transitions, increment
     * attempts, store results, or capture error messages.
     *
     * @param JobDTO $job The job envelope with updated fields.
     * @return JobDTO     The updated envelope as reflected in storage.
     * @throws \RuntimeException If the job has no ID or the update fails.
     */
    public function update_job( JobDTO $job ): JobDTO;

    /**
     * Permanently remove a job from the queue.
     *
     * Intended for completed jobs or manual admin purges.
     * Does not archive — use archive_failed_job() for failures.
     *
     * @param JobDTO $job The job envelope to remove.
     * @return bool       True if removed, false if not found or on failure.
     */
    public function remove_job( JobDTO $job ): bool;

    /**
     * Move a failed job to the failed jobs archive and remove it from the queue.
     *
     * Archiving preserves the full envelope for inspection/replay without
     * cluttering the active queue table.
     *
     * @param JobDTO $job The failed job envelope.
     * @return bool       True on success, false on failure.
     */
    public function archive_failed_job( JobDTO $job ): bool;

    /*
    |-----------------------
    | Single-record lookups
    |-----------------------
    */

    /**
     * Retrieve a single job by its storage ID.
     *
     * @param int $id The storage-assigned job identifier.
     * @return JobDTO|null The job envelope, or null if not found.
     */
    public function get_job_by_id( int $id ): ?JobDTO;

    /*
    |--------------------
    | Listing operations
    |--------------------
    */

    /**
     * Retrieve a page of jobs matching the given status.
     *
     * @param int         $page   1-indexed page number. Values below 1 are treated as 1.
     * @param int         $limit  Maximum number of records per page.
     * @param string      $status One of the JobDTO::STATUS_* constants.
     * @param string|null $queue  Optionally restrict to a specific queue.
     * @return JobDTO[]           Array of matching job envelopes, oldest first.
     */
    public function get_jobs_by_status( int $page, int $limit, string $status, ?string $queue = null ): array;

    /**
     * Retrieve a page of jobs with optional, independently-combinable filters.
     *
     * Unlike get_jobs_by_status(), status is optional here — this is
     * the general-purpose listing method for admin/CLI views that need
     * to browse the queue without fixing a status up front.
     *
     * @param int         $page      1-indexed page number. Values below 1 are treated as 1.
     * @param int         $limit     Maximum number of records per page.
     * @param string|null $queue     Optionally restrict to a specific queue.
     * @param string|null $status    Optionally restrict to a specific status.
     * @param string|null $job_class Optionally restrict to a specific job class.
     * @return JobDTO[]              Array of matching job envelopes, oldest first.
     */
    public function get_jobs( int $page, int $limit, ?string $queue = null, ?string $status = null, ?string $job_class = null ): array;

    /**
     * Retrieve a page of archived failed jobs, optionally filtered by queue or job class.
     *
     * Failed jobs live in a separate archive table with a narrower shape
     * than JobDTO (no status/priority/attempts lifecycle fields), so
     * implementations return plain associative arrays rather than JobDTO.
     *
     * @param int         $page      1-indexed page number. Values below 1 are treated as 1.
     * @param int         $limit     Maximum number of records per page.
     * @param string|null $queue     Optionally restrict to a specific queue.
     * @param string|null $job_class Optionally restrict to a specific job class.
     * @return array<int, array<string, mixed>> Matching failed-job records, most recently failed first.
     */
    public function get_failed_jobs( int $page, int $limit, ?string $queue = null, ?string $job_class = null ): array;

    /*
    |---------------------
    | Counting operations
    |---------------------
    */

    /**
     * Count jobs matching the given status, optionally filtered by queue.
     *
     * @param string      $status One of the JobDTO::STATUS_* constants.
     * @param string|null $queue  Optionally restrict to a specific queue.
     * @return int                Number of matching jobs.
     */
    public function count_jobs_by_status( string $status, ?string $queue = null ): int;

    /**
     * Count jobs matching the same optional filters as get_jobs().
     *
     * @param string|null $queue     Optionally restrict to a specific queue.
     * @param string|null $status    Optionally restrict to a specific status.
     * @param string|null $job_class Optionally restrict to a specific job class.
     * @return int                   Number of matching jobs.
     */
    public function count_jobs( ?string $queue = null, ?string $status = null, ?string $job_class = null ): int;

    /**
     * Count archived failed jobs matching the same optional filters as get_failed_jobs().
     *
     * @param string|null $queue     Optionally restrict to a specific queue.
     * @param string|null $job_class Optionally restrict to a specific job class.
     * @return int                   Number of matching failed jobs.
     */
    public function count_failed_jobs( ?string $queue = null, ?string $job_class = null ): int;

    /*
    |-----------------------
    | Maintenance operations
    |-----------------------
    */

    /**
     * Release jobs that have been in 'running' state longer than the
     * given timeout — indicating the worker that claimed them died.
     *
     * Released jobs are returned to 'pending' status so they can be
     * picked up by the next available worker.
     *
     * @param int $timeout_seconds Jobs running longer than this are released. Default 300.
     * @return int                 Number of jobs released.
     */
    public function release_stale_running_jobs( int $timeout_seconds = 300 ): int;

    /**
     * Purge all completed jobs older than the given number of days.
     *
     * Keeps the queue table lean. Completed jobs carry no actionable
     * information once retained for the configured period.
     *
     * @param int $older_than_days Jobs completed more than this many days ago are removed.
     * @return int                 Number of jobs purged.
     */
    public function purge_completed_jobs( int $older_than_days = 7 ): int;

    /**
     * Purge archived failed jobs older than the given number of days.
     *
     * Failed jobs are an audit trail, not disposable state like
     * completed jobs — implementations should use a longer default
     * retention than purge_completed_jobs() and this should always
     * be an explicit, deliberate action.
     *
     * @param int $older_than_days Failed jobs archived more than this many days ago are removed.
     * @return int                 Number of failed jobs purged.
     */
    public function purge_failed_jobs( int $older_than_days = 30 ): int;
    
    /*
    |----------
    | Identity
    |----------
    */

    /**
     * Return a unique identifier for this storage backend.
     *
     * Example: 'mysql', 'sqlite', 'redis'.
     *
     * @return string
     */
    public function get_adapter_id(): string;
}