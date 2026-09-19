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

/**
 * Contract that every queue worker must fulfil.
 *
 * A worker is the runtime agent responsible for claiming jobs
 * from the queue, resolving their handlers, executing them, and
 * reporting the outcome back to the JobQueue manager.
 */
interface WorkerInterface {

    /**
     * Attach an optional logger callback for external output streaming.
     *
     * @param callable|null $logger A callable that accepts a single string message.
     * @return void
     */
    public function set_logger( ?callable $logger ): void;

    /**
     * Attach an external stop condition callback.
     *
     * Useful for bridging CLI signal handlers or custom termination logic.
     *
     * @param callable|null $checker A callable returning boolean (true if execution should stop).
     * @return void
     */
    public function set_stop_checker( ?callable $checker ): void;

    /**
     * Request the worker loop to shut down gracefully.
     *
     * Triggers the worker to finish the currently executing job and
     * then break out of its processing loop.
     *
     * @return void
     */
    public function request_stop(): void;

    /**
     * Determine whether worker execution should cease.
     *
     * Evaluates both internal termination requests and any attached
     * external stop checkers.
     *
     * @return bool True if the worker should halt, false otherwise.
     */
    public function should_stop(): bool;

    /**
     * Claim and process the next available job from the queue.
     *
     * Implementations must:
     *   1. Call JobQueue::claim_next_job() to atomically claim a job.
     *   2. Obtain the handler class via JobDTO::get_job_class().
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
     * condition is reached (memory limit, max jobs, timeout, or signal).
     *
     * Intended for long-running CLI workers. Web-triggered workers
     * should use process_next_job() or process_within_time_budget() directly.
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