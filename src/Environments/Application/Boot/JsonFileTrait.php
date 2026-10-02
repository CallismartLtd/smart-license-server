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

use SmartLicenseServer\FileSystem\FileSystem;

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
	private FileSystem $fs;

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
	 * Write a JSON object file.
	 *
	 * Uses the FileSystem defaults: the application's file and directory
	 * permissions, and the adapter's atomic write (temporary file, then
	 * rename), which also creates missing parent directories.
	 *
	 * @param string               $file Absolute path.
	 * @param array<string, mixed> $data Data to write.
	 * @return void
	 * @throws \RuntimeException When the file cannot be written.
	 */
	private function write_json( string $file, array $data ) : void {
		$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

		if ( ! $this->fs->put_contents( $file, $json ) ) {
			throw new \RuntimeException( "Could not write \"{$file}\". Check that the storage directory is writable." );
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