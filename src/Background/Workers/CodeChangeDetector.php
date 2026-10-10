<?php
/**
 * Code change detector class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Workers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Workers;

use SmartLicenseServer\Environments\Application\Boot\InstallationState;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;

/**
 * Tells a long-running process that the code it loaded is no longer the code on disk.
 *
 * A PHP process keeps the classes it loaded at start-up and loads the rest
 * lazily from disk. After an update the two no longer match: old classes in
 * memory, new files on disk, and every class loaded from then on mixes the
 * two until something breaks. During the swap itself, a class may be loaded
 * from a half-replaced tree.
 *
 * So a daemon (the `queue work` worker) asks this class between jobs:
 *
 *  - REASON_CHANGED:  the code on disk is no longer this process's: an
 *    update swapped its files in (it may still be finishing), or
 *    state.json records another application version. Exit, so the
 *    process supervisor starts it again on the new code; that new
 *    process also finishes an update still waiting to be finished.
 *  - REASON_UPDATING: an update is swapping files (maintenance flag
 *    "update"). Load nothing; wait for it to end.
 *
 * Processes that start fresh for every run (cron, web-triggered workers)
 * do not need it.
 */
final class CodeChangeDetector {

	/**
	 * Reasons.
	 */
	public const REASON_UPDATING = 'updating';
	public const REASON_CHANGED  = 'changed';

	/**
	 * The version this process loaded.
	 *
	 * @var string
	 */
	private string $running_version;

	/**
	 * Constructor.
	 *
	 * @param InstallationState $state           The installation state (records the installed version).
	 * @param MaintenanceFlag   $flag            The maintenance flag (marks an update in progress).
	 * @param string|null       $running_version The version this process loaded; defaults to SMLISER_VER.
	 */
	public function __construct(
		private InstallationState $state,
		private MaintenanceFlag $flag,
		?string $running_version = null
	) {
		$this->running_version = $running_version ?? \SMLISER_VER;
	}

	/**
	 * Why this process should stop taking work, if it should.
	 *
	 * Reads two small files; cheap enough to call every second.
	 *
	 * @return string|null One of the REASON_* constants, or null to carry on.
	 */
	public function reason() : ?string {
		if ( MaintenanceFlag::REASON_UPDATE === $this->flag->reason() ) {
			$run = $this->update_run();

			// Swapped to another version: waiting would never end, since
			// finishing needs a process running the new code.
			return 'swapped' === ( $run['stage'] ?? null ) && $this->running_version !== ( $run['to'] ?? null )
				? self::REASON_CHANGED
				: self::REASON_UPDATING;
		}

		$installed = $this->installed_version();

		// An unreadable state file is not evidence of an update; carry on.
		if ( null !== $installed && $installed !== $this->running_version ) {
			return self::REASON_CHANGED;
		}

		return null;
	}

	/**
	 * The version this process loaded.
	 *
	 * @return string
	 */
	public function running_version() : string {
		return $this->running_version;
	}

	/**
	 * The update journal (state.json update.run), if there is one.
	 *
	 * @return array|null
	 */
	private function update_run() : ?array {
		$state = $this->state->read();
		$run   = is_array( $state ) ? ( $state['update']['run'] ?? null ) : null;

		return is_array( $run ) ? $run : null;
	}

	/**
	 * The application version state.json records, if it can be read.
	 *
	 * @return string|null
	 */
	public function installed_version() : ?string {
		$state   = $this->state->read();
		$version = is_array( $state ) ? ( $state['versions']['app'] ?? null ) : null;

		return is_string( $version ) && '' !== $version ? $version : null;
	}
}