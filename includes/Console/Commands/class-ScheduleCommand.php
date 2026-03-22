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

use SmartLicenseServer\Console\CLIAwareTrait;
use SmartLicenseServer\Console\CommandInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Evaluates all registered scheduled tasks and runs any that are due.
 */
class ScheduleCommand implements CommandInterface {
    use CLIAwareTrait;

    public static function name(): string {
        return 'schedule';
    }

    public static function description(): string {
        return 'Run all due scheduled tasks.';
    }

    public function execute( array $args = [] ): void {
        $this->start_timer();
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
        $this->done( sprintf( '%d task(s) ran, %d failed.', $total, $failed ) );
    }
}