<?php
/**
 * Maintenance flag class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Owns the maintenance flag file (storage/maintenance.json).
 *
 * Any run that takes the site down for the web writes it: maintenance,
 * upgrades and installations. Its reason tells them apart:
 *
 *   { "reason": "maintenance", "message": "...", "retry_after": 300 }
 *   { "reason": "installation" }
 *
 * A flag without a reason is treated as maintenance.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
final class MaintenanceFlag {
	use JsonFileTrait;

	/**
	 * Flag file name, inside the storage directory.
	 */
	public const FILE = 'maintenance.json';

	/**
	 * Reason for scheduled maintenance and upgrades.
	 */
	public const REASON_MAINTENANCE = 'maintenance';

	/**
	 * Reason for an installation in progress.
	 */
	public const REASON_INSTALLATION = 'installation';

	/**
	 * Class constructor.
	 *
	 * @param FileSystem $fs   Filesystem API.
	 * @param string     $file Absolute path to the flag file.
	 */
	public function __construct(
		FileSystem $fs,
		private readonly string $file
	) {
		$this->fs	= $fs;
	}

	/**
	 * Create the flag for the standard runtime layout.
	 *
	 * @param FileSystem $fs Filesystem API.
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self( $fs, rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/' . self::FILE );
	}

	/**
	 * Absolute path to the flag file.
	 *
	 * @return string
	 */
	public function path() : string {
		return $this->file;
	}

	/**
	 * Read the flag.
	 *
	 * @return array<string, mixed>|null Null when no flag exists; an empty array
	 *                                   for a flag that exists but cannot be parsed.
	 */
	public function read() : ?array {
		$flag = $this->read_json( $this->file );

		return false === $flag ? array() : $flag;
	}

	/**
	 * The flag's reason, or null when no flag exists.
	 *
	 * @return string|null
	 */
	public function reason() : ?string {
		$flag = $this->read();

		if ( null === $flag ) {
			return null;
		}

		return is_string( $flag['reason'] ?? null ) ? $flag['reason'] : self::REASON_MAINTENANCE;
	}

	/**
	 * Whether the flag marks an installation in progress.
	 *
	 * @return bool
	 */
	public function is_installation() : bool {
		return self::REASON_INSTALLATION === $this->reason();
	}

	/**
	 * Write the flag, atomically.
	 *
	 * @param string $reason      One of the REASON_* constants.
	 * @param string $message     Optional visitor-facing message.
	 * @param int    $retry_after Optional Retry-After seconds; 0 omits it.
	 * @return void
	 * @throws \RuntimeException When the flag cannot be written.
	 */
	public function write( string $reason, string $message = '', int $retry_after = 0 ) : void {
		$this->write_json(
			$this->file,
			array_filter(
				array(
					'reason'      => $reason,
					'message'     => $message,
					'retry_after' => $retry_after,
					'since'       => gmdate( DATE_ATOM ),
				)
			)
		);
	}

	/**
	 * Remove the flag.
	 *
	 * @return void
	 */
	public function clear() : void {
		$this->delete_file( $this->file );
	}
}