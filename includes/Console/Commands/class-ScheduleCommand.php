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

use SmartLicenseServer\Console\CommandInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Evaluates all registered scheduled tasks and runs any that are due.
 */
class ScheduleCommand implements CommandInterface {

    public static function name(): string {
        return 'schedule';
    }

    public static function description(): string {
        return 'Run all due scheduled tasks.';
    }

    public function execute( array $args = [] ): void {
        $results = smliser_scheduler()->run_due_tasks();
        $total   = count( $results );
        $failed  = count( array_filter( $results, fn( $r ) => $r === false ) );
        echo sprintf( 'Done. %d task(s) ran, %d failed.' . PHP_EOL, $total, $failed );
    }
}