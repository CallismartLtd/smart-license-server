<?php
/**
 * Queue worker class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Workers
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Workers;

use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Queue worker.
 *
 * Responsible for claiming jobs from the queue, resolving their
 * handlers, executing them, and reporting outcomes back to JobQueue.
 *
 * The worker is intentionally thin — it knows how to run a job
 * but nothing about how jobs are stored. All persistence is
 * delegated to the injected JobQueue instance.
 *
 * ## Single job (web hook / post-request):
 *
 *   smliser_queue_worker()->process_next_job();
 *
 * ## Continuous processing (CLI / cron):
 *
 *   smliser_queue_worker()->start_processing();
 *
 * ## Specific queue:
 *
 *   smliser_queue_worker()->process_next_job( JobDTO::QUEUE_CRITICAL );
 */
class QueueWorker implements WorkerInterface {

    /*
    |----------------------
    | CONFIGURATION
    |----------------------
    */

    /**
     * Maximum number of jobs to process in one start_processing() run.
     * 0 means unlimited — run until the queue is empty.
     *
     * @var int
     */
    private int $max_jobs;

    /**
     * Memory limit in megabytes. Worker exits start_processing() if
     * current usage exceeds this ceiling to avoid OOM crashes.
     *
     * @var int
     */
    private int $memory_limit_mb;

    /**
     * Seconds to sleep between polling cycles when the queue is empty.
     *
     * @var int
     */
    private int $sleep_seconds;

    /*
    |----------------------
    | DEPENDENCIES
    |----------------------
    */

    /**
     * The job queue manager.
     *
     * @var JobQueue
     */
    private JobQueue $queue;

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * Constructor.
     *
     * @param JobQueue $queue           The bootstrapped job queue manager.
     * @param int      $max_jobs        Max jobs per run. 0 = unlimited. Default 0.
     * @param int      $memory_limit_mb Memory ceiling in MB. Default 128.
     * @param int      $sleep_seconds   Seconds to sleep when queue is empty. Default 5.
     */
    public function __construct(
        JobQueue $queue,
        int      $max_jobs        = 0,
        int      $memory_limit_mb = 128,
        int      $sleep_seconds   = 5
    ) {
        $this->queue           = $queue;
        $this->max_jobs        = $max_jobs;
        $this->memory_limit_mb = $memory_limit_mb;
        $this->sleep_seconds   = $sleep_seconds;
    }

    /*
    |----------------------
    | WorkerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Claims the next available job, resolves its handler, calls
     * handle() with the payload, and records the outcome on the
     * JobQueue. All exceptions thrown by the handler are caught —
     * the worker never dies because a single job failed.
     */
    public function process_next_job( ?string $queue = null ): bool {
        $job = $this->queue->claim_next_job( $queue );

        if ( $job === null ) {
            return false;
        }

        $this->execute( $job );

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Runs a continuous processing loop, polling the queue and
     * sleeping when it is empty. Exits when:
     *   - The queue is empty and $max_jobs > 0 and the limit is reached.
     *   - Memory usage exceeds $memory_limit_mb.
     *   - $max_jobs is reached.
     */
    public function start_processing( ?string $queue = null ): int {
        $processed = 0;

        while ( true ) {
            if ( $this->has_exceeded_memory_limit() ) {
                break;
            }

            if ( $this->max_jobs > 0 && $processed >= $this->max_jobs ) {
                break;
            }

            $found = $this->process_next_job( $queue );

            if ( $found ) {
                $processed++;
                continue;
            }

            // Queue was empty — if max_jobs is set we are done,
            // otherwise sleep and poll again.
            if ( $this->max_jobs > 0 ) {
                break;
            }

            sleep( $this->sleep_seconds );
        }

        return $processed;
    }

    /*
    |----------------------
    | EXECUTION
    |----------------------
    */

    /**
     * Execute a single claimed job and record its outcome.
     *
     * Resolves the handler class from the JobDTO, calls handle()
     * with the payload, and delegates success/failure recording
     * to the JobQueue manager.
     *
     * All Throwables are caught so a bad job never kills the worker.
     *
     * @param JobDTO $job The claimed job envelope.
     * @return void
     */
    private function execute( JobDTO $job ): void {
        try {
            $handler = $job->resolve_handler();
            $result  = $handler->handle( $job->get( JobDTO::KEY_PAYLOAD ) );
            $this->queue->record_job_completed( $job, $result );
        } catch ( \Throwable $e ) {
            $this->queue->record_job_failed( $job, $this->format_error( $e ) );
        }
    }

    /*
    |----------------------
    | HELPERS
    |----------------------
    */

    /**
     * Whether current memory usage has exceeded the configured limit.
     *
     * @return bool
     */
    private function has_exceeded_memory_limit(): bool {
        $used_mb = memory_get_usage( true ) / 1024 / 1024;
        return $used_mb >= $this->memory_limit_mb;
    }

    /**
     * Format a Throwable into a concise error string for storage.
     *
     * @param \Throwable $e
     * @return string
     */
    private function format_error( \Throwable $e ): string {
        return sprintf(
            '%s: %s in %s on line %d',
            get_class( $e ),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
    }
}