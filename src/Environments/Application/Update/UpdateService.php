<?php
/**
 * UpdateService class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

use SmartLicenseServer\Background\Jobs\Updates\UpdateNotificationJob;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Environments\Application\Boot\InstallationState;
use SmartLicenseServer\Environments\Application\Release\ReleaseManifest;
use SmartLicenseServer\Environments\Application\Release\ReleaseSignature;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * What the console, the admin page, the scheduler and the update job share:
 * checking for updates, finding what blocks one, preparing a package, and
 * the automatic-update policy.
 *
 * The last check is kept in state.json (update.check), so every interface
 * shows the same result without asking the update server again; a dry run
 * keeps its verified package (update.ready) for a later install of the same
 * version.
 */
final class UpdateService {

	use UpdateProgressTrait;
	use QueueAwareTrait;

	/**
	 * Automatic update modes.
	 */
	public const AUTO_OFF      = 'off';
	public const AUTO_SECURITY = 'security';
	public const AUTO_ALL      = 'all';

	/**
	 * Settings key of the automatic update mode.
	 *
	 * @var string
	 */
	public const SETTING_AUTO = 'smliser_auto_update';

	/**
	 * How long a check result is reused before the server is asked again, in seconds.
	 *
	 * @var int
	 */
	public const CHECK_TTL = 43200;

	/**
	 * How long a queued automatic update is left alone before queueing it again, in seconds.
	 *
	 * @var int
	 */
	public const QUEUED_TTL = 86400;

	/**
	 * Days a backup of the previous version is kept.
	 *
	 * @var int
	 */
	public const BACKUP_DAYS = 14;

	/**
	 * Free disk space an update needs, in bytes.
	 *
	 * @var int
	 */
	public const MIN_FREE_BYTES = 209715200;

	/**
	 * Most characters of update output kept in a failure email (the end is kept).
	 *
	 * @var int
	 */
	private const NOTICE_OUTPUT_LIMIT = 2000;

	/**
	 * Application root, with a trailing slash.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param UpdateServer      $server    The update server.
	 * @param Updater           $updater   Applies packages.
	 * @param PackageVerifier   $verifier  Verifies packages.
	 * @param ReleaseSignature  $signature Release signature keys.
	 * @param InstallationState $state     The installation state.
	 * @param Settings          $settings  Settings API.
	 * @param FileSystem        $fs        Filesystem API.
	 * @param string            $root      Application root.
	 * @param JobQueue          $job_queue Queues the update emails.
	 */
	public function __construct(
		private UpdateServer $server,
		private Updater $updater,
		private PackageVerifier $verifier,
		private ReleaseSignature $signature,
		private InstallationState $state,
		private Settings $settings,
		private FileSystem $fs,
		string $root,
		JobQueue $job_queue
	) {
		$this->root      = rtrim( $root, '/\\' ) . '/';
		$this->job_queue = $job_queue;
	}

	/*
	|----------
	| Checking
	|----------
	*/

	/**
	 * The latest release, from the last check or a new one.
	 *
	 * A failed check is recorded too, keeping the last known release, so
	 * the admin page can show both.
	 *
	 * @param bool          $refresh  Ask the server even when the last check is recent.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{checked_at: string, latest: ?string, security: bool, error: ?string, available: bool}
	 */
	public function check( bool $refresh = false, ?callable $progress = null ) : array {
		$last = $this->state->update_section( InstallationState::UPDATE_CHECK );

		if ( ! $refresh && null !== $last && time() - strtotime( (string) ( $last['checked_at'] ?? '' ) ) < self::CHECK_TTL ) {
			$this->report( $progress, 'check', self::STATUS_INFO, sprintf( 'Using the check made at %s.', $last['checked_at'] ?? '' ) );
			return $this->with_availability( $last );
		}

		try {
			$latest = $this->server->latest( $progress );
			$check  = array( 'latest' => $latest['version'], 'security' => $latest['security'], 'error' => null );
		} catch ( UpdateException $e ) {
			$check = array( 'latest' => $last['latest'] ?? null, 'security' => (bool) ( $last['security'] ?? false ), 'error' => $e->getMessage() );
		}

		$check = array( 'checked_at' => gmdate( DATE_ATOM ) ) + $check + array_intersect_key( (array) $last, array_flip( array( 'queued' ) ) );

		try {
			$this->state->set_update_section( InstallationState::UPDATE_CHECK, $check );
		} catch ( \RuntimeException $e ) {
			$check['error'] = trim( $check['error'] . ' The result could not be saved: ' . $e->getMessage() );
		}

		return $this->with_availability( $check );
	}

