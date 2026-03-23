<?php
/**
 * Notify app update job class file.
 *
 * Dispatched when an existing app's zip file is updated. Queries all
 * active licenses for the app and sends NewAppVersionNotificationEmail
 * to each licensee whose service_id is a valid email address.
 *
 * Triggered from: AbstractHostedApp::save() — update branch,
 * after successful zip upload. Dispatched alongside SendAppUpdatedEmailJob.
 *
 * Payload:
 *   app_type   (string) — e.g. 'plugin', 'theme', 'software'.
 *   app_slug   (string) — The app slug.
 *   batch_size (int)    — Max licenses per run. Default 100.
 *   offset     (int)    — Pagination offset for batch continuation. Default 0.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Apps
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Apps;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Email\Templates\Apps\NewAppVersionNotificationEmail;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Monetization\License;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Notifies active licensees when a new app version is available.
 */
class NotifyAppUpdateJob implements JobHandlerInterface {
    use QueueAwareTrait;

    public static function get_job_name(): string {
        return 'notify_app_update';
    }

    public static function get_job_description(): string {
        return 'Sends new version notification emails to all active licensees of an app.';
    }

    /**
     * @param array $payload {
     *     @type string $app_type   App type e.g. 'plugin'.
     *     @type string $app_slug   App slug.
     *     @type int    $batch_size Max licenses to process per run. Default 100.
     *     @type int    $offset     Pagination offset. Default 0.
     * }
     * @return array{notified: int, skipped: int, ineligible: int}
     */
    public function handle( array $payload ): mixed {
        $app_type   = $payload['app_type']   ?? '';
        $app_slug   = $payload['app_slug']   ?? '';
        $batch_size = max( 1, (int) ( $payload['batch_size'] ?? 100 ) );
        $offset     = max( 0, (int) ( $payload['offset']     ?? 0 ) );

        $notified   = 0;
        $skipped    = 0;
        $ineligible = 0;

        $app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( ! $app ) {
            return compact( 'notified', 'skipped', 'ineligible' );
        }

        $db        = smliser_db();
        $table     = SMLISER_LICENSE_TABLE;
        $app_prop  = sprintf( '%s/%s', $app_type, $app_slug );

        $terminal = [
            License::STATUS_EXPIRED,
            License::STATUS_REVOKED,
            License::STATUS_SUSPENDED,
        ];

        $placeholders = implode( ', ', array_fill( 0, count( $terminal ), '?' ) );

        $sql = "SELECT * FROM {$table}
                WHERE `app_prop` = ?
                AND `status` NOT IN ( {$placeholders} )
                LIMIT ? OFFSET ?";

        $rows = $db->get_results(
            $sql,
            array_merge( [ $app_prop ], $terminal, [ $batch_size, $offset ] )
        );

        foreach ( $rows as $row ) {
            $license    = License::from_array( $row );
            $service_id = $license->get_service_id();

            if ( ! filter_var( $service_id, FILTER_VALIDATE_EMAIL ) ) {
                $ineligible++;
                continue;
            }

            $message = ( new NewAppVersionNotificationEmail( $app, $license, $service_id ) )->to_message();

            if ( $message === null ) {
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

        // Dispatch next batch if there may be more.
        if ( count( $rows ) === $batch_size ) {
            $this->dispatch_job(
                static::class,
                [
                    'app_type'   => $app_type,
                    'app_slug'   => $app_slug,
                    'batch_size' => $batch_size,
                    'offset'     => $offset + $batch_size,
                ]
            );
        }

        return compact( 'notified', 'skipped', 'ineligible' );
    }
}