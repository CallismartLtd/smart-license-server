<?php
/**
 * Queue command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Commands\AbstractCommand;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Console\SignalManager;
use SmartLicenseServer\Console\SignalInfo;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Workers\CodeChangeDetector;
use SmartLicenseServer\Background\Workers\QueueWorker;

/**
 * Manage and process background job queues from the CLI interface.
 */
class QueueCommand extends AbstractCommand {

    /**
     * Exit code of a worker that stopped because the application was updated.
     *
     * Non-zero on purpose (75, EX_TEMPFAIL: "try again"), so every common
     * supervisor configuration starts it again on the new code: systemd
     * Restart=on-failure or Restart=always, and supervisord's default
     * autorestart=unexpected with exitcodes=0.
     *
     * @var int
     */
    public const EXIT_CODE_UPDATED = 75;

    /**
     * Seconds between checks while waiting for an update to end.
     *
     * @var int
     */
    private const UPDATE_POLL_SECONDS = 2;

    /**
     * Set by the signal handler, so a wait for an update can be interrupted too.
     *
     * @var bool
     */
    private bool $interrupted = false;

    /**
     * Constructor.
     *
     * @param JobQueue           $queue          Job queue repository instance.
     * @param QueueWorker        $worker         Queue worker engine instance.
     * @param SignalManager      $signal_manager Process OS signal coordinator.
     * @param CodeChangeDetector $code_changes   Tells the worker when the application is updated.
     * @param InputInterface     $io             CLI input interface stream.
     * @param OutputInterface    $output         CLI output interface stream.
     * @param ScriptName         $script_name    Executable binary alias context.
     */
    public function __construct(
        protected JobQueue $queue,
        protected QueueWorker $worker,
        protected SignalManager $signal_manager,
        protected CodeChangeDetector $code_changes,
        InputInterface $io,
        OutputInterface $output,
        ScriptName $script_name
    ) {
        parent::__construct( $io, $output, $script_name );
    }