	/**
	 * What prevents an update right now, found without contacting the server.
	 *
	 * @return array<string, string> Plain-language problems keyed by kind ("keys", "zip", "process",
	 *                               "writable", "disk", "unfinished"); empty when nothing does.
	 */
	public function blockers() : array {
		$problems = array();

		if ( ! $this->signature->configured() ) {
			$problems['keys'] = 'No release signing keys are configured, so no package can be verified.';
		}

		if ( ! class_exists( \ZipArchive::class ) ) {
			$problems['zip'] = 'The zip PHP extension is not enabled.';
		}

		if ( ! function_exists( 'proc_open' ) ) {
			$problems['process'] = 'PHP may not start a new process (proc_open is disabled), which finishing an update needs.';
		}

		foreach ( array( '', 'system', 'server' ) as $dir ) {
			$path = $this->root . $dir;

			if ( $this->fs->exists( $path ) && ! $this->fs->is_writable( $path ) ) {
				$problems[ 'writable:' . ( '' === $dir ? '.' : $dir ) ] = sprintf( 'PHP cannot write to %s; the files are owned by another user.', '' === $dir ? 'the application folder' : $dir . '/' );
			}
		}

		$free = @disk_free_space( $this->root );

		if ( false !== $free && $free < self::MIN_FREE_BYTES ) {
			$problems['disk'] = sprintf( 'Only %d MB of disk space is free; an update needs at least %d MB.', (int) ( $free / 1048576 ), (int) ( self::MIN_FREE_BYTES / 1048576 ) );
		}

		$stage = $this->updater->status()['stage'] ?? null;

		if ( Updater::STAGE_APPLYING === $stage || Updater::STAGE_SWAPPED === $stage ) {
			$problems['unfinished'] = 'A previous update is not finished. Finish it or roll it back first.';
		}

		return $problems;
	}

	/*
	|-----------
	| Preparing
	|-----------
	*/

