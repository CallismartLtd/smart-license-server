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
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Evaluates all registered scheduled tasks and runs any that are due.
 */
class ScheduleCommand extends AbstractCommand {
    public static function name(): string {
        return 'schedule';
    }

    public static function description(): string {
        return 'Run all due scheduled tasks.';
    }
    public static function synopsis(): string {
        return 'smliser schedule';
    }

    public static function help(): string {
        return '';
    }


    public function run( CommandInput $input ): int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $this->output->info( 'Running due scheduled tasks...' );

        $results = smliser_scheduler()->run_due_tasks();
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