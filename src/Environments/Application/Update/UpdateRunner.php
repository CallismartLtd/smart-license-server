<?php
/**
 * UpdateRunner class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\FileSystem\FileSystem;
use Throwable;

/**
 * Runs updates the same way from any PHP SAPI: the console, an admin web
 * request, the queue worker, or the boot of the next request.
 *
 * An update has two halves that cannot run in the same process:
 *
 *  1. install: prepare (download, verify, stage) and apply (swap the
 *     files in, under maintenance). Runs anywhere.
 *  2. finish: migrations, the recorded versions, maintenance off. Must
 *     run on the new code, so in a process started after the swap.
 *
 * complete() starts that process when it can (proc_open() and a PHP CLI
 * binary, see PhpCli), so the update is finished before install()
 * returns. When it cannot, the update stays "swapped" and is finished by
 * the next process that boots on the new code: any web request (the
 * admin page reloading, a visitor, the cron URL), any console command,
 * or the restarted queue worker. See finish_pending() and
 * ApplicationEnvironment.
 *
 * Every method takes the update lock for its own duration; a process
 * that already holds it (the console, between prepare and apply) keeps it.
 *
 * Status values returned by install(), apply_ready() and complete():
 *   up_to_date  nothing to install
 *   cancelled   the confirm callback declined
 *   finished    installed and finished
 *   pending     installed; finishes on the next boot of the new code
 */
final class UpdateRunner {

	use UpdateProgressTrait;

	/**
	 * Statuses.
	 */
	public const UP_TO_DATE = 'up_to_date';
	public const CANCELLED  = 'cancelled';
	public const FINISHED   = 'finished';
	public const PENDING    = 'pending';

	/**
	 * Seconds allowed for the finish process.
	 *
	 * @var int
	 */
	private const FINISH_TIMEOUT = 600;

	/**
	 * An update this process finished while booting, if any.
	 *
	 * @var array|null
	 */
	private ?array $finished_at_boot = null;

	/**
	 * Constructor.
	 *
	 * @param UpdateService $updates   Checks, prepares and notifies.
	 * @param Updater       $updater   Applies, finishes and rolls back.
	 * @param AppInstaller  $installer Republishes the public assets.
	 * @param FileSystem    $fs        Filesystem API.
	 * @param string        $root      Application root.
	 */
	public function __construct(
		private UpdateService $updates,
		private Updater $updater,
		private AppInstaller $installer,
		private FileSystem $fs,
		private string $root
	) {
		$this->root = rtrim( $root, '/\\' ) . '/';
	}

	/*
	|------------
	| Installing
	|------------
	*/

	/**
	 * Prepare and apply an update, then finish it if this server allows.
	 *
	 * @param string|null   $package        Local package; null for the update server.
	 * @param bool          $reinstall      Install the installed version again.
	 * @param bool          $trust_unsigned Accept a local package without a valid signature.
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @param callable|null $confirm        fn( array $prepared ): bool, asked between prepare and apply.
	 * @param bool          $passthrough    Let the finish process write to this process's output (console).
	 * @return array{status: string, prepared?: array, finish?: array}
	 * @throws UpdateException When the update cannot be prepared or applied (the previous files are restored).
	 */
	public function install(
		?string $package = null,
		bool $reinstall = false,
		bool $trust_unsigned = false,
		?callable $progress = null,
		?callable $confirm = null,
		bool $passthrough = false
	) : array {
		$prepared = $this->locked( fn() => $this->prepare_and_apply( $package, $reinstall, $trust_unsigned, $progress, $confirm ) );

		if ( ! is_array( $prepared ) ) {
			return array( 'status' => $prepared );
		}

		return array( 'prepared' => $prepared ) + $this->complete( $progress, $passthrough );
	}

	/**
	 * Prepare the latest release without installing it (a dry run's work).
	 *
	 * The verified package is kept, so apply_ready() needs no download.
	 *
	 * @param bool          $reinstall Prepare the installed version again.
	 * @param callable|null $progress  Progress callback.
	 * @return array|null UpdateService::prepare() result; null when up to date.
	 * @throws UpdateException
	 */
	public function prepare( bool $reinstall = false, ?callable $progress = null ) : ?array {
		return $this->locked( fn() => $this->updates->prepare( null, $reinstall, false, $progress ) );
	}