	/**
	 * Get a verified, staged package ready for Updater::apply().
	 *
	 * From the update server, the latest release is used; a package a dry
	 * run already downloaded for that version is reused after rechecking
	 * its hash. A local package must carry its .sha256 (and .sha256.sig
	 * unless trusted) beside it.
	 *
	 * The returned "package" says where the package came from (the URL
	 * it was downloaded from, or the local path), its SHA-256 and the key
	 * that signed it; pass it to Updater::apply() so the update log keeps it.
	 *
	 * @param string|null   $package        Local package path; null to use the update server.
	 * @param bool          $reinstall      Allow the installed version again.
	 * @param bool          $trust_unsigned Accept a local package without a valid signature.
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{manifest: ReleaseManifest, package: array{source: string, sha256: string, signed_by: ?string}, warnings: string[]}|null
	 *         Null when the installation is up to date.
	 *
	 * @throws UpdateException When something blocks the update or the package is unusable.
	 */
	public function prepare( ?string $package = null, bool $reinstall = false, bool $trust_unsigned = false, ?callable $progress = null ) : ?array {
		$blockers = $this->blockers();

		// A trusted local package does not need signing keys.
		if ( null !== $package && $trust_unsigned ) {
			unset( $blockers['keys'] );
		}

		if ( ! empty( $blockers ) ) {
			throw new UpdateException( implode( ' ', $blockers ) );
		}

		if ( null !== $package ) {
			$source = (string) ( realpath( $package ) ?: $package );

			$this->report( $progress, 'download', self::STATUS_INFO, sprintf( 'Using the local package %s.', $source ) );

			$verified = $this->verifier->verify( $package, $this->sibling( $package, '.sha256' ), $this->sibling( $package, '.sha256.sig' ), $trust_unsigned, $progress );

			return array(
				'manifest' => $this->updater->stage( $package, null, $reinstall, $progress ),
				'package'  => array( 'source' => $source, 'sha256' => $verified['sha256'], 'signed_by' => $verified['signed_by'] ),
				'warnings' => $verified['warnings'],
			);
		}

		$check = $this->check( true, $progress );

		if ( null !== $check['error'] ) {
			throw new UpdateException( $check['error'] );
		}

		$compare = version_compare( (string) $check['latest'], \SMLISER_VER );

		if ( $compare < 0 || ( 0 === $compare && ! $reinstall ) ) {
			return null;
		}

		$ready    = $this->ready_package( (string) $check['latest'] );
		$warnings = array();

		if ( null !== $ready ) {
			$zip    = (string) $ready['zip'];
			$origin = array( 'source' => (string) ( $ready['source'] ?? '' ), 'sha256' => (string) $ready['sha256'], 'signed_by' => $ready['signed_by'] ?? null );

			$this->report( $progress, 'download', self::STATUS_OK, sprintf( 'Reusing %s, verified by the dry run of %s (downloaded from %s).', $zip, $ready['prepared_at'] ?? '?', '' !== $origin['source'] ? $origin['source'] : 'the update server' ) );
		} else {
			$download = $this->server->download( (string) $check['latest'], $this->updater->downloads_dir(), false, $progress );
			$zip      = $download['zip'];
			$warnings = $download['warnings'];
			$origin   = array( 'source' => $download['source'], 'sha256' => $download['sha256'], 'signed_by' => $download['signed_by'] );
		}

		try {
			$manifest = $this->updater->stage( $zip, (string) $check['latest'], $reinstall, $progress );
		} catch ( UpdateException $e ) {
			$this->updater->discard_working_files();
			throw $e;
		}

		$this->state->set_update_section(
			InstallationState::UPDATE_READY,
			array( 'version' => $manifest->version, 'zip' => $zip, 'prepared_at' => gmdate( DATE_ATOM ) ) + $origin
		);

		return array( 'manifest' => $manifest, 'package' => $origin, 'warnings' => $warnings );
	}

	/**
	 * Everything an update would do, except installing.
	 *
	 * Checks, downloads, verifies and stages the latest release; the
	 * verified package is kept so installing that version later does not
	 * download it again.
	 *
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{ok: bool, version: ?string, installed: string, package: ?array, blockers: array<string, string>, warnings: string[], error: ?string}
	 */
	public function dry_run( ?callable $progress = null ) : array {
		$result = array(
			'ok'        => false,
			'version'   => null,
			'installed' => \SMLISER_VER,
			'package'   => null,
			'blockers'  => $this->blockers(),
			'warnings'  => array(),
			'error'     => null,
		);

		if ( ! empty( $result['blockers'] ) ) {
			return $result;
		}

		try {
			$prepared = $this->prepare( null, false, false, $progress );
		} catch ( UpdateException $e ) {
			$result['error'] = $e->getMessage();
			return $result;
		}

		if ( null === $prepared ) {
			$result['ok'] = true;
			return $result;
		}

		$result['ok']       = true;
		$result['version']  = $prepared['manifest']->version;
		$result['package']  = $prepared['package'];
		$result['warnings'] = $prepared['warnings'];

		return $result;
	}

	/*
	|--------------------
	| Automatic updates
	|--------------------
	*/

	/**
	 * The automatic update mode.
	 *
	 * @return string One of the AUTO_* constants; security releases only by default.
	 */
	public function auto_mode() : string {
		$mode = $this->settings->get( self::SETTING_AUTO, self::AUTO_SECURITY );

		return in_array( $mode, array( self::AUTO_OFF, self::AUTO_SECURITY, self::AUTO_ALL ), true ) ? $mode : self::AUTO_SECURITY;
	}

