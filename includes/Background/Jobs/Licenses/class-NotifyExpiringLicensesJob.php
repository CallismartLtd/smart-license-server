<?php
/**
 * Notify expiring licenses job class file.
 *
 * Queries licenses expiring within a configurable number of days and
 * sends a LicenseExpiryReminderEmail to each licensee whose service_id
 * is a valid email address. Does not modify license status.
 *
 * Designed to run daily via the Scheduler. Register one scheduler entry
 * per reminder threshold — the threshold is carried in the payload:
 *
 *   $this->dispatch( NotifyExpiringLicensesJob::class, ['days_before' => 7] )
 *        ->daily_at( '08:00' )
 *        ->id( 'notify_expiring_licenses_7d' );
 *
 *   $this->dispatch( NotifyExpiringLicensesJob::class, ['days_before' => 3] )
 *        ->daily_at( '08:00' )
 *        ->id( 'notify_expiring_licenses_3d' );
 *
 * Payload:
 *   days_before (int) — Days before expiry to query. Default 7.
 *   batch_size  (int) — Maximum licenses to process per run. Default 100.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Licenses
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Licenses;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Email\Templates\Licenses\LicenseExpiryReminderEmail;
use SmartLicenseServer\Monetization\License;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Send expiry reminder emails to licensees approaching their end_date.
 */
class NotifyExpiringLicensesJob implements JobHandlerInterface {
    use QueueAwareTrait;

    /*
    |--------------------------------------------
    | JobHandlerInterface
    |--------------------------------------------
    */

    public static function get_job_name(): string {
        return 'notify_expiring_licenses';
    }

    public static function get_job_description(): string {
        return 'Sends expiry reminder emails to licensees whose licenses are expiring soon.';
    }

    /**
     * Execute the job.
     *
     * Queries licenses expiring within the given days_before window
     * where the status is still active, then sends a reminder email
     * to each licensee whose service_id is a valid email address.
     *
     * @param array $payload {
     *     @type int $days_before Days before expiry to notify. Default 7.
     *     @type int $batch_size  Maximum licenses to process. Default 100.
     * }
     * @return array{notified: int, skipped: int, ineligible: int}
     */
    public function handle( array $payload ): mixed {
        $days_before = max( 1, (int) ( $payload['days_before'] ?? 7 ) );
        $batch_size  = max( 1, (int) ( $payload['batch_size']  ?? 100 ) );

        $db    = smliser_db();
        $table = SMLISER_LICENSE_TABLE;

        // Query licenses expiring in exactly the target window.
        // The window is: NOW() + days_before (inclusive of that day).
        $sql = "SELECT * FROM {$table}
                WHERE `status` = ?
                AND `end_date` IS NOT NULL
                AND DATE( `end_date` ) = DATE( NOW() + INTERVAL ? DAY )
                LIMIT ?";

        $rows = $db->get_results( $sql, [
            License::STATUS_ACTIVE,
            $days_before,
            $batch_size,
        ] );

        $notified   = 0;
        $skipped    = 0;
        $ineligible = 0;

        if ( empty( $rows ) ) {
            return compact( 'notified', 'skipped', 'ineligible' );
        }

        foreach ( $rows as $row ) {
            $license    = License::from_array( $row );
            $service_id = $license->get_service_id();

            // Only send when service_id is a valid email address.
            if ( ! filter_var( $service_id, FILTER_VALIDATE_EMAIL ) ) {
                $ineligible++;
                continue;
            }

            if ( $license->get_end_date() === null ) {
                $ineligible++;
                continue;
            }

            $message = ( new LicenseExpiryReminderEmail(
                $license,
                $service_id,
                $days_before
            ) )->to_message();

            if ( $message === null ) {
                // Template is disabled — skip silently.
                $skipped++;
                continue;
            }

            try {
                smliser_mailer()->send( $message );
                $notified++;
            } catch ( \Throwable ) {
                $skipped++;
            }
        }

        // If there may be more licenses in this window, dispatch another batch.
        if ( count( $rows ) === $batch_size ) {
            $this->dispatch_job(
                static::class,
                [
                    'days_before' => $days_before,
                    'batch_size'  => $batch_size,
                ],
            );
        }

        return compact( 'notified', 'skipped', 'ineligible' );
    }
}