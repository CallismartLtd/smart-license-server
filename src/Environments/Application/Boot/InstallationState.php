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
 *   { "installed_at": "2026-10-01T14:30:00+00:00", "versions": { "app": "0.2.0", "schema": "0.2.0" } }
 *
 * Its presence (with installed_at) is the per-request "installed" check.
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
	 * Class constructor.
	 *
	 * @param FileSystem $fs   Filesystem API.
	 * @param string     $file Absolute path to the state file.
	 */
	public function __construct(
		private readonly FileSystem $fs,
		private readonly string $file
	) {}

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
	 * Record a completed installation.
	 *
	 * Keeps the original installed_at when the state already exists.
	 * Written atomically (temporary file + rename), so readers never see a
	 * partial file; concurrent writers write the same state.
	 *
	 * @param array<string, string> $versions Installed versions, e.g. [ 'app' => SMLISER_VER, 'schema' => SMLISER_DB_VER ].
	 * @return void
	 * @throws \RuntimeException When the state cannot be written.
	 */
	public function mark_installed( array $versions ) : void {
		$current = $this->read();

		$state = array(
			'installed_at' => is_array( $current ) && ! empty( $current['installed_at'] )
				? $current['installed_at']
				: gmdate( DATE_ATOM ),
			'versions'     => $versions,
		);

		$this->write_json( $this->file, $state );
	}
}