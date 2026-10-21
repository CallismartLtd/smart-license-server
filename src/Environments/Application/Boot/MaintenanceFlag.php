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
 * Maintenance switched on by an operator (see MaintenanceMode) also records
 * who switched it on, the hash of its bypass token, and whether background
 * work is paused meanwhile:
 *
 *   { "reason": "maintenance", "since": "...", "by": "...",
 *     "bypass": "<sha256 of the token>", "background": "paused" }
 *
 * A maintenance flag without "background" (written by hand, or by an older
 * version) leaves background work running, as before.
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
	 * An application update is being applied (see Update\Updater).
	 *
	 * Resolves to Maintenance like any reason other than installation.
	 *
	 * @var string
	 */
	public const REASON_UPDATE = 'update';

	/**
	 * Background work waits while the flag is set.
	 */
	public const BACKGROUND_PAUSED = 'paused';

	/**
	 * Background work carries on while the flag is set.
	 */
	public const BACKGROUND_RUNNING = 'running';

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
		$this->fs = $fs;
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

		return false === $flag ? [] : $flag;
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
				[
					'reason'      => $reason,
					'message'     => $message,
					'retry_after' => $retry_after,
					'since'       => gmdate( DATE_ATOM ),
				]
			)
		);
	}

	/**
	 * Write a maintenance flag an operator switched on, atomically.
	 *
	 * @param string $message          Visitor-facing message; '' for the default.
	 * @param int    $retry_after      Retry-After seconds; 0 omits it.
	 * @param string $bypass_hash      SHA-256 of the bypass token (see MaintenanceBypass).
	 * @param bool   $pause_background Whether scheduled tasks and queued jobs wait meanwhile.
	 * @param string $by               Who switched it on, for the status.
	 * @return void
	 * @throws \RuntimeException When the flag cannot be written.
	 */
	public function write_maintenance( string $message, int $retry_after, string $bypass_hash, bool $pause_background, string $by = '' ) : void {
		$this->write_json(
			$this->file,
			array_filter(
				[
					'reason'      => self::REASON_MAINTENANCE,
					'message'     => $message,
					'retry_after' => $retry_after,
					'since'       => gmdate( DATE_ATOM ),
					'by'          => $by,
					'bypass'      => $bypass_hash,
					'background'  => $pause_background ? self::BACKGROUND_PAUSED : self::BACKGROUND_RUNNING,
				]
			)
		);
	}

	/**
	 * Put back a flag exactly as read() returned it, every field included.
	 *
	 * For a process that replaced the flag for a while (an update) and
	 * gives the previous one back.
	 *
	 * @param array<string, mixed> $flag A read() result.
	 * @return void
	 * @throws \RuntimeException When the flag cannot be written.
	 */
	public function restore( array $flag ) : void {
		$this->write_json( $this->file, $flag );
	}

	/**
	 * Whether maintenance asks background work to wait.
	 *
	 * Only a maintenance flag that says so: an update pauses background work
	 * on its own terms (see CodeChangeDetector), an installation has none.
	 *
	 * @return bool
	 */
	public function background_paused() : bool {
		$flag = $this->read();

		return null !== $flag
			&& self::REASON_MAINTENANCE === $this->reason()
			&& self::BACKGROUND_PAUSED === ( $flag['background'] ?? self::BACKGROUND_RUNNING );
	}

	/**
	 * The stored hash of the bypass token, when maintenance has one.
	 *
	 * @return string|null
	 */
	public function bypass_hash() : ?string {
		$flag = $this->read();

		if ( null === $flag || self::REASON_MAINTENANCE !== $this->reason() ) {
			return null;
		}

		return is_string( $flag['bypass'] ?? null ) && '' !== $flag['bypass'] ? $flag['bypass'] : null;
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
