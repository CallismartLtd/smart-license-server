<?php
/**
 * Clean trashed apps job class file.
 *
 * Permanently deletes apps that have been in trash status longer than
 * a configurable threshold. Processes all three app tables — plugins,
 * themes, and software — in a single run.
 *
 * Designed to run weekly via the Scheduler.
 *
 * Payload:
 *   days_in_trash (int) — Days an app must be in trash before deletion. Default 30.
 *   batch_size    (int) — Max apps to delete per type per run. Default 50.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Apps
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Apps;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Permanently deletes trashed apps past the retention threshold.
 */
class CleanTrashedAppsJob implements JobHandlerInterface {

    public static function get_job_name(): string {
        return 'clean_trashed_apps';
    }

    public static function get_job_description(): string {
        return 'Permanently deletes apps that have been in trash longer than the retention threshold.';
    }

    /**
     * @param array $payload {
     *     @type int $days_in_trash Days before permanent deletion. Default 30.
     *     @type int $batch_size    Max apps per type per run. Default 50.
     * }
     * @return array{deleted: int, failed: int}
     */
    public function handle( array $payload ): mixed {
        $days_in_trash = max( 1, (int) ( $payload['days_in_trash'] ?? 30 ) );
        $batch_size    = max( 1, (int) ( $payload['batch_size']    ?? 50 ) );

        $deleted = 0;
        $failed  = 0;

        $type_tables = [
            'plugin'   => SMLISER_PLUGINS_TABLE,
            'theme'    => SMLISER_THEMES_TABLE,
            'software' => SMLISER_SOFTWARE_TABLE,
        ];

        $db        = smliser_db();
        $threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_in_trash} days" ) );

        foreach ( $type_tables as $type => $table ) {
            $sql = "SELECT `id` FROM {$table}
                    WHERE `status` = ?
                    AND `updated_at` < ?
                    LIMIT ?";

            $rows = $db->get_results( $sql, [
                AbstractHostedApp::STATUS_TRASH,
                $threshold,
                $batch_size,
            ] );

            foreach ( $rows as $row ) {
                $app = HostedApplicationService::get_app_by_id( $type, (int) $row['id'] );

                if ( ! $app ) {
                    $failed++;
                    continue;
                }

                $app->delete()
                    ? $deleted++
                    : $failed++;
            }
        }

        return compact( 'deleted', 'failed' );
    }
}