	/**
	 * Apply the latest release, reusing the package a dry run or prepare() verified.
	 *
	 * Without a kept package it downloads one, like install().
	 *
	 * @param bool          $reinstall Install the installed version again.
	 * @param callable|null $progress  Progress callback.
	 * @return array{status: string, prepared?: array, finish?: array}
	 * @throws UpdateException
	 */
	public function apply_ready( bool $reinstall = false, ?callable $progress = null ) : array {
		return $this->install( null, $reinstall, false, $progress );
	}

	/**
	 * Everything an update would do, except installing.
	 *
	 * @param callable|null $progress Progress callback.
	 * @return array UpdateService::dry_run() result.
	 * @throws UpdateException When another update is running.
	 */
	public function dry_run( ?callable $progress = null ) : array {
		return $this->locked( fn() => $this->updates->dry_run( $progress ) );
	}

	/*
	|-----------
	| Finishing
	|-----------
	*/

	/**
	 * Finish an applied update in a new process, when this server allows it.
	 *
	 * @param callable|null $progress    Progress callback.
	 * @param bool          $passthrough Let the finish process write to this process's output.
	 * @return array{status: string, finish: array{method: string, exit_code?: int, output?: string}}
	 */
	public function complete( ?callable $progress = null, bool $passthrough = false ) : array {
		if ( Updater::STAGE_SWAPPED !== ( $this->updater->status()['stage'] ?? null ) ) {
			return array( 'status' => self::FINISHED, 'finish' => array( 'method' => 'none' ) );
		}

		$php = PhpCli::find();

		if ( null === $php ) {
			$this->report( $progress, 'finish', self::STATUS_WARNING, 'This server cannot start a new PHP process; the update finishes on the next request to the site.' );

			return array( 'status' => self::PENDING, 'finish' => array( 'method' => 'next_boot' ) );
		}

		$this->report( $progress, 'finish', self::STATUS_INFO, sprintf( 'Finishing with the new version (%s %ssmliser update finish).', $php, $this->root ) );

		$result = $this->run_finish_process( $php, $passthrough );
		$stage  = $this->updater->status()['stage'] ?? null;

		// The process may have failed to start or crashed; the next boot finishes it then.
		$status = Updater::STAGE_SWAPPED === $stage ? self::PENDING : self::FINISHED;

		return array( 'status' => $status, 'finish' => array( 'method' => 'process' ) + $result );
	}

	/**
	 * Finish an update applied by an earlier process, if this process runs its new code.
	 *
	 * Called at boot (see ApplicationEnvironment) and by `update finish`.
	 * Cheap when there is nothing to do: one read of the update journal.
	 *
	 * @param callable|null $progress Progress callback.
	 * @return array|null Updater::finish() result, or null when there was nothing to
	 *                    finish here (no update waiting, another version's code, or
	 *                    another process holding the lock).
	 * @throws UpdateException When finishing fails (the site stays in maintenance).
	 */
	public function finish_pending( ?callable $progress = null ) : ?array {
		$journal = $this->updater->status();

		if ( Updater::STAGE_SWAPPED !== ( $journal['stage'] ?? null ) || \SMLISER_VER !== ( $journal['to'] ?? null ) ) {
			return null;
		}

		$held = $this->updater->holds_lock();

		if ( ! $held && ! $this->updater->lock() ) {
			return null;
		}

		try {
			// Another process may have finished it while this one waited for the lock.
			if ( Updater::STAGE_SWAPPED !== ( $this->updater->status()['stage'] ?? null ) ) {
				return null;
			}

			$result = $this->updater->finish( null, $progress );
		} finally {
			if ( ! $held ) {
				$this->updater->unlock();
			}
		}

		$this->relink_assets( $progress );
		$this->quietly( fn() => $this->updates->notify_installed( $result ) );
		$this->quietly( fn() => $this->updates->clear_queued( (string) $result['to'] ) );

		return $result;
	}

	/**
	 * Finish at boot, recording the result for finished_at_boot().
	 *
	 * Never throws: a failure is logged and leaves the site in maintenance.
	 *
	 * @return bool Whether an update was finished.
	 */
	public function finish_at_boot() : bool {
		try {
			$this->finished_at_boot = $this->finish_pending();
		} catch ( Throwable $e ) {
			\smliser_log_error( sprintf( '[UpdateRunner] Finishing the update failed: %s', $e->getMessage() ) );
			return false;
		}

		return null !== $this->finished_at_boot;
	}

	/**
	 * The update this process finished while booting, if any.
	 *
	 * Lets `update finish` report an update its own boot already completed.
	 *
	 * @return array|null
	 */
	public function finished_at_boot() : ?array {
		return $this->finished_at_boot;
	}

	/*
	|-------------
	| Rolling back
	|-------------
	*/

