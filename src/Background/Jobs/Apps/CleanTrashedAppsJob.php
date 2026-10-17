<?php
/**
 * Clean trashed apps job class file.
 *
 * Permanently deletes apps that have been in trash status longer than
 * a configurable threshold. Processes every registered app type in a
 * single run.
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
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\HostedApps\Storage\AppStorage;

/**
 * Permanently deletes trashed apps past the retention threshold.
 *
 * The run is driven by what app storage holds in its trash. For each app
 * due for deletion, the trashed database record goes first, then the
 * files: if deleting the files fails, the app is still in the trash
 * listing and the next run finishes it.
 *
 * An app is skipped, never deleted, when a live app has the same slug:
 * deleting files removes the live and trashed copies alike, and the
 * slug's database record may be the live app's.
 */
class CleanTrashedAppsJob implements JobHandlerInterface {

	public static function get_job_name() : string {
		return 'clean_trashed_apps';
	}

	public static function get_job_description() : string {
		return 'Permanently deletes apps that have been in trash longer than the retention threshold.';
	}

	/**
	 * @param array{
	 *     days_in_trash?: int,
	 *     batch_size?: int
	 * 
	 * } $payload
	 * @return array{deleted: int, failed: int, skipped: int}
	 */
	public function handle( array $payload = [] ) : mixed {
		$days_in_trash = max( 1, (int) ( $payload['days_in_trash'] ?? 30 ) );
		$batch_size    = max( 1, (int) ( $payload['batch_size'] ?? 50 ) );

		$deleted         = 0;
		$failed          = 0;
		$skipped         = 0;
		$processed_batch = 0;
		$storage         = AppStorage::instance();

		// Long-running worker: never decide from an earlier run's answers.
		$storage->flush();

		foreach ( HostedApplicationService::list_trashed_apps() as $data ) {
			if ( $processed_batch >= $batch_size ) {
				break;
			}

			$timestamp = TimestampValue::fromTimestamp( $data['timestamp'] )
				->addDays( $days_in_trash );

			if ( ! $timestamp->isPast() ) {
				continue;
			}

			$processed_batch++;

			$type = $data['app_type'];
			$slug = $data['app_slug'];

			try {
				if ( $storage->has_app( $type, $slug ) ) {
					$skipped++;
					continue;
				}

				$app = HostedApplicationService::get_app_by_slug( $type, $slug );

				if ( null !== $app && AbstractHostedApp::STATUS_TRASH !== $app->get_status() ) {
					$skipped++;
					continue;
				}

				if ( null !== $app && ! $app->delete() ) {
					$failed++;
					continue;
				}

				$purged = HostedAppsRegistry::instance()->get_app_type_directory_class( $type )?->purge( $slug );

				// Not found: the files are already gone.
				if ( $purged instanceof Exception && StorageException::NOT_FOUND !== $purged->get_error_code() ) {
					$failed++;
					continue;
				}

				$deleted++;
			} catch ( \Throwable ) {
				$failed++;
			}
		}

		return compact( 'deleted', 'failed', 'skipped' );
	}
}