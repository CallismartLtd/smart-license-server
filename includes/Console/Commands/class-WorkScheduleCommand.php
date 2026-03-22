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

use SmartLicenseServer\Console\CommandInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Processes background jobs and runs due scheduled tasks in a single pass.
 * The most common command to wire into a system crontab or WP-CLI schedule.
 */
class WorkScheduleCommand implements CommandInterface {

    public static function name(): string {
        return 'work:schedule';
    }

    public static function description(): string {
        return 'Process background jobs and run due scheduled tasks in one pass.';
    }

    public function execute( array $args = [] ): void {
        $processed = smliser_queue_worker()->process_within_time_budget();
        $results   = smliser_scheduler()->run_due_tasks();
        $total     = count( $results );
        $failed    = count( array_filter( $results, fn( $r ) => $r === false ) );

        echo sprintf(
            'Queue: %d job(s) processed. Scheduler: %d task(s) ran, %d failed.' . PHP_EOL,
            $processed,
            $total,
            $failed
        );
    }
}