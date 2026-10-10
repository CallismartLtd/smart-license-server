<?php
/**
 * Installation state class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Owns the installation state file (storage/state.json).
 *
 * The file is written by the application, never shipped:
 *
 *   {
 *     "installed_at": "2026-10-01T14:30:00+00:00",
 *     "versions": { "app": "0.2.0", "schema": "0.2.0" },
 *     "update": {
 *       "check": { … the last update check … },
 *       "ready": { … a downloaded, verified package waiting to be installed … },
 *       "run":   { … the last update: its stage, and what it moved … }
 *     }
 *   }
 *
 * Its presence (with installed_at) is the per-request "installed" check.
 *
 * Every change is a locked read-change-write (see change()), so the web,
 * the scheduler, the queue worker and the console can all write their part
 * without losing each other's, and every write is atomic, so a reader never
 * sees a partial file.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
final class InstallationState {
	use JsonFileTrait;

	/**
	 * State file name, inside the storage directory.
	 */
	public const FILE = 'state.json';

	/**
	 * Sections of the "update" entry.
	 */
	public const UPDATE_CHECK    = 'check';
	public const UPDATE_READY    = 'ready';
	public const UPDATE_RUN      = 'run';
	public const UPDATE_NOTIFIED = 'notified';

	/**
	 * Class constructor.
	 *
	 * @param FileSystem $fs   Filesystem API.
	 * @param string     $file Absolute path to the state file.
	 */
	public function __construct(
		FileSystem $fs,
		private readonly string $file
	) {
		$this->fs	= $fs;
	}

	/**
	 * Create the state for the standard runtime layout.
	 *
	 * @param FileSystem $fs Filesystem API.
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self( $fs, rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/' . self::FILE );
	}

	/**
	 * Absolute path to the state file.
	 *
	 * @return string
	 */
	public function path() : string {
		return $this->file;
	}

	/**
	 * Read the state file.
	 *
	 * @return array<string, mixed>|false|null Decoded state; false if unreadable or invalid; null if missing.
	 */
	public function read() : array|false|null {
		return $this->read_json( $this->file );
	}

	/**
	 * Whether the state file records a completed installation.
	 *
	 * @return bool
	 */
	public function is_installed() : bool {
		$state = $this->read();

		return is_array( $state ) && ! empty( $state['installed_at'] );
	}

	/**
	 * Record a completed installation, or the versions after an update.
	 *
	 * Keeps the original installed_at and every other entry (update status).
	 *
	 * @param array<string, string> $versions Installed versions, e.g. [ 'app' => SMLISER_VER, 'schema' => SMLISER_DB_VER ].
	 * @return void
	 * @throws \RuntimeException When the state cannot be read or written.
	 */
	public function mark_installed( array $versions ) : void {
		$this->change(
			static function ( array $state ) use ( $versions ) : array {
				$state['installed_at'] = ! empty( $state['installed_at'] ) ? $state['installed_at'] : gmdate( DATE_ATOM );
				$state['versions']     = $versions;

				return $state;
			}
		);
	}

	/**
	 * A section of the update status.
	 *
	 * @param string $section One of the UPDATE_* constants.
	 * @return array|null Null when the section is not set (or the state cannot be read).
	 */
	public function update_section( string $section ) : ?array {
		$state = $this->read();
		$value = is_array( $state ) ? ( $state['update'][ $section ] ?? null ) : null;

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Set or remove a section of the update status.
	 *
	 * @param string     $section One of the UPDATE_* constants.
	 * @param array|null $value   The section; null removes it.
	 * @return void
	 * @throws \RuntimeException When the state cannot be read or written.
	 */
	public function set_update_section( string $section, ?array $value ) : void {
		$this->change(
			static function ( array $state ) use ( $section, $value ) : array {
				$update = is_array( $state['update'] ?? null ) ? $state['update'] : array();

				if ( null === $value ) {
					unset( $update[ $section ] );
				} else {
					$update[ $section ] = $value;
				}

				if ( empty( $update ) ) {
					unset( $state['update'] );
				} else {
					$state['update'] = $update;
				}

				return $state;
			}
		);
	}

	/**
	 * Apply a change to the state under an exclusive lock.
	 *
	 * The lock (a sibling ".lock" file) is held from the read to the write,
	 * so concurrent writers apply their changes one after the other instead
	 * of the last one silently dropping the others'. A state file that
	 * exists but cannot be read is never overwritten: that would erase the
	 * installation record the boot process depends on.
	 *
	 * @param callable( array $state ): array $change Receives the current state ([] when there is none), returns the new one.
	 * @return array The state written.
	 * @throws \RuntimeException When the state cannot be locked, read or written.
	 */
	public function change( callable $change ) : array {
		$lock = @fopen( $this->file . '.lock', 'c' );

		if ( false === $lock || ! flock( $lock, LOCK_EX ) ) {
			throw new \RuntimeException( sprintf( 'Could not lock "%s". Check that the storage directory is writable.', $this->file ) );
		}

		try {
			$current = $this->read();

			if ( false === $current ) {
				throw new \RuntimeException( sprintf( '"%s" is damaged and was left unchanged; repair or restore it first.', $this->file ) );
			}

			$state = $change( $current ?? array() );

			$this->write_json( $this->file, $state );

			return $state;
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}
}