    /**
     * {@inheritdoc}
     */
    public static function name(): string {
        return 'queue';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string {
        return 'Process background job queues and inspect queue state.';
    }

    /**
     * {@inheritdoc}
     */
    public function help(): string {
        $script_name    = $this->script_name;
        return implode( PHP_EOL, [
            'Subcommands:',
            '  work              Start processing jobs on the queue as a worker daemon.',
            '  status            Show job metrics grouped by status.',
            '  list              List jobs, optionally filtered by queue/status/class.',
            '  failed            List archived failed jobs, with full error messages.',
            '  release-stale     Release jobs stuck in running state back to pending.',
            '  purge             Purge completed jobs older than a threshold.',
            '  purge-failed      Purge archived failed jobs older than a threshold.',
            '  help              Show this help message.',
            '',
            'Options for work:',
            '  --queue=<name>    Target queue channel. Default: all queues.',
            '  --timeout=<sec>   Maximum execution time budget in seconds.',
            '',
            '  The worker stops taking jobs while the application is being updated, and',
            '  exits with code ' . self::EXIT_CODE_UPDATED . ' once a new version is installed, so the',
            '  process supervisor starts it again on the new code.',
            '',
            'Options for list:',
            '  --page=<n>        Page number (1-indexed). Default: 1.',
            '  --limit=<n>       Records per page. Default: 20.',
            '  --queue=<name>    Filter by queue channel.',
            '  --status=<name>   Filter by job status.',
            '  --job-class=<fqcn> Filter by job class.',
            '',
            'Options for failed:',
            '  --page=<n>        Page number (1-indexed). Default: 1.',
            '  --limit=<n>       Records per page. Default: 20.',
            '  --queue=<name>    Filter by queue channel.',
            '  --job-class=<fqcn> Filter by job class.',
            '',
            'Options for release-stale:',
            '  --timeout=<sec>   Seconds after which a running job is deemed stale. Default: 300.',
            '',
            'Options for purge:',
            '  --days=<n>        Purge completed jobs older than N days. Default: 7.',
            '',
            'Options for purge-failed:',
            '  --days=<n>        Purge archived failed jobs older than N days. Default: 30.',
            '',
            'Examples:',
            "  {$script_name} {$this->name()} work --queue=default --timeout=120",
            "  {$script_name} {$this->name()} status",
            "  {$script_name} {$this->name()} list --status=pending --page=1 --limit=20",
            "  {$script_name} {$this->name()} failed --queue=default",
            "  {$script_name} {$this->name()} release-stale --timeout=600",
            "  {$script_name} {$this->name()} purge --days=14",
            "  {$script_name} {$this->name()} purge-failed --days=90",
        ] );
    }

    /**
     * Default execution handle when no subcommand is given.
     *
     * @param CommandInput $input
     * @return int
     */
    public function run( CommandInput $input ): int {$this->output->info( static::description() );
        $this->output->newline();
        $this->output->writeln( 'Run `smliser queue help` to see available subcommands.' );

        return 0;
    }

    /**
     * Map subcommands to internal method callbacks.
     *
     * @return array<string, callable>
     */
    public function get_subcommands(): array {
        return [
            'work'          => [ $this, 'handle_work' ],
            'status'        => [ $this, 'handle_status' ],
            'list'          => [ $this, 'handle_list' ],
            'failed'        => [ $this, 'handle_failed' ],
            'release-stale' => [ $this, 'handle_release_stale' ],
            'purge'         => [ $this, 'handle_purge' ],
            'purge-failed'  => [ $this, 'handle_purge_failed' ],
            'help'          => [ $this, 'handle_help' ],
        ];
    }

    /**
     * Start worker execution daemon with OS signal monitoring and systemd journal output streaming.
     *
     * The worker loads its code once, so it must not outlive an update:
     *
     *  - while an update swaps files, it takes no jobs and waits;
     *  - once state.json records a new version, it exits with
     *    EXIT_CODE_UPDATED and the supervisor starts it on the new code;
     *  - an update that ended without a new version (failed, rolled
     *    back) lets it carry on.
     *
     * A worker started while state.json and the code on disk disagree (an
     * update whose finish step has not run) waits instead of exiting, so
     * the supervisor does not restart it in a loop.
     *
     * @param CommandInput $input
     * @return int Execution exit status code.
     */
    public function handle_work( CommandInput $input ): int {
        $queue      = $input->get_option( 'queue', null );
        $timeout    = $input->get_option( 'timeout', null );

        $queue_channel  = is_string( $queue ) ?$queue : null;
        $time_budget    = $timeout !== null ? (int) $timeout : null;

        $this->interrupted = false;

        // Stream real-time log entries to standard output for journald capture.
        $this->worker->set_logger( fn( string $message ) => $this->log( $message ) );

        // Attach SignalManager listeners to handle graceful termination signals
        if ( $this->signal_manager->is_supported() ) {
            $stop_handler   = function( int $sig, SignalInfo $info ) {
                $this->output->newline();
                $this->output->info(
                    sprintf(
                        'Received %s (%s). Requesting graceful worker shutdown...',
                        $info->description,
                        $info->signal_name
                    )
                );

                $this->interrupted = true;
                $this->worker->request_stop();
            };

            $this->signal_manager->on( 'SIGTERM', $stop_handler );
            $this->signal_manager->on( 'SIGINT', $stop_handler );
            $this->signal_manager->on( 'SIGHUP', $stop_handler );
        }

        // Checked between jobs and every second while idle.
        $reason = null;
        $this->worker->set_stop_checker(
            function () use ( &$reason ): bool {
                $reason = $this->code_changes->reason();

                return null !== $reason;
            }
        );

        try {
            if ( $time_budget !== null ) {
                return $this->work_within_budget( $time_budget, $queue_channel );
            }

            return $this->work_until_stopped( $queue_channel, $reason );
        } finally {
            $this->worker->set_stop_checker( null );
        }
    }

    /**
     * Process for a time budget (cron-style runs, a fresh process each time).
     *
     * A run that starts during an update does nothing; the next one picks up.
     *
     * @param int         $time_budget   Seconds.
     * @param string|null $queue_channel Queue, or null for all.
     * @return int Exit code.
     */
    private function work_within_budget( int $time_budget, ?string $queue_channel ): int {
        if ( null !== $this->code_changes->reason() ) {
            $this->log( 'The application is being updated, or its update has not finished; no jobs taken in this run.' );
            return 0;
        }

        $this->worker->process_within_time_budget( $time_budget, $queue_channel );

        return 0;
    }

    /**
     * Process until a signal, a worker limit, or an application update.
     *
     * @param string|null $queue_channel Queue, or null for all.
     * @param string|null $reason        Set by the stop checker: why the worker stopped.
     * @return int Exit code.
     */
    private function work_until_stopped( ?string $queue_channel, ?string &$reason ): int {
        // Started on code that state.json does not record yet: wait for the
        // update to be finished (or rolled back) rather than exit and be
        // restarted in a loop.
        if ( CodeChangeDetector::REASON_CHANGED === $this->code_changes->reason() ) {
            $this->log(
                sprintf(
                    'This worker runs version %s but state.json records %s; an update has not finished. Waiting for `%s update finish` (or a rollback)...',
                    $this->code_changes->running_version(),
                    $this->code_changes->installed_version() ?? 'unknown',
                    $this->script_name
                )
            );

            if ( ! $this->wait_while( fn() => null !== $this->code_changes->reason() ) ) {
                return 0;
            }
        }

        while ( true ) {
            $reason = null;

            $this->worker->start_processing( $queue_channel );

            // A signal, the memory ceiling or the job limit: a normal stop.
            if ( $this->interrupted || null === $reason ) {
                return 0;
            }

            if ( CodeChangeDetector::REASON_CHANGED === $reason ) {
                return $this->exit_for_update();
            }

            // REASON_UPDATING: take no jobs until the update has ended.
            $this->log( 'The application is being updated. No jobs are taken until it ends.' );

            if ( ! $this->wait_while( fn() => CodeChangeDetector::REASON_UPDATING === $this->code_changes->reason() ) ) {
                return 0;
            }

            if ( CodeChangeDetector::REASON_CHANGED === $this->code_changes->reason() ) {
                return $this->exit_for_update();
            }

            $this->log( 'The update ended without a new version; resuming.' );
        }
    }

    /**
     * Log why the worker exits after an update, and return the restart exit code.
     *
     * @return int
     */
    private function exit_for_update(): int {
        $this->log(
            sprintf(
                'The application was updated from %s to %s. Exiting with code %d so the process supervisor starts the worker on the new code.',
                $this->code_changes->running_version(),
                $this->code_changes->installed_version() ?? 'a new version',
                self::EXIT_CODE_UPDATED
            )
        );

        return self::EXIT_CODE_UPDATED;
    }

    /**
     * Sleep while a condition holds, until it clears or a signal arrives.
     *
     * @param callable(): bool $condition Keep waiting while this returns true.
     * @return bool True when the condition cleared, false when interrupted.
     */
    private function wait_while( callable $condition ): bool {
        while ( $condition() ) {
            if ( $this->interrupted ) {
                return false;
            }

            sleep( self::UPDATE_POLL_SECONDS );
        }

        return ! $this->interrupted;
    }

    /**
     * Write a timestamped worker log line.
     *
     * @param string $message
     * @return void
     */
    private function log( string $message ): void {
        $timestamp  = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
        $this->output->writeln( sprintf( '[%s] %s', $timestamp, $message ) );
    }

    /**
     * Render job metrics grouped by queue state.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_status( CommandInput $input ): int {
        $statuses   = [ 'pending', 'running', 'failed', 'completed' ];
        $rows       = [];
        $total      = 0;

        foreach ( $statuses as $status ) {
            $count  = $this->queue->count_jobs_by_status( $status );
            $total  += $count;

            $rows[] = [ strtoupper( $status ), $count ];
        }

        $this->output->newline();
        $this->output->table( [ 'Status', 'Count' ], $rows );
        $this->output->newline();
        $this->output->writeln( sprintf( 'Total Jobs across all states: %d', $total ) );

        return 0;
    }

    /**
     * List jobs, optionally filtered by queue, status, and/or job class.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_list( CommandInput $input ): int {
        $page      = max( 1, (int) $input->get_option( 'page', 1 ) );
        $limit     = max( 1, (int) $input->get_option( 'limit', 20 ) );
        $queue     = $input->get_option( 'queue', null );
        $status    = $input->get_option( 'status', null );
        $job_class = $input->get_option( 'job-class', null );

        $jobs  = $this->queue->get_jobs( $page, $limit, $queue, $status, $job_class );
        $total = $this->queue->count_jobs( $queue, $status, $job_class );

        if ( empty( $jobs ) ) {
            $this->output->writeln( 'No jobs found matching the given filters.' );
            return 0;
        }

        $rows = array_map(
            fn( JobDTO $job ) => [
                $job->get( JobDTO::KEY_ID ),
                $job->get( JobDTO::KEY_JOB_CLASS ),
                $job->get( JobDTO::KEY_QUEUE ),
                strtoupper( (string) $job->get( JobDTO::KEY_STATUS ) ),
                $job->get( JobDTO::KEY_ATTEMPTS ),
                $job->get( JobDTO::KEY_AVAILABLE_AT )->format( \smliser_datetime_format() ),
                $this->format_result( $job->get( JobDTO::KEY_RESULT ) ),
            ],
            $jobs
        );

        $this->output->newline();
        $this->output->table( [ 'ID', 'Job Class', 'Queue', 'Status', 'Attempts', 'Available At', 'Result' ], $rows );
        $this->output->newline();
        $this->output->writeln( sprintf( 'Page %d — showing %d of %d matching job(s).', $page, count( $jobs ), $total ) );

        return 0;
    }

    /**
     * List archived failed jobs, optionally filtered by queue and/or job class.
     *
     * Rendered as individual blocks rather than a table — these logs
     * exist for auditing, and a table forces every column to a fixed
     * width, truncating exactly the detail an auditor needs.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_failed( CommandInput $input ): int {
        $page      = max( 1, (int) $input->get_option( 'page', 1 ) );
        $limit     = max( 1, (int) $input->get_option( 'limit', 20 ) );
        $queue     = $input->get_option( 'queue', null );
        $job_class = $input->get_option( 'job-class', null );

        $failed_jobs = $this->queue->get_failed_jobs( $page, $limit, $queue, $job_class );
        $total       = $this->queue->count_failed_jobs( $queue, $job_class );

        if ( empty( $failed_jobs ) ) {
            $this->output->writeln( 'No failed jobs found matching the given filters.' );
            return 0;
        }

        $divider = str_repeat( '-', 70 );

        $this->output->newline();

        foreach ( $failed_jobs as $job ) {
            $started_at   = $job->get( JobDTO::KEY_STARTED_AT );
            $completed_at = $job->get( JobDTO::KEY_COMPLETED_AT );
            $result       = $job->get( JobDTO::KEY_RESULT );

            $this->output->writeln( $divider );
            $this->output->info( sprintf(
                'ID: %d    Queue: %s    Priority: %d',
                $job->get( JobDTO::KEY_ID ),
                $job->get( JobDTO::KEY_QUEUE ),
                $job->get( JobDTO::KEY_PRIORITY )
            ) );
            $this->output->info( sprintf( 'Job Class: %s', $job->get( JobDTO::KEY_JOB_CLASS ) ) );
            $this->output->info( sprintf(
                'Attempts: %d / %d',
                $job->get( JobDTO::KEY_ATTEMPTS ),
                $job->get( JobDTO::KEY_MAX_ATTEMPTS )
            ) );
            $this->output->info( sprintf(
                'Created: %s    Started: %s',
                $job->get( JobDTO::KEY_CREATED_AT )->format( \smliser_datetime_format() ),
                $started_at ? $started_at->format( \smliser_datetime_format() ) : '-'
            ) );
            $this->output->info( sprintf(
                'Failed At: %s',
                $job->get( JobDTO::KEY_AVAILABLE_AT )->format( \smliser_datetime_format() )
            ) );

            if ( null !== $result ) {
                $this->output->newline();
                $this->output->info( 'Result at time of failure:' );
                $this->output->writeln( $this->format_result( $result ) );
            }

            $this->output->newline();
            $this->output->info( 'Error:' );
            $this->output->error( (string) $job->get( JobDTO::KEY_ERROR_MESSAGE ) );
            $this->output->newline();
        }

        $this->output->writeln( $divider );
        $this->output->writeln( sprintf( 'Page %d — showing %d of %d matching failed job(s).', $page, count( $failed_jobs ), $total ) );

        return 0;
    }

    /**
     * Release stale running jobs back to pending state.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_release_stale( CommandInput $input ): int {
        $timeout_seconds = (int) $input->get_option( 'timeout', 300 );

        $this->start_timer();

        $released = $this->queue->release_stale_running_jobs( $timeout_seconds );

        $this->output->success(
            sprintf( 'Released %d stale running job(s) back to pending in %s.', $released, $this->elapsed_formatted() )
        );

        return 0;
    }

    /**
     * Purge completed jobs older than a given day threshold.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_purge( CommandInput $input ): int {
        $days = (int) $input->get_option( 'days', 7 );

        $this->start_timer();

        $purged = $this->queue->purge_completed_jobs( $days );

        $this->output->success(
            sprintf( 'Purged %d completed job(s) older than %d day(s) in %s.', $purged, $days, $this->elapsed_formatted() )
        );

        return 0;
    }

    /**
     * Purge archived failed jobs older than a given day threshold.
     *
     * Separate from `purge` deliberately — failed jobs are an audit
     * trail, not routine disposable state, so this carries its own
     * longer default retention and must be invoked explicitly.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_purge_failed( CommandInput $input ): int {
        $days = (int) $input->get_option( 'days', 30 );

        $this->start_timer();

        $purged = $this->queue->purge_failed_jobs( $days );

        $this->output->success(
            sprintf( 'Purged %d archived failed job(s) older than %d day(s) in %s.', $purged, $days, $this->elapsed_formatted() )
        );

        return 0;
    }

    /**
     * Render a job's stored result for table display.
     *
     * Compact-encodes non-scalar results to a single line rather than
     * truncating — this is an audit surface, so the full result stays
     * visible even if the line runs long.
     *
     * @param mixed $result
     * @return string
     */
    private function format_result( mixed $result ): string {
        if ( null === $result ) {
            return '-';
        }

        if ( is_scalar( $result ) ) {
            return (string) $result;
        }

        return json_encode( $result, JSON_UNESCAPED_SLASHES );
    }
}