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
use SmartLicenseServer\Console\Traits\CLIUtilsTrait;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Schedule\ScheduledTask;

/**
 * Manage and execute scheduled tasks from the CLI.
 */
class ScheduleCommand extends AbstractCommand {
    use CLIUtilsTrait;

    public function __construct(
        protected Scheduler $scheduler,
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
     * @param CommandInput $input
     * @return int
     */
    public function handle_run( CommandInput $input ): int {
        $force = (bool)$input->get_option( 'force', false );

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

        foreach ( $tasks_to_run as $id =>$task ) {
            try {
                $task->execute();
                $rows[] = [$id,
                    $task->get_label() ?? $id,
                    'PASSED',
                    '—',
                ];
            } catch ( \Throwable $e ) {
                $rows[] = [$id,
                    $task->get_label() ?? $id,
                    'FAILED',
                    $e->getMessage(),
                ];
            }
            $run_count++;
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
            /** @var ScheduledTask $task */
            $task  = $data['task'];
            $state = $data['state'];

            $last_ran   = $state['last_ran_at'] ? $state['last_ran_at']->format( \smliser_datetime_format() ) : 'Never';
            $next_run   = $state['next_run_at'] ? $state['next_run_at']->format( \smliser_datetime_format() ) : 'N/A';
            $is_due     = $task->is_due( $state['last_ran_at'] );
            $status     = $is_due ? 'DUE' : 'WAITING';

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