	/**
	 * Set the automatic update mode.
	 *
	 * @param string $mode One of the AUTO_* constants.
	 * @return void
	 * @throws UpdateException For an unknown mode.
	 */
	public function set_auto_mode( string $mode ) : void {
		if ( ! in_array( $mode, array( self::AUTO_OFF, self::AUTO_SECURITY, self::AUTO_ALL ), true ) ) {
			throw new UpdateException( sprintf( 'Unknown automatic update mode "%s"; use off, security or all.', $mode ) );
		}

		$this->settings->set( self::SETTING_AUTO, $mode );
	}

	/**
	 * The scheduled check: refresh the check, remove an old backup, and say
	 * whether an automatic update should be queued.
	 *
	 * Returns true at most once per version per QUEUED_TTL, so a stalled
	 * queue does not collect duplicate update jobs.
	 *
	 * @return bool Whether to queue the automatic update.
	 */
	public function scheduled_check() : bool {
		$check = $this->check( true );

		$this->expire_backup();
		$this->quietly( fn() => $this->notify_available( $check ) );

		$mode = $this->auto_mode();

		if (
			! $check['available']
			|| self::AUTO_OFF === $mode
			|| ( self::AUTO_SECURITY === $mode && ! $check['security'] )
		) {
			return false;
		}

		$queued = $check['queued'] ?? null;

		if ( is_array( $queued ) && $check['latest'] === ( $queued['version'] ?? null ) && time() - strtotime( (string) ( $queued['at'] ?? '' ) ) < self::QUEUED_TTL ) {
			return false;
		}

		$this->mark_queued( (string) $check['latest'], 'automatic' );

		return true;
	}

	/**
	 * Delete the backup once it is older than BACKUP_DAYS.
	 *
	 * @return bool Whether a backup was deleted.
	 */
	public function expire_backup() : bool {
		$backup = $this->updater->backup();

		if ( null === $backup || time() - strtotime( $backup['made_at'] ) < self::BACKUP_DAYS * 86400 ) {
			return false;
		}

		try {
			return $this->updater->delete_backup();
		} catch ( UpdateException ) {
			return false;
		}
	}

	/**
	 * Record that an install was queued for the update job.
	 *
	 * @param string $version Version to be installed.
	 * @param string $by      Who queued it: "automatic" or "admin".
	 * @return void
	 */
	public function mark_queued( string $version, string $by ) : void {
		$this->state->change(
			static function ( array $state ) use ( $version, $by ) : array {
				$state['update']['check']['queued'] = array( 'version' => $version, 'by' => $by, 'at' => gmdate( DATE_ATOM ) );

				return $state;
			}
		);
	}

	/**
	 * Record the outcome of a queued update attempt, and clear the queued marker.
	 *
	 * The update log (update.run) only exists once files are moved; this
	 * also covers attempts that stopped earlier, e.g. a failed verification.
	 *
	 * @param string $action  "install" or "rollback".
	 * @param bool   $ok      Whether it succeeded.
	 * @param string $message What happened, in the console's words.
	 * @return void
	 */
	public function record_attempt( string $action, bool $ok, string $message ) : void {
		$queued = null;
		$check  = null;

		$this->state->change(
			static function ( array $state ) use ( $action, $ok, $message, &$queued, &$check ) : array {
				$queued = is_array( $state['update']['check']['queued'] ?? null ) ? $state['update']['check']['queued'] : null;
				$check  = is_array( $state['update']['check'] ?? null ) ? $state['update']['check'] : array();

				unset( $state['update']['check']['queued'] );

				$state['update']['attempt'] = array(
					'action'  => $action,
					'ok'      => $ok,
					'message' => $message,
					'at'      => gmdate( DATE_ATOM ),
				);

				return $state;
			}
		);

		if ( ! $ok ) {
			$this->quietly( fn() => $this->notify_failed( $action, $message, $queued, (array) $check ) );
		}
	}

