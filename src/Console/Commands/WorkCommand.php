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

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Traits\CLIAwareTrait;
use SmartLicenseServer\Console\Contracts\CommandInterface;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Processes background jobs until the queue is empty or the time
 * budget is exhausted.
 */
class WorkCommand extends AbstractCommand {

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


    public function run( CommandInput $input ): int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $this->output->info( 'Processing queue...' );

        $processed = smliser_queue_worker()->process_within_time_budget();

        if ( $processed === 0 ) {
            $this->output->writeln( 'No jobs were waiting in the queue.' );
        }

        $this->output->success( 
            sprintf( '%d job(s) processed. Completed in %ss', $processed, $stopwatch->elapsed() ) 
        );

        return 0;
    }
}