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
use SmartLicenseServer\Core\Container\Container;

/**
 * Decoupled, web-safe queue worker execution engine.
 */
class QueueWorker implements WorkerInterface {

    /**
     * Internal stop request flag.
     *
     * Set true by request_stop() (e.g. from a signal handler) and read
     * by should_stop(). Reset to false at the top of each run entry
     * point (start_processing() / process_within_time_budget()) — NOT
     * inside should_stop() itself, since should_stop() is also polled
     * mid-loop and must not silently un-stop a worker that's already
     * been told to shut down. Without that reset, a worker instance
     * that outlives a single run (e.g. reused across repeated `queue
     * work` invocations in one interactive CLI session) would carry a
     * stale "stop requested" flag into every subsequent run and exit
     * immediately, before processing anything.
     *
     * @var bool
     */
    protected bool $should_quit = false;

    /**
     * Optional external stop checker closure.
     *
     * @var callable|null
     */
    protected $stop_checker = null;

    /**
     * Optional logging callback for CLI streaming.
     *
     * @var callable|null
     */
    protected $logger = null;

    /**
     * Constructor.
     *
     * @param JobQueue  $queue            The bootstrapped job queue instance.
     * @param Container $container        The DI container used to resolve job handlers.
     * @param int       $max_jobs         Max jobs to process per run cycle. 0 = unlimited.
     * @param int       $memory_limit_mb  Memory limit ceiling in MB. Default 128.
     * @param int       $sleep_seconds    Seconds to wait when queue is idle. Default 5.
     */
    public function __construct(
        protected JobQueue $queue,
        protected Container $container,
        protected int      $max_jobs        = 0,
        protected int      $memory_limit_mb = 128,
        protected int      $sleep_seconds   = 5
    ) {}

    /**
     * Attach an optional logger callback for stdout or file logging.
     *
     * @param callable|null $logger
     * @return void
     */
    public function set_logger( ?callable $logger ): void {
        $this->logger = $logger;
    }

    /**
     * Attach an external stop condition callback.
     *
     * @param callable|null $checker Callback returning bool.
     * @return void
     */
    public function set_stop_checker( ?callable $checker ): void {
        $this->stop_checker = $checker;
    }

    /**
     * Flag the worker loop to request a graceful exit.
     *
     * @return void
     */
    public function request_stop(): void {
        $this->should_quit = true;
    }

    /**
     * Determine whether worker execution should cease.
     *
     * @return bool
     */
    public function should_stop(): bool {
        if ( $this->should_quit ) {
            return true;
        }

        if ( is_callable( $this->stop_checker ) && call_user_func( $this->stop_checker ) ) {
            return true;
        }

        return false;
    }

    /**
     * Claim and execute the next available job in the queue.
     *
     * @param string|null $queue Target queue channel name.
     * @return bool True if a job was found and processed, false otherwise.
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
     * Start continuous worker execution daemon loop.
     *
     * @param string|null $queue Target queue channel name.
     * @return int Total number of jobs processed.
     */
    public function start_processing( ?string $queue = null ): int {
        // Re-arm: a stop requested on a previous run of this same worker
        // instance must not carry over into this new one.
        $this->should_quit = false;

        $processed = 0;

        $this->log( 'Worker daemon initiated.' );

        while ( ! $this->should_stop() ) {
            if ( $this->has_exceeded_memory_limit() ) {
                $this->log( sprintf( 'Memory ceiling (%dMB) reached. Stopping worker.', $this->memory_limit_mb ) );
                break;
            }

            if ( $this->max_jobs > 0 && $processed >= $this->max_jobs ) {
                $this->log( sprintf( 'Maximum job limit (%d) reached. Stopping worker.', $this->max_jobs ) );
                break;
            }

            $found = $this->process_next_job( $queue );

            if ( $found ) {
                $processed++;
                continue;
            }

            if ( $this->max_jobs > 0 ) {
                break;
            }

            $this->smart_sleep( $this->sleep_seconds );
        }

        $this->log( sprintf( 'Worker process exited gracefully. Processed %d job(s).', $processed ) );

        return $processed;
    }

    /**
     * Process job queue within a restricted time window.
     *
     * @param int|null    $time_budget_seconds Execution budget in seconds.
     * @param string|null $queue               Target queue channel name.
     * @return int Total number of jobs processed.
     */
    public function process_within_time_budget( ?int $time_budget_seconds = null, ?string $queue = null ): int {
        // Re-arm: a stop requested on a previous run of this same worker
        // instance must not carry over into this new one.
        $this->should_quit = false;

        $budget     = $time_budget_seconds ?? $this->safe_time_budget_seconds();
        $start_time = microtime( true );
        $processed  = 0;

        while ( ! $this->should_stop() ) {
            if ( $this->has_exceeded_memory_limit() ) {
                break;
            }

            $elapsed = microtime( true ) - $start_time;
            if ( $elapsed >= ( $budget - 1 ) ) {
                break;
            }

            $found = $this->process_next_job( $queue );

            if ( ! $found ) {
                break;
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * Instantiate handler from DI container and process job payload.
     *
     * @param JobDTO $job
     * @return void
     */
    protected function execute( JobDTO $job ): void {
        $start_time = microtime( true );
        $job_class  = $job->get_job_class();

        $this->log( sprintf( 'Processing job [%s]...', $job_class ) );

        try {
            $handler = $this->container->get( $job_class );
            $result  = $handler->handle( $job->get( JobDTO::KEY_PAYLOAD ) );
            $this->queue->record_job_completed( $job, $result );

            $duration = round( microtime( true ) - $start_time, 4 );
            $this->log( sprintf( 'Job [%s] PROCESSED successfully (%fs).', $job_class, $duration ) );
        } catch ( \Throwable $e ) {
            $duration = round( microtime( true ) - $start_time, 4 );
            $this->log( sprintf( 'Job [%s] FAILED (%fs): %s', $job_class, $duration, $e->getMessage() ) );
            $this->queue->record_job_failed( $job, $this->format_error( $e ) );
        }
    }

    /**
     * Sleep in 1-second ticks to quickly respond to termination signals.
     *
     * @param int $seconds
     * @return void
     */
    protected function smart_sleep( int $seconds ): void {
        for ( $i = 0; $i < $seconds; $i++ ) {
            if ( $this->should_stop() ) {
                break;
            }
            sleep( 1 );
        }
    }

    /**
     * Check if allocated memory threshold has been reached.
     *
     * @return bool
     */
    protected function has_exceeded_memory_limit(): bool {
        $used_mb = memory_get_usage( true ) / 1024 / 1024;
        return $used_mb >= $this->memory_limit_mb;
    }

    /**
     * Calculate a safe timeout ceiling based on PHP configuration.
     *
     * @return int
     */
    protected function safe_time_budget_seconds(): int {
        $max = (int) ini_get( 'max_execution_time' );

        if ( $max <= 0 ) {
            return 100;
        }

        return max( 5, (int) ( $max * 0.8 ) );
    }

    /**
     * Format a Throwable exception into a readable error trail.
     *
     * @param \Throwable $e
     * @return string
     */
    protected function format_error( \Throwable $e ): string {
        return sprintf(
            '%s: %s in %s on line %d',
            get_class( $e ),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
    }

    /**
     * Send log line to custom logger callback if assigned.
     *
     * @param string $message
     * @return void
     */
    protected function log( string $message ): void {
        if ( is_callable( $this->logger ) ) {
            call_user_func( $this->logger, $message );
        }
    }
}