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

	/**
	 * State file name, inside the storage directory.
	 */
	public const FILE = 'state.json';

	/**
	 * Class constructor.
	 *
	 * @param string $file Absolute path to the state file.
	 */
	public function __construct( private readonly string $file ) {}

	/**
	 * Create the state for the standard runtime layout.
	 *
	 * @return self
	 */
	public static function from_runtime() : self {
		return new self( rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/' . self::FILE );
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
		if ( ! is_file( $this->file ) ) {
			return null;
		}

		$raw = @file_get_contents( $this->file );

		if ( false === $raw ) {
			return false;
		}

		try {
			$data = json_decode( $raw, true, 64, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			return false;
		}

		return is_array( $data ) ? $data : false;
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
	 * Written atomically (temporary file + rename) under an exclusive lock.
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

		$this->write( $state );
	}

	/**
	 * Atomically write the state file.
	 *
	 * @param array<string, mixed> $state State to write.
	 * @return void
	 * @throws \RuntimeException When the state cannot be written.
	 */
	private function write( array $state ) : void {
		$dir = dirname( $this->file );

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			throw new \RuntimeException( "The storage directory \"{$dir}\" is not writable." );
		}

		$lock_file = $this->file . '.lock';
		$lock      = fopen( $lock_file, 'c' );

		if ( false === $lock || ! flock( $lock, LOCK_EX ) ) {
			throw new \RuntimeException( "Could not lock \"{$this->file}\"." );
		}

		try {
			$temp = $this->file . '.' . bin2hex( random_bytes( 6 ) ) . '.tmp';
			$json = json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

			if ( false === file_put_contents( $temp, $json ) || ! rename( $temp, $this->file ) ) {
				@unlink( $temp );
				throw new \RuntimeException( "Could not write \"{$this->file}\"." );
			}
		} finally {
			// Remove the lock file so it does not linger in storage. A writer
			// racing on a fresh lock file writes the same state, so this is safe.
			@unlink( $lock_file );
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}
}