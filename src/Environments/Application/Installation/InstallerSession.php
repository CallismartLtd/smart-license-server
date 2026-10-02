<?php
/**
 * Installer session class file.
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
 * Single-owner session for the web installer.
 *
 * Handled manually because the application session (SessionManager) needs
 * SMLISER_SECRET, which does not exist until installation writes it. The
 * owner holds a random cookie; only its SHA-256 hash is stored, server-side,
 * in storage/setup/installer-session.json (0600), with a CSRF token and
 * activity timestamps.
 *
 * Exactly one owner at a time. A claim idle for longer than the idle
 * timeout is stale: it no longer blocks anyone and its cookie stops working,
 * so an abandoned installation can be claimed again with the setup token.
 *
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */
final class InstallerSession {
	use JsonFileTrait;

	/**
	 * Permissions for the setup directory.
	 */
	private const DIR_MODE = 0700;

	/**
	 * Permissions for the file.
	 */
	private const FILE_MODE = 0600;

	/**
	 * Session file name, inside the setup directory.
	 */
	public const FILE = 'installer-session.json';

	/**
	 * Cookie name.
	 */
	public const COOKIE = 'smliser_installer';

	/**
	 * Seconds of inactivity after which a claim is stale.
	 */
	public const IDLE_TIMEOUT = 1800;

	/**
	 * Class constructor.
	 *
	 * @param FileSystem $fs           Filesystem API.
	 * @param string     $file         Absolute path to the session file.
	 * @param int        $idle_timeout Seconds of inactivity after which a claim is stale.
	 */
	public function __construct(
		FileSystem $fs,
		private readonly string $file,
		private readonly int $idle_timeout = self::IDLE_TIMEOUT
	) {
		$this->fs	= $fs;
	}

	/**
	 * Create the session for the standard runtime layout.
	 *
	 * @param FileSystem $fs Filesystem API.
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self( $fs, rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/setup/' . self::FILE );
	}

	/**
	 * Whether someone holds an active (non-stale) claim.
	 *
	 * @return bool
	 */
	public function is_claimed() : bool {
		return null !== $this->active_record();
	}

	/**
	 * Whether the given cookie value belongs to the active claim.
	 *
	 * @param string|null $cookie The request's installer cookie value.
	 * @return bool
	 */
	public function is_owner( ?string $cookie ) : bool {
		$record = $this->active_record();

		return null !== $record
			&& is_string( $cookie )
			&& '' !== $cookie
			&& hash_equals( $record['session'], hash( 'sha256', $cookie ) );
	}

	/**
	 * Claim the installer for a new owner.
	 *
	 * @return string The cookie value to give the new owner.
	 * @throws \LogicException   When an active claim already exists.
	 * @throws \RuntimeException When the session cannot be written.
	 */
	public function claim() : string {
		if ( $this->is_claimed() ) {
			throw new \LogicException( 'The installer is already claimed.' );
		}

		$cookie = bin2hex( random_bytes( 32 ) );
		$now    = time();

		$this->write_json(
			$this->file,
			array(
				'session'    => hash( 'sha256', $cookie ),
				'csrf'       => bin2hex( random_bytes( 16 ) ),
				'claimed_at' => $now,
				'last_seen'  => $now,
			),
			self::FILE_MODE,
			self::DIR_MODE
		);

		return $cookie;
	}

	/**
	 * Record activity on the active claim, keeping it from going stale.
	 *
	 * @return void
	 */
	public function touch() : void {
		$record = $this->active_record();

		if ( null !== $record ) {
			$record['last_seen'] = time();
			$this->write_json( $this->file, $record, self::FILE_MODE, self::DIR_MODE );
		}
	}

	/**
	 * The active claim's CSRF token, or an empty string when unclaimed.
	 *
	 * @return string
	 */
	public function csrf() : string {
		return $this->active_record()['csrf'] ?? '';
	}

	/**
	 * Check a submitted CSRF token.
	 *
	 * @param mixed $input Submitted token.
	 * @return bool
	 */
	public function verify_csrf( mixed $input ) : bool {
		$csrf = $this->csrf();

		return '' !== $csrf && is_string( $input ) && hash_equals( $csrf, $input );
	}

	/**
	 * Store a one-time notice for the owner's next page.
	 *
	 * @param string $type    Notice type: "success", "warning" or "error".
	 * @param string $message Notice text.
	 * @return void
	 */
	public function flash( string $type, string $message ) : void {
		$record = $this->active_record();

		if ( null !== $record ) {
			$record['flash'][] = array( 'type' => $type, 'message' => $message );
			$this->write_json( $this->file, $record, self::FILE_MODE, self::DIR_MODE );
		}
	}

	/**
	 * Get and clear the stored notices.
	 *
	 * @return array<int, array{type: string, message: string}>
	 */
	public function pull_flash() : array {
		$record = $this->active_record();

		if ( null === $record || empty( $record['flash'] ) || ! is_array( $record['flash'] ) ) {
			return array();
		}

		$notices = $record['flash'];
		unset( $record['flash'] );
		$this->write_json( $this->file, $record, self::FILE_MODE, self::DIR_MODE );

		return array_values(
			array_filter(
				$notices,
				static fn( mixed $n ) : bool => is_array( $n ) && is_string( $n['type'] ?? null ) && is_string( $n['message'] ?? null )
			)
		);
	}

	/**
	 * End the claim.
	 *
	 * @return void
	 */
	public function release() : void {
		$this->delete_file( $this->file );
	}

	/**
	 * Seconds of inactivity after which a claim is stale.
	 *
	 * @return int
	 */
	public function idle_timeout() : int {
		return $this->idle_timeout;
	}

	/**
	 * The stored claim when present and not stale.
	 *
	 * @return array{session: string, csrf: string, claimed_at: int, last_seen: int, flash?: array}|null
	 */
	private function active_record() : ?array {
		$record = $this->read_json( $this->file ) ?: null;

		if (
			null === $record
			|| ! is_string( $record['session'] ?? null )
			|| ! is_string( $record['csrf'] ?? null )
			|| (int) ( $record['last_seen'] ?? 0 ) + $this->idle_timeout < time()
		) {
			return null;
		}

		return $record;
	}
}