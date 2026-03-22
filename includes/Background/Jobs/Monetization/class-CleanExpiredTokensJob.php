<?php
/**
 * Clean expired tokens job class file.
 *
 * Deletes expired download tokens from the token table.
 * Replaces the WordPress smliser_clean cron hook for this specific
 * operation — runs in the background job queue so it works across
 * all environments, not just WordPress.
 *
 * Designed to run every 4 hours via the Scheduler.
 *
 * Payload: none.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Monetization
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Monetization;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Deletes all expired download tokens from the database.
 */
class CleanExpiredTokensJob implements JobHandlerInterface {

    /*
    |--------------------------------------------
    | JobHandlerInterface
    |--------------------------------------------
    */

    public static function get_job_name(): string {
        return 'clean_expired_tokens';
    }

    public static function get_job_description(): string {
        return 'Deletes expired download tokens from the token table.';
    }

    /**
     * Execute the job.
     *
     * Uses a single DELETE query — no PHP-side iteration, no object
     * loading. Compares expiry against the current Unix timestamp so
     * the check is consistent with how DownloadToken::is_expired()
     * evaluates tokens at runtime.
     *
     * @param array $payload Unused.
     * @return array{deleted: int}
     */
    public function handle( array $payload ): mixed {
        $db    = smliser_db();
        $table = SMLISER_APP_DOWNLOAD_TOKEN_TABLE;

        $db->query( "DELETE FROM {$table} WHERE `expiry` < ?", [ time() ] );

        return [ 'deleted' => $db->rows_affected() ];
    }
}