	/**
	 * Restore the previous version's files.
	 *
	 * @param callable|null $progress Progress callback.
	 * @return string The version restored.
	 * @throws UpdateException
	 */
	public function rollback( ?callable $progress = null ) : string {
		$version = $this->locked( fn() => $this->updater->rollback( $progress ) );

		$this->relink_assets( $progress );

		return $version;
	}

	/*
	|---------
	| Helpers
	|---------
	*/

	/**
	 * Prepare, ask for confirmation, apply.
	 *
	 * @param string|null   $package
	 * @param bool          $reinstall
	 * @param bool          $trust_unsigned
	 * @param callable|null $progress
	 * @param callable|null $confirm
	 * @return array|string The prepared update, or a status when nothing was applied.
	 * @throws UpdateException
	 */
	private function prepare_and_apply( ?string $package, bool $reinstall, bool $trust_unsigned, ?callable $progress, ?callable $confirm ) : array|string {
		$prepared = $this->updates->prepare( $package, $reinstall, $trust_unsigned, $progress );

		if ( null === $prepared ) {
			return self::UP_TO_DATE;
		}

		if ( null !== $confirm && ! $confirm( $prepared ) ) {
			return self::CANCELLED;
		}

		// Load what complete() needs before the swap: once the files are
		// replaced, a class loaded from disk would be the new version's.
		PhpCli::find();

		$this->updater->apply( $prepared['manifest'], $prepared['package'], $progress );

		return $prepared;
	}

	/**
	 * Run a step under the update lock, keeping a lock this process already holds.
	 *
	 * @param callable $step
	 * @return mixed The step's result.
	 * @throws UpdateException When another process holds the lock.
	 */
	private function locked( callable $step ) : mixed {
		$held = $this->updater->holds_lock();

		if ( ! $held && ! $this->updater->lock() ) {
			throw new UpdateException( 'Another update is running. Try again once it has ended.' );
		}

		try {
			return $step();
		} finally {
			if ( ! $held ) {
				$this->updater->unlock();
			}
		}
	}

	/**
	 * Run `smliser update finish` in a new PHP process.
	 *
	 * @param string $php         PHP CLI binary.
	 * @param bool   $passthrough Share this process's output instead of capturing it.
	 * @return array{exit_code: int, output?: string}
	 */
	private function run_finish_process( string $php, bool $passthrough ) : array {
		$command = array( $php, $this->root . 'smliser', 'update', 'finish' );

		$descriptors = $passthrough && defined( 'STDOUT' )
			? array( 0 => array( 'pipe', 'r' ), 1 => STDOUT, 2 => STDERR )
			: array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'redirect', 1 ) );

		try {
			$process = proc_open( $command, $descriptors, $pipes, $this->root );
		} catch ( Throwable ) {
			$process = false;
		}

		if ( ! is_resource( $process ) ) {
			return array( 'exit_code' => -1, 'output' => 'The finish process could not be started.' );
		}

		fclose( $pipes[0] );

		$output = '';

		if ( isset( $pipes[1] ) ) {
			$deadline = time() + self::FINISH_TIMEOUT;

			while ( ! feof( $pipes[1] ) && time() < $deadline ) {
				$output .= (string) fread( $pipes[1], 8192 );
			}

			fclose( $pipes[1] );
		}

		$code = proc_close( $process );

		return array( 'exit_code' => $code ) + ( isset( $pipes[1] ) ? array( 'output' => trim( $output ) ) : array() );
	}

	/**
	 * Republish the public assets from the current system/assets.
	 *
	 * A symlink keeps working on its own; a copy (where symlinks are not
	 * available) is refreshed.
	 *
	 * @param callable|null $progress
	 * @return void
	 */
	private function relink_assets( ?callable $progress ) : void {
		try {
			$copied = $this->fs->is_file( rtrim( $this->installer->assets_public_dir(), '/\\' ) . '/' . AppInstaller::ASSETS_COPY_MARKER );
			$this->installer->link_public_assets( $copied );
		} catch ( Throwable $e ) {
			$this->report( $progress, 'finish', self::STATUS_WARNING, sprintf( 'The public assets could not be republished: %s Run `smliser installer link:assets --force`.', $e->getMessage() ) );
		}
	}

	/**
	 * Run a step whose failure must not fail the update.
	 *
	 * @param callable $step
	 * @return void
	 */
	private function quietly( callable $step ) : void {
		try {
			$step();
		} catch ( Throwable $e ) {
			\smliser_log_error( sprintf( '[UpdateRunner] %s', $e->getMessage() ) );
		}
	}
}