	/*
	|---------------
	| Notifications
	|---------------
	*/

	/**
	 * Email the administration address about a release automatic updates will not install.
	 *
	 * Sent once per version: when automatic updates are off, or set to
	 * security releases only and this is a regular release. Releases that
	 * will be installed automatically are reported when they are installed
	 * (or fail) instead.
	 *
	 * @param array $check A check result (see check()).
	 * @return bool Whether an email was queued.
	 */
	public function notify_available( array $check ) : bool {
		if ( empty( $check['available'] ) || ! is_string( $check['latest'] ?? null ) ) {
			return false;
		}

		$mode     = $this->auto_mode();
		$security = ! empty( $check['security'] );

		if ( self::AUTO_ALL === $mode || ( self::AUTO_SECURITY === $mode && $security ) ) {
			return false;
		}

		$version  = $check['latest'];
		$notified = $this->state->update_section( InstallationState::UPDATE_NOTIFIED );

		if ( $version === ( $notified['available'] ?? null ) ) {
			return false;
		}

		$this->dispatch_job(
			UpdateNotificationJob::class,
			array(
				'event'           => UpdateNotificationJob::EVENT_AVAILABLE,
				'current_version' => \SMLISER_VER,
				'new_version'     => $version,
				'security'        => $security,
				'detail'          => self::AUTO_OFF === $mode
					? 'Automatic updates are off on this site, so it will not be installed automatically.'
					: 'Automatic updates on this site install security releases only, so this release will not be installed automatically.',
			)
		);

		$this->state->set_update_section(
			InstallationState::UPDATE_NOTIFIED,
			array( 'available' => $version, 'at' => gmdate( DATE_ATOM ) ) + (array) $notified
		);

		return true;
	}

	/**
	 * Email the administration address after an automatic update was installed.
	 *
	 * Called by the finish step, which runs on the new code. Updates an
	 * administrator started (from the Updates page or the console) send
	 * nothing on success: they were watched as they happened.
	 *
	 * @param array $result Updater::finish() result: {from, to, package}.
	 * @return bool Whether an email was queued.
	 */
	public function notify_installed( array $result ) : bool {
		$check  = (array) $this->state->update_section( InstallationState::UPDATE_CHECK );
		$queued = is_array( $check['queued'] ?? null ) ? $check['queued'] : null;

		if ( null === $queued || 'automatic' !== ( $queued['by'] ?? null ) || ( $result['to'] ?? null ) !== ( $queued['version'] ?? null ) ) {
			return false;
		}

		$this->dispatch_job(
			UpdateNotificationJob::class,
			array(
				'event'           => UpdateNotificationJob::EVENT_INSTALLED,
				'current_version' => (string) ( $result['from'] ?? '' ),
				'new_version'     => (string) $result['to'],
				'security'        => $result['to'] === ( $check['latest'] ?? null ) && ! empty( $check['security'] ),
				'package'         => (array) ( $result['package'] ?? array() ),
			)
		);

		return true;
	}

	/**
	 * Email the administration address about a queued update or rollback that failed.
	 *
	 * @param string     $action  "install" or "rollback".
	 * @param string     $message The update's output.
	 * @param array|null $queued  The queued marker of the attempt ({version, by, at}).
	 * @param array      $check   The stored check.
	 * @return void
	 */
	private function notify_failed( string $action, string $message, ?array $queued, array $check ) : void {
		$version = (string) ( $queued['version'] ?? $check['latest'] ?? '' );
		$output  = trim( $message );

		if ( strlen( $output ) > self::NOTICE_OUTPUT_LIMIT ) {
			$output = '…' . substr( $output, -self::NOTICE_OUTPUT_LIMIT );
		}

		$this->dispatch_job(
			UpdateNotificationJob::class,
			array(
				'event'           => UpdateNotificationJob::EVENT_FAILED,
				'action'          => $action,
				'current_version' => \SMLISER_VER,
				'new_version'     => $version,
				'security'        => $version === ( $check['latest'] ?? null ) && ! empty( $check['security'] ),
				'detail'          => '' !== $output ? $output : 'The update ended without output.',
			)
		);
	}

