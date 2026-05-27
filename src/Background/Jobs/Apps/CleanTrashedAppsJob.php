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
 *   batch_size    (int) — Max apps to delete per run. Default 50.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Apps
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Apps;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\Dates\TimestampValue;
use SmartLicenseServer\HostedApps\HostedApplicationService;

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
     * 
     * @param array{
     *     days_in_trash?: int,
     *     batch_size?: int
     * 
     * } $payload
     * @return array{'deleted': int, 'failed': int}
     */
    public function handle( array $payload = [] ): mixed {
        $days_in_trash = max( 1, (int) ( $payload['days_in_trash'] ?? 30 ) );
        $batch_size    = max( 1, (int) ( $payload['batch_size']    ?? 50 ) );

        $deleted            = 0;
        $failed             = 0;
        $processed_batch    = 0;
        $trashed_apps_data  = HostedApplicationService::list_trashed_apps();

        foreach ( $trashed_apps_data as $data ) {
            if ( $processed_batch >= $batch_size ) {
                break;
            }

            $timestamp  = TimestampValue::fromTimestamp( $data['timestamp'] ?? 0 )
                ->addDays( $days_in_trash );

            if ( ! $timestamp->isPast() ) {
                continue;
            }

            $type   = $data['app_type'] ?? '';
            $slug   = $data['app_slug'] ?? '';
            $app    = HostedApplicationService::get_app_by_slug( $type, $slug );

            try {
                $app_deleted    = $app?->delete() ?? false;

                // Even if the app record is deleted, we attempt to delete the files.
                // This ensures that if the record deletion fails but files are removed,
                // we don't leave orphaned files.
                $files_deleted  = smliser_filesystem()->rmdir( $data['trash_path'], true );

                if ( $app_deleted || $files_deleted ) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch( \Throwable ) {
                $failed++;
            }

            $processed_batch++;
        }

        return compact( 'deleted', 'failed' );
    }
}