<?php
/**
 * Maintenance mode class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Maintenance
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Maintenance;

use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Boot\BootModeResolver;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;

/**
 * Switches maintenance mode on and off for an operator: before a database
 * backup or import, a server move, or any work that needs the site quiet.
 *
 * Philosophy:
 *
 *  - Visitors wait, the operator works. Visitors get the 503 maintenance
 *    page with Retry-After; the operator keeps the console, and the site
 *    itself through the bypass link that switching on returns (see
 *    MaintenanceBypass).
 *  - Quiet by default. Scheduled tasks and queued jobs wait too, so
 *    nothing writes to the database during a backup or an import. Pass
 *    $pause_background false to keep them running.
 *  - Never over another owner. An update or an installation also takes the
 *    site down, and only they may bring it back: switching maintenance on
 *    or off during one is refused. An update that starts during
 *    maintenance gives the flag back as it found it.
 *  - One state, on disk. Everything lives in the maintenance flag, so the
 *    web, the console and the background runners all see the same thing.
 */
final class MaintenanceMode {

	/**
	 * Constructor.
	 *
	 * @param MaintenanceFlag $flag The maintenance flag.
	 * @param URLManager      $urls Builds the bypass link.
	 */
	public function __construct(
		private MaintenanceFlag $flag,
		private URLManager $urls
	) {}

	/**
	 * What takes the site down now, if anything.
	 *
	 * @return array{
	 *     active: bool,
	 *     reason: ?string,
	 *     operator: bool,
	 *     message: string,
	 *     retry_after: int,
	 *     since: ?string,
	 *     by: ?string,
	 *     background_paused: bool,
	 *     bypass: bool
	 * } operator is true for maintenance switched on with this class; reason is
	 *   a MaintenanceFlag::REASON_* constant.
	 */
	public function status() : array {
		$flag   = $this->flag->read();
		$reason = $this->flag->reason();

		return [
			'active'            => null !== $flag,
			'reason'            => $reason,
			'operator'          => null !== $this->flag->bypass_hash(),
			'message'           => \is_string( $flag['message'] ?? null ) ? $flag['message'] : '',
			'retry_after'       => \is_numeric( $flag['retry_after'] ?? null ) ? (int) $flag['retry_after'] : 0,
			'since'             => \is_string( $flag['since'] ?? null ) ? $flag['since'] : null,
			'by'                => \is_string( $flag['by'] ?? null ) && '' !== $flag['by'] ? $flag['by'] : null,
			'background_paused' => $this->flag->background_paused(),
			'bypass'            => null !== $this->flag->bypass_hash(),
		];
	}

	/**
	 * Take the site down for visitors.
	 *
	 * Switching on while already on starts a new maintenance with these
	 * settings and a new token: earlier bypass links stop working.
	 *
	 * @param string $message          Visitor-facing message; '' for the default one.
	 * @param int    $retry_after      Seconds visitors are told to wait (Retry-After).
	 * @param bool   $pause_background Whether scheduled tasks and queued jobs wait meanwhile.
	 * @param string $by               Who switches it on, for the status.
	 * @return string The bypass token. Only its hash is stored: show the link now.
	 * @throws \RuntimeException When an update or installation owns the flag, or it cannot be written.
	 */
	public function enable( string $message = '', int $retry_after = BootModeResolver::DEFAULT_RETRY_AFTER, bool $pause_background = true, string $by = '' ) : string {
		$this->assert_not_owned( 'switched on' );

		$token = MaintenanceBypass::new_token();

		$this->flag->write_maintenance( \trim( $message ), \max( 0, $retry_after ), MaintenanceBypass::hash( $token ), $pause_background, $by );

		return $token;
	}

	/**
	 * Make a new bypass token for the current maintenance; earlier links stop working.
	 *
	 * @return string The new token.
	 * @throws \RuntimeException When maintenance is not on, or not switched on by an operator.
	 */
	public function renew_bypass() : string {
		$status = $this->status();

		if ( ! $status['active'] || MaintenanceFlag::REASON_MAINTENANCE !== $status['reason'] ) {
			throw new \RuntimeException( 'Maintenance mode is not on.' );
		}

		$flag  = $this->flag->read() ?? [];
		$token = MaintenanceBypass::new_token();

		$this->flag->restore( [ 'bypass' => MaintenanceBypass::hash( $token ) ] + $flag );

		return $token;
	}

	/**
	 * Open the site to visitors again.
	 *
	 * @return bool False when maintenance was not on.
	 * @throws \RuntimeException When an update or installation owns the flag.
	 */
	public function disable() : bool {
		if ( null === $this->flag->read() ) {
			return false;
		}

		$this->assert_not_owned( 'switched off' );
		$this->flag->clear();

		return true;
	}

	/**
	 * The bypass link for a token: the admin dashboard, with the token.
	 *
	 * @param string $token
	 * @return string
	 */
	public function bypass_url( string $token ) : string {
		return (string) $this->urls->admin_url( '', '', [ MaintenanceBypass::QUERY => $token ] );
	}

	/**
	 * Refuse when an update or installation owns the maintenance flag.
	 *
	 * @param string $action What was asked, for the message.
	 * @return void
	 * @throws \RuntimeException
	 */
	private function assert_not_owned( string $action ) : void {
		$reason = $this->flag->reason();

		if ( MaintenanceFlag::REASON_UPDATE === $reason ) {
			throw new \RuntimeException( \sprintf( 'An update is in progress and owns maintenance mode, so it cannot be %s here. It ends maintenance itself; if it is stuck, finish or roll back the update.', $action ) );
		}

		if ( MaintenanceFlag::REASON_INSTALLATION === $reason ) {
			throw new \RuntimeException( \sprintf( 'An installation is in progress and owns maintenance mode, so it cannot be %s here.', $action ) );
		}
	}
}
