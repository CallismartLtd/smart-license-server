<?php
/**
 * JSON file trait file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

/**
 * Reads, atomically writes and deletes small JSON state files through the
 * application's FileSystem API.
 *
 * The using class must hold the FileSystem instance in `$this->fs`.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
trait JsonFileTrait {

	/**
	 * Read a JSON object file.
	 *
	 * @param string $file Absolute path.
	 * @return array<string, mixed>|false|null The decoded object; false when the file
	 *                                         exists but cannot be read or parsed; null when missing.
	 */
	private function read_json( string $file ) : array|false|null {
		if ( ! $this->fs->is_file( $file ) ) {
			return null;
		}

		$raw = $this->fs->get_contents( $file );

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
	 * Atomically write a JSON object file (temporary file, then rename).
	 *
	 * @param string               $file      Absolute path.
	 * @param array<string, mixed> $data      Data to write.
	 * @param int                  $file_mode File permissions.
	 * @param int|false            $dir_mode  Permissions for a directory that must be created; false for the default.
	 * @return void
	 * @throws \RuntimeException When the file cannot be written.
	 */
	private function write_json( string $file, array $data, int $file_mode = \SMLISER_FILE_PERMISSION, int|false $dir_mode = false ) : void {
		$dir = dirname( $file );

		if ( ! $this->fs->is_dir( $dir ) && ! $this->fs->mkdir( $dir, $dir_mode, true ) ) {
			throw new \RuntimeException( "Could not create \"{$dir}\". Check that the storage directory is writable." );
		}

		$temp = $file . '.' . bin2hex( random_bytes( 6 ) ) . '.tmp';
		$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

		if ( ! $this->fs->put_contents( $temp, $json, $file_mode ) ) {
			throw new \RuntimeException( "Could not write \"{$file}\". Check that the storage directory is writable." );
		}

		if ( ! $this->fs->rename( $temp, $file ) ) {
			$this->fs->delete( $temp );
			throw new \RuntimeException( "Could not write \"{$file}\"." );
		}
	}

	/**
	 * Delete a file when it exists.
	 *
	 * @param string $file Absolute path.
	 * @return void
	 */
	private function delete_file( string $file ) : void {
		if ( $this->fs->is_file( $file ) ) {
			$this->fs->delete( $file );
		}
	}
}