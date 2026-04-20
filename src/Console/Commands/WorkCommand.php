<?php
/**
 * Work command class file.
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
 * Processes background jobs until the queue is empty or the time
 * budget is exhausted.
 */
class WorkCommand implements CommandInterface {
    use CLIAwareTrait;

    public static function name(): string {
        return 'work';
    }

    public static function description(): string {
        return 'Process background jobs until the queue is empty.';
    }
    public static function synopsis(): string {
        return 'smliser work';
    }

    public static function help(): string {
        return '';
    }


    public function execute( array $args = [] ): void {
        $this->start_timer();
        $this->info( 'Processing queue...' );

        $processed = smliser_queue_worker()->process_within_time_budget();

        if ( $processed === 0 ) {
            $this->line( 'No jobs were waiting in the queue.', self::VERBOSITY_VERBOSE );
        }

        $this->newline();
        $this->done( sprintf( '%d job(s) processed.', $processed ) );
    }
}