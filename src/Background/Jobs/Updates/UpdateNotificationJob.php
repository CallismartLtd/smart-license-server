<?php
/**
 * Update notification job class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Updates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Background\Jobs\Updates;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Email\Templates\System\UpdateAvailableEmail;
use SmartLicenseServer\Email\Templates\System\UpdateEmail;
use SmartLicenseServer\Email\Templates\System\UpdateFailedEmail;
use SmartLicenseServer\Email\Templates\System\UpdateInstalledEmail;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Emails the site administration address about an update.
 *
 * Queued by UpdateService (see its notify_* methods), which decides when an
 * email is due. The "installed" email is queued by the finish step, which
 * runs on the new code, so this job runs in a worker started with the new
 * version (the worker stops itself once state.json records a new version).
 */
class UpdateNotificationJob implements JobHandlerInterface {

	/**
	 * Events.
	 */
	public const EVENT_AVAILABLE = 'available';
	public const EVENT_INSTALLED = 'installed';
	public const EVENT_FAILED    = 'failed';

	/**
	 * Constructor.
	 *
	 * @param Mailer     $mailer     Sends the email.
	 * @param URLManager $urlmanager Builds the Updates page URL.
	 * @param Settings   $settings   Holds the administration email.
	 */
	public function __construct(
		protected Mailer $mailer,
		protected URLManager $urlmanager,
		protected Settings $settings
	) {}

	/**
	 * {@inheritdoc}
	 *
	 * Expected payload keys:
	 *   - event           (string) One of the EVENT_* constants.
	 *   - current_version (string) Version installed before the event.
	 *   - new_version     (string) Release the email is about.
	 *   - security        (bool)   Whether it is a security release.
	 *   - detail          (string) Event detail: why it is not installed automatically, or the failure output.
	 *   - package         (array)  {source, sha256, signed_by}, for "installed".
	 *   - action          (string) "install" or "rollback", for "failed".
	 *
	 * @param array<string, mixed> $payload
	 * @return array|bool The mailer response, or true when nothing was sent (no address, or the email is switched off).
	 */
	public function handle( array $payload = [] ): mixed {
		$to = trim( (string) $this->settings->get( Settings::ADMIN_EMAIL, '' ) );

		if ( '' === $to ) {
			return true;
		}

		$email = $this->email( $to, $payload );

		if ( null === $email ) {
			return true;
		}

		$message = $email->to_message();

		// Switched off in the email template settings.
		if ( null === $message ) {
			return true;
		}

		return $this->mailer->send( $message )->to_array();
	}

	/**
	 * The email for an event.
	 *
	 * @param string $to      Recipient.
	 * @param array  $payload Job payload.
	 * @return UpdateEmail|null Null for an unknown event.
	 */
	protected function email( string $to, array $payload ): ?UpdateEmail {
		$url      = (string) $this->urlmanager->admin_tools_page_url( 'updates' );
		$current  = (string) ( $payload['current_version'] ?? '' );
		$new      = (string) ( $payload['new_version'] ?? '' );
		$security = ! empty( $payload['security'] );
		$detail   = (string) ( $payload['detail'] ?? '' );

		return match ( $payload['event'] ?? null ) {
			self::EVENT_AVAILABLE => new UpdateAvailableEmail( $to, $current, $new, $security, $url, $detail ),
			self::EVENT_INSTALLED => new UpdateInstalledEmail( $to, $current, $new, $security, $url, $detail, (array) ( $payload['package'] ?? array() ) ),
			self::EVENT_FAILED    => new UpdateFailedEmail( $to, $current, $new, $security, $url, $detail, 'rollback' === ( $payload['action'] ?? '' ) ? 'rollback' : 'install' ),
			default               => null,
		};
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name(): string {
		return 'Send Update Notification';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description(): string {
		return 'Emails the site administration address when an update is available, installed automatically, or failed.';
	}
}