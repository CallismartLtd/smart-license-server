<?php
/**
 * Boot mode resolver class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Resolves the boot mode from files only; it never touches the database.
 *
 * Resolution order (first match wins):
 *  1. Maintenance flag with reason "installation", while not installed
 *     → Installation (an installation is in progress). Once installed,
 *     a leftover installation flag is ignored.
 *  2. Any other maintenance flag → Maintenance.
 *  3. Installation state missing, or without installed_at → Installation.
 *  4. Normal.
 *
 * Fails closed: an unreadable state file resolves to Maintenance, never to
 * Installation or Normal.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
final class BootModeResolver {

	/**
	 * Default Retry-After in seconds for maintenance.
	 */
	public const DEFAULT_RETRY_AFTER = 300;

	/**
	 * Retry-After in seconds for visitors during an installation.
	 */
	public const INSTALLATION_RETRY_AFTER = 120;

	/**
	 * Resolved mode, cached for the lifetime of the request.
	 *
	 * @var BootMode|null
	 */
	private ?BootMode $mode = null;

	/**
	 * Whether an installation flag marks an installation in progress.
	 *
	 * @var bool
	 */
	private bool $installation_in_progress = false;

	/**
	 * Visitor-facing message for the resolved mode.
	 *
	 * @var string
	 */
	private string $message = '';

	/**
	 * Retry-After seconds for the resolved mode.
	 *
	 * @var int
	 */
	private int $retry_after = self::DEFAULT_RETRY_AFTER;

	/**
	 * Class constructor.
	 *
	 * @param InstallationState $state The installation state.
	 * @param MaintenanceFlag   $flag  The maintenance flag.
	 */
	public function __construct(
		private readonly InstallationState $state,
		private readonly MaintenanceFlag $flag
	) {}

	/**
	 * Create a resolver for the standard runtime layout.
	 *
	 * @param FileSystem $fs Filesystem API.
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self( InstallationState::from_runtime( $fs ), MaintenanceFlag::from_runtime( $fs ) );
	}

	/**
	 * Resolve the boot mode.
	 *
	 * @return BootMode
	 */
	public function resolve() : BootMode {
		return $this->mode ??= $this->detect();
	}

	/**
	 * Discard the cached mode so the next resolve() re-reads the files.
	 *
	 * @return void
	 */
	public function refresh() : void {
		$this->mode                     = null;
		$this->installation_in_progress = false;
		$this->message                  = '';
		$this->retry_after              = self::DEFAULT_RETRY_AFTER;
	}

	/**
	 * Force Maintenance for a state the application cannot safely run in.
	 *
	 * @param string $reason Internal reason, logged; never shown to visitors.
	 * @return BootMode
	 */
	public function fail_closed( string $reason ) : BootMode {
		\smliser_log_error( '[BootModeResolver] Failing closed to maintenance: ' . $reason );

		$this->installation_in_progress = false;
		$this->message                  = sprintf( '%s is temporarily unavailable. Please try again shortly.', \SMLISER_APP_NAME );
		$this->retry_after              = self::DEFAULT_RETRY_AFTER;

		return $this->mode = BootMode::Maintenance;
	}

	/**
	 * Whether an installation has been started (installation flag present).
	 *
	 * @return bool
	 */
	public function installation_in_progress() : bool {
		$this->resolve();
		return $this->installation_in_progress;
	}

	/**
	 * The installation state this resolver reads.
	 *
	 * @return InstallationState
	 */
	public function state() : InstallationState {
		return $this->state;
	}

	/**
	 * The maintenance flag this resolver reads.
	 *
	 * @return MaintenanceFlag
	 */
	public function flag() : MaintenanceFlag {
		return $this->flag;
	}

	/**
	 * Visitor-facing message for the resolved mode.
	 *
	 * @return string
	 */
	public function message() : string {
		$this->resolve();
		return $this->message;
	}

	/**
	 * Retry-After seconds for the resolved mode.
	 *
	 * @return int
	 */
	public function retry_after() : int {
		$this->resolve();
		return $this->retry_after;
	}

	/*
	|-----------
	| Detection
	|-----------
	*/

	/**
	 * Run the checks in order.
	 *
	 * @return BootMode
	 */
	private function detect() : BootMode {
		$flag = $this->flag->read();

		if ( null !== $flag ) {
			$is_installation = MaintenanceFlag::REASON_INSTALLATION === ( $flag['reason'] ?? null );

			if ( ! $is_installation ) {
				return $this->maintenance( $flag );
			}

			if ( ! $this->state->is_installed() ) {
				$this->installation_in_progress = true;
				return $this->installation();
			}

			// Leftover installation flag after a completed install: ignore it.
		}

		$state = $this->state->read();

		if ( false === $state ) {
			return $this->fail_closed( 'The installation state could not be read.' );
		}

		if ( null === $state || empty( $state['installed_at'] ) ) {
			return $this->installation();
		}

		return BootMode::Normal;
	}

	/**
	 * Resolve Installation, with the message shown to visitors meanwhile.
	 *
	 * @return BootMode
	 */
	private function installation() : BootMode {
		$this->message     = sprintf(
			'We are setting things up behind the scenes. %s will be ready shortly, please check back in a few minutes.',
			\SMLISER_APP_NAME
		);
		$this->retry_after = self::INSTALLATION_RETRY_AFTER;

		return BootMode::Installation;
	}

	/**
	 * Resolve Maintenance from the flag's content.
	 *
	 * @param array<string, mixed> $flag Parsed flag; empty when unreadable.
	 * @return BootMode
	 */
	private function maintenance( array $flag ) : BootMode {
		$this->message = isset( $flag['message'] ) && is_string( $flag['message'] ) && '' !== $flag['message']
			? $flag['message']
			: sprintf( '%s is undergoing scheduled maintenance. Please try again shortly.', \SMLISER_APP_NAME );

		if ( isset( $flag['retry_after'] ) && is_numeric( $flag['retry_after'] ) ) {
			$this->retry_after = max( 0, (int) $flag['retry_after'] );
		}

		return BootMode::Maintenance;
	}
}