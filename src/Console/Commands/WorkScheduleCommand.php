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

use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Processes background jobs and runs due scheduled tasks in a single pass.
 * The most common command to wire into a system crontab or WP-CLI schedule.
 */
class WorkScheduleCommand extends AbstractCommand {
    public function __construct(
        protected Scheduler $scheduler,
        protected QueueWorker $queue_worker,
        InputInterface $io,
        OutputInterface $output,
        ScriptName $script_name
    ) {
        parent::__construct(
            io: $io,
            output: $output,
            script_name: $script_name
        );
    }
    public static function name(): string {
        return 'work:schedule';
    }

    public function description(): string {
        return 'Process background jobs and run due scheduled tasks in one pass.';
    }
    public function synopsis(): string {
        return 'smliser work:schedule';
    }

    public function help(): string {
        return '';
    }


    public function run( CommandInput $input ): int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        // Queue.
        $this->output->info( 'Processing queue...' );
        $processed = $this->queue_worker->process_within_time_budget();

        if ( $processed === 0 ) {
            $this->output->writeln( 'No jobs were waiting in the queue.' );
        } else {
            $this->output->writeln( sprintf( '%d job(s) processed.', $processed ) );
        }

        // Scheduler.
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