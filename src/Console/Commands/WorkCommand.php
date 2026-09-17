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

use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Processes background jobs until the queue is empty or the time
 * budget is exhausted.
 */
class WorkCommand extends AbstractCommand {
    public function __construct(
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
        return 'work';
    }

    public function description(): string {
        return 'Process background jobs until the queue is empty.';
    }
    public function synopsis(): string {
        return 'smliser work';
    }

    public function help(): string {
        return '';
    }


    public function run( CommandInput $input ): int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $this->output->info( 'Processing queue...' );

        $processed = $this->queue_worker->process_within_time_budget();

        if ( $processed === 0 ) {
            $this->output->writeln( 'No jobs were waiting in the queue.' );
        }

        $this->output->success( 
            sprintf( '%d job(s) processed. Completed in %ss', $processed, $stopwatch->elapsed() ) 
        );

        return 0;
    }
}