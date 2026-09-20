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
use SmartLicenseServer\Console\Traits\CLIUtilsTrait;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Workers\QueueWorker;

/**
 * Manage and process background job queues from the CLI interface.
 */
class QueueCommand extends AbstractCommand {
    use CLIUtilsTrait;

    /**
     * Constructor.
     *
     * @param JobQueue      $queue          Job queue repository instance.
     * @param QueueWorker   $worker         Queue worker engine instance.
     * @param SignalManager $signal_manager Process OS signal coordinator.
     * @param InputInterface  $io           CLI input interface stream.
     * @param OutputInterface $output       CLI output interface stream.
     * @param ScriptName    $script_name    Executable binary alias context.
     */
    public function __construct(
        protected JobQueue $queue,
        protected QueueWorker $worker,
        protected SignalManager $signal_manager,
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
            '  release-stale     Release jobs stuck in running state back to pending.',
            '  purge             Purge completed jobs older than a threshold.',
            '  help              Show this help message.',
            '',
            'Options for work:',
            '  --queue=<name>    Target queue channel. Default: all queues.',
            '  --timeout=<sec>   Maximum execution time budget in seconds.',
            '',
            'Options for release-stale:',
            '  --timeout=<sec>   Seconds after which a running job is deemed stale. Default: 300.',
            '',
            'Options for purge:',
            '  --days=<n>        Purge completed jobs older than N days. Default: 7.',
            '',
            'Examples:',
            "  {$script_name} {$this->name()} work --queue=default --timeout=120",
            "  {$script_name} {$this->name()} status",
            "  {$script_name} {$this->name()} release-stale --timeout=600",
            "  {$script_name} {$this->name()} purge --days=14",
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
            'release-stale' => [ $this, 'handle_release_stale' ],
            'purge'         => [ $this, 'handle_purge' ],
            'help'          => [ $this, 'handle_help' ],
        ];
    }

    /**
     * Start worker execution daemon with OS signal monitoring and systemd journal output streaming.
     *
     * @param CommandInput $input
     * @return int Execution exit status code.
     */
    public function handle_work( CommandInput $input ): int {
        $queue      = $input->get_option( 'queue', null );
        $timeout    = $input->get_option( 'timeout', null );

        $queue_channel  = is_string( $queue ) ?$queue : null;
        $time_budget    = $timeout !== null ? (int) $timeout : null;

        // Stream real-time log entries to standard output for journald capture.
        $this->worker->set_logger( function( string $message ) {
            $timestamp  = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
            $this->output->writeln( sprintf( '[%s] %s', $timestamp, $message ) );
        });

        // Attach SignalManager listeners to handle graceful termination signals
        if ( $this->signal_manager->is_supported() ) {
            $stop_handler   = function( int $sig ) {
                $this->output->newline();
                $this->output->info(
                    sprintf(
                        'Received OS signal (%d). Requesting graceful worker shutdown...', $sig
                    )
                );

                $this->worker->request_stop();
            };

            $this->signal_manager->on( 'SIGTERM', $stop_handler );
            $this->signal_manager->on( 'SIGINT', $stop_handler );
            $this->signal_manager->on( 'SIGHUP', $stop_handler );
        }

        if ( $time_budget !== null ) {
            $this->worker->process_within_time_budget( $time_budget, $queue_channel );
        } else {
            $this->worker->start_processing( $queue_channel );
        }

        return 0;
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
}