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

use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Evaluates all registered scheduled tasks and runs any that are due.
 */
class ScheduleCommand extends AbstractCommand {


    public function __construct(
        protected Scheduler $scheduler,
        InputInterface $io,
        OutputInterface $output,
        ScriptName $script_name
    ) {
        return parent::__construct( $io, $output, $script_name );
    }

    public static function name(): string {
        return 'schedule';
    }

    public function description(): string {
        return 'Run all due scheduled tasks.';
    }
    public function synopsis(): string {
        return "{$this->script_name} schedule";
    }

    public function help(): string {
        return '';
    }

    public function run( CommandInput $input ): int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $this->output->info( 'Running due scheduled tasks...' );

        $results = $this->scheduler->run_due_tasks();
        $total   = count( $results );
        $failed  = count( array_filter( $results, fn( $r ) => $r === false ) );

        if ( $total === 0 ) {
            $this->output->writeln( 'No tasks were due.' );
        } else {
            $this->output->table(
                [ 'Task', 'Result' ],
                array_map(
                    fn( $id, $ok ) => [ $id, $ok ? '✔ Passed' : '✖ Failed' ],
                    array_keys( $results ),
                    $results
                )
            );

            if ( $failed > 0 ) {
                $this->output->warning( sprintf( '%d task(s) failed.', $failed ) );
            }
        }

        $this->output->success(
            sprintf( '%d task(s) ran, %d failed. Completed in %ss', 
                $total,
                $failed,
                $stopwatch->elapsed()
            )
        );

        return 0;
    }
}