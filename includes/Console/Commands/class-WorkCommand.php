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

use SmartLicenseServer\Console\CommandInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Processes background jobs until the queue is empty or the time
 * budget is exhausted.
 */
class WorkCommand implements CommandInterface {

    public static function name(): string {
        return 'work';
    }

    public static function description(): string {
        return 'Process background jobs until the queue is empty.';
    }

    public function execute( array $args = [] ): void {
        $processed = smliser_queue_worker()->process_within_time_budget();
        echo sprintf( 'Done. %d job(s) processed.' . PHP_EOL, $processed );
    }
}