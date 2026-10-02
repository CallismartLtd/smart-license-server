<?php
/**
 * Setup token class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use SmartLicenseServer\Environments\Application\Boot\JsonFileTrait;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * One-time token proving server access before the web installer can be claimed.
 *
 * Stored in plain text in storage/setup/setup-token.json with the
 * application's normal file permissions, so the operator can read it through
 * File Manager, FTP or SSH (often as a different system user than the web
 * server), or print it with `smliser installer token`.
 * Deleted when the installation completes.
 *
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */
final class SetupToken {
	use JsonFileTrait;

	/**
	 * Token file name, inside the setup directory.
	 */
	public const FILE = 'setup-token.json';

	/**
	 * Token lifetime in seconds.
	 */
	public const TTL = 86400;

	/**
	 * Class constructor.
	 *
	 * @param FileSystem $fs   Filesystem API.
	 * @param string     $file Absolute path to the token file.
	 */
	public function __construct(
		FileSystem $fs,
		private readonly string $file
	) {
		$this->fs	= $fs;
	}

	/**
	 * Create the token for the standard runtime layout.
	 *
	 * @param FileSystem $fs Filesystem API.
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self( $fs, rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/setup/' . self::FILE );
	}

	/**
	 * Absolute path to the token file.
	 *
	 * @return string
	 */
	public function path() : string {
		return $this->file;
	}

	/**
	 * Get the current token, creating a new one when missing or expired.
	 *
	 * @return string
	 * @throws \RuntimeException When the token file cannot be written.
	 */
	public function get() : string {
		$record = $this->valid_record();

		if ( null !== $record ) {
			return $record['token'];
		}

		$token = bin2hex( random_bytes( 16 ) );

		$this->write_json(
			$this->file,
			array(
				'token'      => $token,
				'expires_at' => time() + self::TTL,
			)
		);

		return $token;
	}

	/**
	 * Unix time the current token expires, or null when there is none.
	 *
	 * @return int|null
	 */
	public function expires_at() : ?int {
		$record = $this->valid_record();

		return null === $record ? null : (int) $record['expires_at'];
	}

	/**
	 * Check a submitted token against the current one.
	 *
	 * @param string $input Submitted token.
	 * @return bool
	 */
	public function verify( string $input ) : bool {
		$record = $this->valid_record();

		return null !== $record && '' !== $input && hash_equals( $record['token'], trim( $input ) );
	}

	/**
	 * Delete the token.
	 *
	 * @return void
	 */
	public function delete() : void {
		$this->delete_file( $this->file );
	}

	/**
	 * The stored token record when present and unexpired.
	 *
	 * @return array{token: string, expires_at: int}|null
	 */
	private function valid_record() : ?array {
		$record = $this->read_json( $this->file ) ?: null;

		if (
			null === $record
			|| ! is_string( $record['token'] ?? null )
			|| '' === $record['token']
			|| (int) ( $record['expires_at'] ?? 0 ) <= time()
		) {
			return null;
		}

		return $record;
	}
}