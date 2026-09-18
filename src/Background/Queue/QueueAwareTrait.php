<?php
/**
 * Queue aware trait file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Queue
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Queue;

/**
 * Queue-aware trait.
 */
trait QueueAwareTrait {
    protected JobQueue $job_queue;

    /**
     * Dispatch a job onto the default queue.
     *
     * @param string               $job_class Fully-qualified handler class name.
     * @param array<string, mixed> $payload   Argument array for the handler.
     * @return JobDTO                         The persisted envelope with its ID set.
     */
    protected function dispatch_job( string $job_class, array $payload = [] ): JobDTO {
        return $this->job_queue->dispatch(
            JobDTO::make( job_class: $job_class, payload: $payload )
        );
    }

    /**
     * Dispatch a job onto a specific queue.
     *
     * @param string               $job_class Fully-qualified handler class name.
     * @param array<string, mixed> $payload   Argument array for the handler.
     * @param string               $queue     One of the JobDTO::QUEUE_* constants.
     * @return JobDTO                         The persisted envelope with its ID set.
     */
    protected function dispatch_job_on_queue( string $job_class, array $payload = [], string $queue = JobDTO::QUEUE_DEFAULT ): JobDTO {
        return $this->job_queue->dispatch(
            JobDTO::make( job_class: $job_class, payload: $payload, queue: $queue )
        );
    }

    /**
     * Dispatch a job with a delay.
     *
     * @param string               $job_class Fully-qualified handler class name.
     * @param array<string, mixed> $payload   Argument array for the handler.
     * @param int                  $delay     Seconds before the job becomes available.
     * @return JobDTO                         The persisted envelope with its ID set.
     */
    protected function dispatch_job_delayed( string $job_class, array $payload = [], int $delay = 60 ): JobDTO {
        return $this->job_queue->dispatch(
            JobDTO::make( job_class: $job_class, payload: $payload, delay: $delay )
        );
    }
}