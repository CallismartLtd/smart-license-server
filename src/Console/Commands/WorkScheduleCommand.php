<?php
/**
 * WorkSchedule command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Traits\CLIAwareTrait;
use SmartLicenseServer\Console\Contracts\CommandInterface;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Processes background jobs and runs due scheduled tasks in a single pass.
 * The most common command to wire into a system crontab or WP-CLI schedule.
 */
class WorkScheduleCommand extends AbstractCommand {

    public static function name(): string {
        return 'work:schedule';
    }

    public static function description(): string {
        return 'Process background jobs and run due scheduled tasks in one pass.';
    }
    public static function synopsis(): string {
        return 'smliser work:schedule';
    }

    public static function help(): string {
        return '';
    }


    public function run( CommandInput $input ): int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        // Queue.
        $this->output->info( 'Processing queue...' );
        $processed = smliser_queue_worker()->process_within_time_budget();

        if ( $processed === 0 ) {
            $this->output->writeln( 'No jobs were waiting in the queue.' );
        } else {
            $this->output->writeln( sprintf( '%d job(s) processed.', $processed ) );
        }

        // Scheduler.
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

        $this->output->writeln( '' );
        $this->output->success( sprintf(
            'Queue: %d job(s) processed. Scheduler: %d task(s) ran, %d failed. Completed in %ss',
            $processed,
            $total,
            $failed,
            $stopwatch->elapsed()
        ) );

        return 0;
    }
}