	/**
	 * Everything the admin page shows, read without contacting the update server.
	 *
	 * @return array{
	 *     installed: string,
	 *     check: array,
	 *     blockers: string[],
	 *     auto: string,
	 *     backup: array{version: string, made_at: string}|null,
	 *     ready: array|null,
	 *     queued: array|null,
	 *     attempt: array|null,
	 *     run: array|null,
	 *     in_progress: bool
	 * }
	 */
	public function overview() : array {
		$check = $this->check();
		$run   = $this->updater->status();
		$state = $this->state->read();

		return array(
			'installed'   => \SMLISER_VER,
			'check'       => $check,
			'blockers'    => array_values( $this->blockers() ),
			'auto'        => $this->auto_mode(),
			'backup'      => $this->updater->backup(),
			'ready'       => $this->state->update_section( InstallationState::UPDATE_READY ),
			'queued'      => is_array( $check['queued'] ?? null ) ? $check['queued'] : null,
			'attempt'     => is_array( $state ) && is_array( $state['update']['attempt'] ?? null ) ? $state['update']['attempt'] : null,
			'run'         => null === $run ? null : array_intersect_key( $run, array_flip( array( 'from', 'to', 'stage', 'started_at', 'finished_at', 'rolled_back_at' ) ) ),
			'in_progress' => in_array( $run['stage'] ?? null, array( Updater::STAGE_APPLYING, Updater::STAGE_SWAPPED ), true ),
		);
	}

	/*
	|---------
	| Helpers
	|---------
	*/

	/**
	 * Run a notification step; an email that cannot be queued must never stop an update.
	 *
	 * @param callable $step The step.
	 * @return void
	 */
	private function quietly( callable $step ) : void {
		try {
			$step();
		} catch ( \Throwable $e ) {
			\smliser_log_error( sprintf( '[UpdateService] Update notification not queued: %s', $e->getMessage() ) );
		}
	}

	/**
	 * A check result with "available" worked out against the installed version.
	 *
	 * @param array $check Stored check.
	 * @return array
	 */
	private function with_availability( array $check ) : array {
		$check += array( 'checked_at' => '', 'latest' => null, 'security' => false, 'error' => null );

		$check['available'] = is_string( $check['latest'] ) && version_compare( $check['latest'], \SMLISER_VER, '>' );

		return $check;
	}

	/**
	 * A package a dry run already verified for this version, if it is still intact.
	 *
	 * @param string $version Version wanted.
	 * @return array|null The update.ready record (zip, sha256, source, signed_by, prepared_at).
	 */
	private function ready_package( string $version ) : ?array {
		$ready = $this->state->update_section( InstallationState::UPDATE_READY );

		if ( null === $ready || $version !== ( $ready['version'] ?? null ) ) {
			return null;
		}

		$zip = (string) ( $ready['zip'] ?? '' );

		if ( '' !== $zip && $this->fs->is_file( $zip ) && hash_equals( (string) ( $ready['sha256'] ?? '' ), (string) hash_file( 'sha256', $zip ) ) ) {
			return $ready;
		}

		$this->updater->discard_working_files();

		return null;
	}

	/**
	 * Contents of a file beside a package, e.g. its .sha256.
	 *
	 * @param string $zip    Package path.
	 * @param string $suffix Suffix replacing ".zip".
	 * @return string|null
	 */
	private function sibling( string $zip, string $suffix ) : ?string {
		$path = preg_replace( '/\.zip$/i', '', $zip ) . $suffix;

		if ( ! $this->fs->is_file( $path ) ) {
			return null;
		}

		$contents = $this->fs->get_contents( $path );

		return is_string( $contents ) ? $contents : null;
	}
}