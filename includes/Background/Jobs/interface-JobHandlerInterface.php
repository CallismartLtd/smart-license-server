<?php
/**
 * Job handler interface file.
 *
 * @package SmartLicenseServer\Background
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Contract that every background job handler must fulfil.
 *
 * A handler is the executable unit — the function that does the
 * actual work. It is intentionally decoupled from the JobDTO;
 * the DTO is the envelope, the handler is the delivery person.
 *
 * The worker resolves the handler class from JobDTO::$job_class,
 * instantiates it, and calls handle() with the job's payload array.
 *
 * ## Implementing a handler
 *
 *   class SendLicenseExpiryEmailJob implements JobHandlerInterface {
 *
 *       public function handle( array $payload ): mixed {
 *           $license  = License::find( $payload['license_id'] );
 *           $template = new LicenseExpiryReminderEmail(
 *               $license,
 *               $payload['recipient'],
 *               $payload['days_left']
 *           );
 *           $message = $template->to_message();
 *           if ( ! $message ) {
 *               return false;
 *           }
 *           $response = smliser_mailer()->send( $message );
 *           return $response->is_success();
 *       }
 *
 *       public static function get_job_name(): string {
 *           return 'Send License Expiry Email';
 *       }
 *
 *       public static function get_job_description(): string {
 *           return 'Sends an expiry reminder email to a licensee.';
 *       }
 *   }
 *
 * ## Dispatching
 *
 *   smliser_job_queue()->enqueue(
 *       JobDTO::make(
 *           job_class : SendLicenseExpiryEmailJob::class,
 *           payload   : [
 *               'license_id' => 42,
 *               'recipient'  => 'user@example.com',
 *               'days_left'  => 7,
 *           ],
 *       )
 *   );
 */
interface JobHandlerInterface {

    /**
     * Execute the job with the given payload.
     *
     * The payload is the exact array stored on the JobDTO — whatever
     * was passed to JobDTO::make( payload: [...] ) at dispatch time.
     *
     * Return any value that is meaningful to the caller; the worker
     * will capture it and store it on JobDTO::$result. Return null
     * if there is no meaningful result.
     *
     * Throw any \Throwable to signal failure. The worker will catch
     * it, record the message on JobDTO::$error_message, increment
     * JobDTO::$attempts, and either requeue (retrying) or archive
     * (failed) the job depending on whether max_attempts is exceeded.
     *
     * @param array<string, mixed> $payload The handler-specific argument array.
     * @return mixed                        Any result value, or null.
     * @throws \Throwable                   On any unrecoverable failure.
     */
    public function handle( array $payload = [] ): mixed;

    /**
     * Return a short human-readable name for this job type.
     *
     * Displayed in admin job lists and log entries.
     * Example: 'Send License Expiry Email', 'Generate Monthly Report'.
     *
     * @return string
     */
    public static function get_job_name(): string;

    /**
     * Return a one-sentence description of what this job does.
     *
     * Displayed alongside the name in the admin job list UI.
     * Example: 'Sends an expiry reminder email to a licensee.'
     *
     * @return string
     */
    public static function get_job_description(): string;
}