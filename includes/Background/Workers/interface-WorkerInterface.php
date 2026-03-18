<?php
/**
 * Worker interface file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Workers
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Workers;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Contract that every queue worker must fulfil.
 *
 * A worker is the runtime agent responsible for claiming jobs
 * from the queue, resolving their handlers, executing them, and
 * reporting the outcome back to the JobQueue manager.
 *
 * Workers are intentionally decoupled from storage — they never
 * touch the database directly. All persistence goes through
 * JobQueue, which in turn delegates to the storage adapter.
 *
 * ## Invocation
 *
 * A worker can be triggered by anything that can run PHP:
 *
 *   // Via cron (recommended for production):
 *   php artisan smliser:work
 *
 *   // Via a web hook (useful for shared hosting):
 *   GET /smliser-worker?token=secret
 *
 *   // Programmatically (e.g. after a request completes):
 *   smliser_queue_worker()->process_next_job();
 */
interface WorkerInterface {

    /**
     * Claim and process the next available job from the queue.
     *
     * Implementations must:
     *   1. Call JobQueue::claim_next_job() to atomically claim a job.
     *   2. Resolve the handler via JobDTO::resolve_handler().
     *   3. Call JobHandlerInterface::handle() with the job payload.
     *   4. Report the outcome via JobQueue::record_job_completed()
     *      or JobQueue::record_job_failed().
     *
     * Returns true if a job was found and processed (regardless of
     * whether it succeeded or failed), false if the queue was empty.
     *
     * @param string|null $queue Restrict to a specific queue, or null
     *                           for the default priority order.
     * @return bool True if a job was processed, false if queue was empty.
     */
    public function process_next_job( ?string $queue = null ): bool;

    /**
     * Continuously process jobs until the queue is empty or a stop
     * condition is reached (memory limit, max jobs, timeout).
     *
     * Intended for long-running CLI workers. Web-triggered workers
     * should use process_next_job() directly to avoid timeout issues.
     *
     * @param string|null $queue Restrict to a specific queue, or null
     *                           for the default priority order.
     * @return int Total number of jobs processed in this run.
     */
    public function start_processing( ?string $queue = null ): int;
    /**
     * Process jobs until the time budget is exhausted.
     *
     * Designed for WordPress cron and web-triggered workers where
     * execution time is constrained. Stops cleanly before the budget
     * runs out — never mid-job.
     *
     * @param int         $time_budget_seconds Max seconds to spend processing. Default 25.
     * @param string|null $queue               Restrict to a specific queue, or null
     *                                         for the default priority order.
     * @return int Total number of jobs processed within the budget.
     */
    public function process_within_time_budget( int $time_budget_seconds = 25, ?string $queue = null ): int;
}