<?php
/**
 * Clean purchase receipts job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Purchases
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Purchases;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Deletes payment receipts that are no longer needed.
 *
 * 1. Receipts of decided (approved or rejected) requests reviewed more than
 *    the retention period ago (Settings::RECEIPT_RETENTION_DAYS, 90 days by
 *    default; 0 keeps them): the file and its page images are deleted and
 *    the request is kept as a record, with a note saying so
 *    (PurchaseRequestService::remove_receipt()). Pending requests keep
 *    their receipts.
 * 2. Receipt files no request refers to, older than a day
 *    (PurchaseRequestService::clean_orphaned_receipts()), whatever the
 *    retention.
 *
 * Runs daily from the Scheduler. Each run handles at most "batch_size"
 * receipts of each kind; the rest wait for the next run.
 *
 * Payload:
 *   batch_size (int) Most receipts removed per kind per run. Default 100.
 */
class CleanPurchaseReceiptsJob implements JobHandlerInterface {

	/**
	 * Retention used when the setting was never saved.
	 *
	 * @var int
	 */
	public const DEFAULT_RETENTION_DAYS = 90;

	public function __construct(
		protected PurchaseRequestService $requests,
		protected Settings $settings
	) {}

	/**
	 * {@inheritdoc}
	 *
	 * @param array{batch_size?: int} $payload
	 * @return array{retention_days: int, removed: int, failed: int, orphans_removed: int, orphans_failed: int}
	 */
	public function handle( array $payload = [] ) : mixed {
		$batch_size = max( 1, (int) ( $payload['batch_size'] ?? 100 ) );
		$days       = max( 0, (int) $this->settings->get( Settings::RECEIPT_RETENTION_DAYS, self::DEFAULT_RETENTION_DAYS ) );
		$removed    = 0;
		$failed     = 0;

		if ( $days > 0 ) {
			$before = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( sprintf( '-%d days', $days ) );
			$reason = sprintf( 'Receipt file deleted automatically, %d days after the decision (receipt retention).', $days );

			foreach ( PurchaseRequest::get_with_receipts_reviewed_before( $before, $batch_size ) as $request ) {
				$this->requests->remove_receipt( $request, $reason ) instanceof Exception ? $failed++ : $removed++;
			}
		}

		$orphans = $this->requests->clean_orphaned_receipts( 86400, $batch_size );

		return [
			'retention_days'  => $days,
			'removed'         => $removed,
			'failed'          => $failed,
			'orphans_removed' => $orphans['removed'],
			'orphans_failed'  => $orphans['failed'],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name() : string {
		return 'Clean Purchase Receipts';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description() : string {
		return 'Delete the payment receipts of decided purchase requests after the receipt retention period, and receipt files no request refers to.';
	}
}
