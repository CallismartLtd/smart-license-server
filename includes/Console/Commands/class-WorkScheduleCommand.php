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

use SmartLicenseServer\Console\CLIAwareTrait;
use SmartLicenseServer\Console\CommandInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Processes background jobs and runs due scheduled tasks in a single pass.
 * The most common command to wire into a system crontab or WP-CLI schedule.
 */
class WorkScheduleCommand implements CommandInterface {
    use CLIAwareTrait;

    public static function name(): string {
        return 'work:schedule';
    }

    public static function description(): string {
        return 'Process background jobs and run due scheduled tasks in one pass.';
    }

    public function execute( array $args = [] ): void {
        $this->start_timer();

        // Queue.
        $this->info( 'Processing queue...' );
        $processed = smliser_queue_worker()->process_within_time_budget();

        if ( $processed === 0 ) {
            $this->line( 'No jobs were waiting in the queue.', self::VERBOSITY_VERBOSE );
        } else {
            $this->line( sprintf( '%d job(s) processed.', $processed ), self::VERBOSITY_VERBOSE );
        }

        $this->newline();

        // Scheduler.
        $this->info( 'Running due scheduled tasks...' );
        $results = smliser_scheduler()->run_due_tasks();
        $total   = count( $results );
        $failed  = count( array_filter( $results, fn( $r ) => $r === false ) );

        if ( $total === 0 ) {
            $this->line( 'No tasks were due.', self::VERBOSITY_VERBOSE );
        } else {
            $this->newline();
            $this->table(
                [ 'Task', 'Result' ],
                array_map(
                    fn( $id, $ok ) => [ $id, $ok ? '✔ Passed' : '✖ Failed' ],
                    array_keys( $results ),
                    $results
                )
            );

            if ( $failed > 0 ) {
                $this->newline();
                $this->warning( sprintf( '%d task(s) failed.', $failed ) );
            }
        }

        $this->newline();
        $this->done( sprintf(
            'Queue: %d job(s) processed. Scheduler: %d task(s) ran, %d failed.',
            $processed,
            $total,
            $failed
        ) );
    }
}