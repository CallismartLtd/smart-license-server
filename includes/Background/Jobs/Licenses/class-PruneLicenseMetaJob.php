<?php
/**
 * Prune license meta job class file.
 *
 * Deletes orphaned rows from the license meta table — rows whose
 * license_id no longer exists in the licenses table. These accumulate
 * when licenses are hard-deleted without cascading the meta deletion,
 * or via data inconsistencies from migrations.
 *
 * Designed to run weekly via the Scheduler.
 *
 * Payload: none.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Licenses
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Licenses;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Removes orphaned rows from the license meta table.
 */
class PruneLicenseMetaJob implements JobHandlerInterface {

    /*
    |--------------------------------------------
    | JobHandlerInterface
    |--------------------------------------------
    */

    public static function get_job_name(): string {
        return 'prune_license_meta';
    }

    public static function get_job_description(): string {
        return 'Deletes orphaned rows from the license meta table.';
    }

    /**
     * Execute the job.
     *
     * Uses a single DELETE ... WHERE NOT EXISTS query — no PHP-side
     * iteration, no loading of License objects. Efficient regardless
     * of table size.
     *
     * @param array $payload Unused.
     * @return array{deleted: int}
     */
    public function handle( array $payload = [] ): mixed {
        $db         = smliser_db();
        $meta_table = SMLISER_LICENSE_META_TABLE;
        $table      = SMLISER_LICENSE_TABLE;

        $sql = "DELETE FROM {$meta_table}
                WHERE `license_id` NOT IN (
                    SELECT `id` FROM {$table}
                )";

        $db->query( $sql );

        $deleted = $db->rows_affected();

        return [ 'deleted' => $deleted ];
    }
}