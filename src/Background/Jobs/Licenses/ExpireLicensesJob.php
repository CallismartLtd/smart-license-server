<?php
/**
 * Expire licenses job class file.
 *
 * Finds all licenses whose end_date has passed and whose status is not
 * already terminal, marks them as expired in the database, and dispatches
 * a LicenseExpiredEmail to the licensee when the service_id is an email
 * address.
 *
 * Designed to run daily via the Scheduler.
 *
 * Payload:
 *   batch_size (int) — Number of licenses to process per run. Default 100.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Licenses
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Licenses;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Email\Templates\Licenses\LicenseExpiredEmail;
use SmartLicenseServer\Monetization\License;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Bulk-expire licenses past their end_date and notify licensees.
 */
class ExpireLicensesJob implements JobHandlerInterface {
    use QueueAwareTrait;

    /*
    |--------------------------------------------
    | JobHandlerInterface
    |--------------------------------------------
    */

    public static function get_job_name(): string {
        return 'expire_licenses';
    }

    public static function get_job_description(): string {
        return 'Marks licenses past their end date as expired and notifies licensees.';
    }

    /**
     * Execute the job.
     *
     * Queries licenses that have passed their end_date and are not
     * already in a terminal status, updates each to expired, and
     * dispatches a notification email when the service_id is a valid
     * email address.
     *
     * @param array $payload {
     *     @type int $batch_size Maximum licenses to process. Default 100.
     * }
     * @return array{expired: int, notified: int, skipped: int}
     */
    public function handle( array $payload = [] ): mixed {
        $batch_size = max( 1, (int) ( $payload['batch_size'] ?? 100 ) );
        $db         = smliser_db();
        $table      = SMLISER_LICENSE_TABLE;

        $terminal_statuses = [
            License::STATUS_EXPIRED,
            License::STATUS_REVOKED,
            License::STATUS_SUSPENDED,
            License::STATUS_LIFETIME,
        ];

        $placeholders = implode( ', ', array_fill( 0, count( $terminal_statuses ), '?' ) );

        $sql = "SELECT * FROM {$table}
                WHERE `end_date` < NOW()
                AND ( `status` NOT IN ( {$placeholders} ) OR `status` IS NULL OR `status` = '' )
                LIMIT ?";

        $params = array_merge( $terminal_statuses, [ $batch_size ] );
        $rows   = $db->get_results( $sql, $params );

        $expired   = 0;
        $notified  = 0;
        $skipped   = 0;

        if ( empty( $rows ) ) {
            return compact( 'expired', 'notified', 'skipped' );
        }

        foreach ( $rows as $row ) {
            $license = License::from_array( $row );

            // Update status in the database.
            $updated = $db->update(
                $table,
                [
                    'status'     => License::STATUS_EXPIRED,
                    'updated_at' => gmdate( 'Y-m-d H:i:s' ),
                ],
                [ 'id' => $license->get_id() ]
            );

            if ( false === $updated ) {
                $skipped++;
                continue;
            }

            $expired++;

            // Send notification if service_id is a valid email.
            $service_id = $license->get_service_id();

            if ( ! filter_var( $service_id, FILTER_VALIDATE_EMAIL ) ) {
                continue;
            }

            if ( $license->get_end_date() === null ) {
                continue;
            }

            $message = ( new LicenseExpiredEmail( $license, $service_id ) )->to_message();

            if ( $message === null ) {
                // Template is disabled — skip silently.
                continue;
            }

            try {
                smliser_mailer()->send( $message );
                $notified++;
            } catch ( \Throwable ) {
                // Email failure must never abort license expiry processing.
            }
        }

        // If there may be more licenses to process, dispatch another batch.
        if ( count( $rows ) === $batch_size ) {
            $this->dispatch_job(
                static::class,
                [ 'batch_size' => $batch_size ],
            );
        }

        return compact( 'expired', 'notified', 'skipped' );
    }
}