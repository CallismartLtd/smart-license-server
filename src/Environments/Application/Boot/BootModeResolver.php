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

/**
 * Resolves the boot mode from files only; it never touches the database.
 *
 * Resolution order (first match wins):
 *  1. Maintenance   — maintenance flag file present. Written by any
 *                     maintenance, upgrade or installation run.
 *  2. Installation  — installation state missing, or without installed_at.
 *  3. Normal.
 *
 * Fails closed: an unreadable state file resolves to Maintenance, never to
 * Installation or Normal.
 *
 * maintenance.json (storage dir): { "message": "...", "retry_after": 300 } — both keys optional.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
final class BootModeResolver {

	/**
	 * Maintenance flag file name, inside the storage directory.
	 */
	public const MAINTENANCE_FILE = 'maintenance.json';

	/**
	 * Default Retry-After in seconds.
	 */
	public const DEFAULT_RETRY_AFTER = 300;

	/**
	 * Resolved mode, cached for the lifetime of the request.
	 *
	 * @var BootMode|null
	 */
	private ?BootMode $mode = null;

	/**
	 * Downtime message for the resolved mode.
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
	 * @param InstallationState $state            The installation state.
	 * @param string            $maintenance_file Absolute path to the maintenance flag file.
	 */
	public function __construct(
		private readonly InstallationState $state,
		private readonly string $maintenance_file
	) {}

	/**
	 * Create a resolver for the standard runtime layout.
	 *
	 * @return self
	 */
	public static function from_runtime() : self {
		return new self(
			InstallationState::from_runtime(),
			rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/' . self::MAINTENANCE_FILE
		);
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
		$this->mode        = null;
		$this->message     = '';
		$this->retry_after = self::DEFAULT_RETRY_AFTER;
	}

	/**
	 * Force Maintenance for a state the application cannot safely run in.
	 *
	 * @param string $reason Internal reason, logged; never shown to visitors.
	 * @return BootMode
	 */
	public function fail_closed( string $reason ) : BootMode {
		\smliser_log_error( '[BootModeResolver] Failing closed to maintenance: ' . $reason );

		$this->message     = sprintf( '%s is temporarily unavailable. Please try again shortly.', \SMLISER_APP_NAME );
		$this->retry_after = self::DEFAULT_RETRY_AFTER;

		return $this->mode = BootMode::Maintenance;
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
	 * User-facing downtime message for the resolved mode.
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
		if ( is_file( $this->maintenance_file ) ) {
			return $this->maintenance();
		}

		$state = $this->state->read();

		if ( false === $state ) {
			
			return $this->fail_closed( 'The installation state could not be read.' );
		}

		if ( null === $state || empty( $state['installed_at'] ) ) {
			$this->message = sprintf(
				'%s has not been installed yet. Run `smliser installer run` on the server to install it.',
				\SMLISER_APP_NAME
			);

			return BootMode::Installation;
		}

		return BootMode::Normal;
	}

	/**
	 * Resolve Maintenance from the flag file.
	 *
	 * An unreadable flag still means maintenance, with default notice values.
	 *
	 * @return BootMode
	 */
	private function maintenance() : BootMode {
		$raw  = @file_get_contents( $this->maintenance_file );
		$flag = false === $raw ? null : json_decode( $raw, true );
		$flag = is_array( $flag ) ? $flag : array();

		$this->message = isset( $flag['message'] ) && is_string( $flag['message'] ) && '' !== $flag['message']
			? $flag['message']
			: sprintf( '%s is undergoing scheduled maintenance. Please try again shortly.', \SMLISER_APP_NAME );

		if ( isset( $flag['retry_after'] ) && is_numeric( $flag['retry_after'] ) ) {
			$this->retry_after = max( 0, (int) $flag['retry_after'] );
		}

		return BootMode::Maintenance;
	}
}