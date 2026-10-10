<?php
/**
 * Schedule command class file.
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
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\CodeChangeDetector;

/**
 * Manage and execute scheduled tasks from the CLI.
 */
class ScheduleCommand extends AbstractCommand {

    /**
     * Constructor.
     *
     * @param Scheduler          $scheduler    The task scheduler.
     * @param CodeChangeDetector $code_changes Tells when the application is being updated.
     * @param InputInterface     $io           CLI input interface stream.
     * @param OutputInterface    $output       CLI output interface stream.
     * @param ScriptName         $script_name  Executable binary alias context.
     */
    public function __construct(
        protected Scheduler $scheduler,
        protected CodeChangeDetector $code_changes,
        InputInterface $io,
        OutputInterface $output,
        ScriptName $script_name
    ) {
        parent::__construct( $io, $output, $script_name );
    }

    public static function name(): string {
        return 'schedule';
    }

    public function description(): string {
        return 'Inspect and run scheduled background tasks.';
    }

    public function help(): string {
        $script_name = $this->script_name;
        return implode( PHP_EOL, [
            'Subcommands:',
            '  run               Evaluate and execute due scheduled tasks.',
            '  list              Display all registered scheduled tasks and their run states.',
            '  help              Show this help message.',
            '',
            'Options:',
            '  --force           Force run all registered tasks immediately regardless of schedule.',
            '',
            'Nothing runs while the application is being updated, or while an update',
            'waits for its finish step; due tasks stay due and run on the next call.',
            '',
            'Examples:',
            "  {$script_name} schedule run",
            "  {$script_name} schedule list",
            "  {$script_name} schedule run --force",
        ] );
    }

    public function run( CommandInput $input ): int {
        $this->output->info( static::description() );
        $this->output->newline();
        $this->output->writeln( 'Run `smliser schedule help` to see available subcommands.' );

        return 0;
    }

    public function get_subcommands(): array {
        return [
            'run'  => [ $this, 'handle_run' ],
            'list' => [ $this, 'handle_list' ],
            'help' => [ $this, 'handle_help' ],
        ];
    }

    /**
     * Evaluate and execute tasks that are due (or forced).
     *
     * Runs nothing while an update swaps files (the code on disk is half
     * replaced) or waits for its finish step (the database may not match
     * the code yet). The check is repeated before every task, so a run
     * that overlaps the start of an update stops there. Skipped tasks are
     * not recorded as run, so they stay due for the next call.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_run( CommandInput $input ): int {
        $force = (bool) $input->get_option( 'force', false );

        if ( null !== ( $reason = $this->code_changes->reason() ) ) {
            $this->output->warning( $this->skip_message( $reason, 'No scheduled tasks were run.' ) );
            return 0;
        }

        $this->start_timer();
        $this->output->info( 'Evaluating scheduled tasks...' );

        if ( $force ) {
            $tasks_to_run = $this->scheduler->get_tasks();
        } else {
            $tasks_to_run = $this->scheduler->get_due_tasks();
        }

        if ( empty( $tasks_to_run ) ) {
            $this->output->warning( 'No tasks were due for execution.' );
            return 0;
        }

        $rows      = [];
        $run_count = 0;

        foreach ( $tasks_to_run as $id => $task ) {
            if ( null !== ( $reason = $this->code_changes->reason() ) ) {
                $this->output->warning( $this->skip_message( $reason, 'The remaining tasks stay due for the next run.' ) );
                break;
            }

            $error  = $this->scheduler->run_task( $id );
            $rows[] = null === $error
                ? [ $id, $task->get_label(), 'PASSED', '—' ]
                : [ $id, $task->get_label(), 'FAILED', $error ];

            $run_count++;
        }

        if ( empty( $rows ) ) {
            return 0;
        }

        $this->output->newline();
        $this->output->table( [ 'Task ID', 'Label / Description', 'Result', 'Error' ], $rows );
        $this->output->newline();

        $this->output->success(
            sprintf( 'Processed %d task(s) in %fs.', $run_count, $this->stop_timer() )
        );

        return 0;
    }

    /**
     * Why tasks are not run while the application is updated.
     *
     * @param string $reason One of the CodeChangeDetector::REASON_* constants.
     * @param string $then   What happens to the tasks.
     * @return string
     */
    private function skip_message( string $reason, string $then ): string {
        return CodeChangeDetector::REASON_UPDATING === $reason
            ? sprintf( 'The application is being updated. %s', $then )
            : sprintf(
                'This process runs version %s but state.json records %s: an update has not finished. Run `%s update finish` (or roll back). %s',
                $this->code_changes->running_version(),
                $this->code_changes->installed_version() ?? 'another version',
                $this->script_name,
                $then
            );
    }

    /**
     * List all registered scheduled tasks and their persisted state.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_list( CommandInput $input ): int {
        $tasks_with_state = $this->scheduler->get_tasks_with_state();

        if ( empty( $tasks_with_state ) ) {
            $this->output->warning( 'No scheduled tasks registered.' );
            return 0;
        }

        $rows = [];

        foreach ( $tasks_with_state as $id => $data ) {
            $task  = $data['task'];
            $state = $data['state'];

            $last_ran   = $state['last_ran_at'] ? $state['last_ran_at']->format( \smliser_datetime_format() ) : 'Never';
            $next_run   = $state['next_run_at'] ? $state['next_run_at']->format( \smliser_datetime_format() ) : 'N/A';
            $status     = $task->is_due( $state['last_ran_at'] ) ? 'DUE' : 'WAITING';

            if ( ! empty( $state['last_error'] ) ) {
                $status = 'ERROR';
            }

            $rows[] = [ $id, $task->get_label(), $last_ran, $next_run, $status ];
        }

        $this->output->newline();
        $this->output->table( [ 'Task ID', 'Label', 'Last Ran', 'Next Run', 'State' ], $rows );
        $this->output->newline();

        return 0;
    }
}