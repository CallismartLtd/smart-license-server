<?php
/**
 * Updater class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

use SmartLicenseServer\Environments\Application\Boot\InstallationState;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;
use SmartLicenseServer\Environments\Application\Release\ReleaseManifest;
use SmartLicenseServer\FileSystem\FileSystem;
use Throwable;
use ZipArchive;

/**
 * Applies a verified release package to this installation.
 *
 * The application owns its directories (system/, server/), so an update
 * replaces each one whole: the new copy is extracted next to the live one
 * and swapped in with renames, so files a release dropped disappear with
 * the old directory. A few loose core files (bootstrap.php, smliser,
 * *.example files, manifest.json and, when a release ships it,
 * public/index.php) are replaced one by one. Everything else belongs to the
 * installation and is never touched: .env, .htaccess, storage/, the rest of
 * public/.
 *
 * Three steps, each safe to repeat:
 *
 *  1. stage()  Extract the package to .updates/staging and check it: the
 *              right product and target, a newer version, requirements met,
 *              every file matching the package's own manifest.
 *  2. apply()  Put the site into maintenance (reason "update"), move the
 *              replaced directories and files into .updates/backup, and
 *              move the new ones in. Any failure restores the backup.
 *  3. finish() Run in a new PHP process, with the new code: migrate the
 *              database if the schema version changed, record the new
 *              versions in state.json, end maintenance.
 *
 * rollback() puts the previous files back while no migration has run.
 * Progress is kept in state.json (update.run, see InstallationState), so an
 * update interrupted at any point can be finished or rolled back. The
 * .updates directory holds files only: the staged copy, the download and
 * the backup. Staged copies and downloads never outlive the attempt that
 * made them, except a verified package kept for installing later (see
 * UpdateService::dry_run()); only the latest backup is kept.
 */
final class Updater {

	/**
	 * Working directory, at the application root (same filesystem as system/, so swaps are renames).
	 *
	 * @var string
	 */
	public const WORK_DIR = '.updates';

	/**
	 * Directories the application owns and replaces whole.
	 *
	 * @var string[]
	 */
	public const OWNED_DIRS = array( 'system', 'server' );

	/**
	 * Root files that belong to the installation, never replaced.
	 *
	 * @var string[]
	 */
	public const PROTECTED_FILES = array( '.env', '.htaccess' );

	/**
	 * Files outside the root an update may replace.
	 *
	 * @var string[]
	 */
	public const PUBLIC_FILES = array( 'public/index.php' );

	/**
	 * Journal stages.
	 */
	public const STAGE_APPLYING    = 'applying';
	public const STAGE_SWAPPED     = 'swapped';
	public const STAGE_FINISHED    = 'finished';
	public const STAGE_ROLLED_BACK = 'rolled_back';

	/**
	 * Application root, with a trailing slash.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Handle of the held update lock.
	 *
	 * @var resource|null
	 */
	private $lock = null;

	/**
	 * Constructor.
	 *
	 * @param FileSystem        $fs    Filesystem API.
	 * @param InstallationState $state The installation state file.
	 * @param MaintenanceFlag   $flag  The maintenance flag file.
	 * @param string            $root  Application root.
	 */
	public function __construct(
		private FileSystem $fs,
		private InstallationState $state,
		private MaintenanceFlag $flag,
		string $root
	) {
		$this->root = rtrim( $root, '/\\' ) . '/';
	}

	/*
	|------------
	| 1. Staging
	|------------
	*/

	/**
	 * Extract a package and check it is fit to install.
	 *
	 * @param string      $zip              Path to a verified package zip.
	 * @param string|null $expected_version Version the package must contain (from the update server); null for any.
	 * @param bool        $reinstall        Allow the installed version again, to repair damaged files.
	 * @return ReleaseManifest The staged release's manifest.
	 *
	 * @throws UpdateException When the package is unusable or the installation is mid-update.
	 */
	public function stage( string $zip, ?string $expected_version = null, bool $reinstall = false ) : ReleaseManifest {
		$this->assert_idle();

		$staging = $this->path( 'staging' );

		$this->remove( $staging );
		$this->remove( $this->path( 'failed' ) );
		$this->make_dir( $staging );

		try {
			$this->extract( $zip, $staging );

			$root     = $this->staged_root();
			$manifest = ReleaseManifest::load( $this->fs, $root );

			if ( null === $manifest ) {
				throw new UpdateException( sprintf( '%s is not a release package (it has no %s).', basename( $zip ), ReleaseManifest::FILE ) );
			}

			if ( UpdateServer::APP_SLUG !== $manifest->name || UpdateServer::TARGET !== $manifest->target ) {
				throw new UpdateException( sprintf( 'The package is %s for "%s"; this installation needs %s for "%s".', $manifest->name, $manifest->target, UpdateServer::APP_SLUG, UpdateServer::TARGET ) );
			}

			if ( null !== $expected_version && $expected_version !== $manifest->version ) {
				throw new UpdateException( sprintf( 'The update server announced %s, but the package contains %s. Nothing was changed.', $expected_version, $manifest->version ) );
			}

			$compare = version_compare( $manifest->version, \SMLISER_VER );

			if ( $compare < 0 ) {
				throw new UpdateException( sprintf( 'The package is version %s, older than the installed %s. Downgrades are not supported.', $manifest->version, \SMLISER_VER ) );
			}

			if ( 0 === $compare && ! $reinstall ) {
				throw new UpdateException( sprintf( 'Version %s is already installed. Use --reinstall to replace the files anyway.', $manifest->version ) );
			}

			$this->assert_requirements( $manifest->requires );

			if ( ! $this->fs->is_dir( $root . 'system' ) ) {
				throw new UpdateException( 'The package has no system/ directory.' );
			}

			$check = $manifest->verify( $this->fs, $root );

			if ( ! $check->passed() ) {
				throw new UpdateException( 'The package contents do not match its manifest: ' . implode( ' ', $check->messages( 5 ) ) );
			}
		} catch ( Throwable $e ) {
			$this->remove( $staging );

			throw $e instanceof UpdateException ? $e : new UpdateException( $e->getMessage(), 0, $e );
		}

		return $manifest;
	}

	/*
	|-------------
	| 2. Applying
	|-------------
	*/

	/**
	 * Swap the staged release in, under maintenance.
	 *
	 * On failure the previous files are restored and the maintenance flag
	 * returns to what it was, then the error is rethrown.
	 *
	 * @param ReleaseManifest $manifest The staged release's manifest (from stage()).
	 * @return void
	 * @throws UpdateException When the swap fails (after restoring).
	 */
	public function apply( ReleaseManifest $manifest ) : void {
		$this->assert_idle();

		$staged = $this->staged_root();
		$backup = $this->path( 'backup' );

		$this->remove( $backup );
		$this->make_dir( $backup );

		$journal = array(
			'from'          => \SMLISER_VER,
			'to'            => $manifest->version,
			'stage'         => self::STAGE_APPLYING,
			'started_at'    => gmdate( DATE_ATOM ),
			'previous_flag' => $this->flag->read(),
			'dirs'          => array(),
			'replaced'      => array(),
			'added'         => array(),
		);

		$this->flag->write(
			MaintenanceFlag::REASON_UPDATE,
			sprintf( '%s is being updated. Please try again in a few minutes.', \SMLISER_APP_NAME ),
			120
		);

		$this->write_journal( $journal );

		try {
			foreach ( self::OWNED_DIRS as $dir ) {
				if ( ! $this->fs->is_dir( $staged . $dir ) ) {
					continue;
				}

				$live     = $this->root . $dir;
				$had_live = $this->fs->is_dir( $live );

				$journal['dirs'][] = array( 'dir' => $dir, 'had_live' => $had_live );
				$this->write_journal( $journal );

				if ( $had_live ) {
					$this->rename( $live, $backup . '/' . $dir );
				}

				$this->rename( $staged . $dir, $live );
			}

			foreach ( $this->root_files( $manifest ) as $relative ) {
				$live = $this->root . $relative;
				$mode = null;

				if ( $this->fs->is_file( $live ) ) {
					// stat() reports permissions as an octal string, e.g. "0755".
					$stat = $this->fs->stat( $live );
					$mode = is_array( $stat ) && is_string( $stat['perms'] ?? null ) ? octdec( $stat['perms'] ) & 0777 : null;

					if ( ! $this->fs->copy( $live, $backup . '/' . $relative, true, $mode ?? false ) ) {
						throw new UpdateException( sprintf( 'Could not back up %s.', $relative ) );
					}

					$journal['replaced'][] = $relative;
				} else {
					$journal['added'][] = $relative;
				}

				$this->write_journal( $journal );
				$this->rename( $staged . $relative, $live );

				if ( null !== $mode ) {
					$this->fs->chmod( $live, $mode );
				}
			}

			$journal['stage'] = self::STAGE_SWAPPED;
			$this->write_journal( $journal );
		} catch ( Throwable $e ) {
			$this->restore( $journal );

			throw new UpdateException(
				sprintf( 'The update to %s failed and the previous files were restored: %s', $manifest->version, $e->getMessage() ),
				0,
				$e
			);
		}
	}

	/*
	|--------------
	| 3. Finishing
	|--------------
	*/

	/**
	 * Complete a swapped update. Must run in a new PHP process, with the new code loaded.
	 *
	 * When the schema version changed and no migrator is given, the files
	 * are rolled back rather than letting new code run on an old schema.
	 *
	 * @param callable( string $from, string $to ): void|null $migrate Migrates the database between schema versions.
	 * @return array{from: string, to: string, schema_changed: bool}
	 *
	 * @throws UpdateException When there is nothing to finish, or migrating fails (the site stays in maintenance).
	 */
	public function finish( ?callable $migrate = null ) : array {
		$journal = $this->read_journal();

		if ( null === $journal || self::STAGE_SWAPPED !== ( $journal['stage'] ?? null ) ) {
			throw new UpdateException( 'There is no update waiting to be finished.' );
		}

		if ( \SMLISER_VER !== $journal['to'] ) {
			throw new UpdateException( sprintf( 'This process runs version %s, not the new %s. Run the finish step again in a new process.', \SMLISER_VER, $journal['to'] ) );
		}

		$state       = $this->state->read();
		$schema_from = is_array( $state ) ? (string) ( $state['versions']['schema'] ?? '0' ) : '0';
		$schema_to   = \SMLISER_DB_VER;
		$changed     = version_compare( $schema_from, $schema_to, '<' );

		if ( $changed ) {
			if ( null === $migrate ) {
				$this->rollback();

				throw new UpdateException( sprintf( 'Version %s changes the database (schema %s to %s), which this installation cannot migrate yet. The previous version was restored.', $journal['to'], $schema_from, $schema_to ) );
			}

			try {
				$migrate( $schema_from, $schema_to );
			} catch ( Throwable $e ) {
				throw new UpdateException(
					sprintf( 'The database migration from schema %s to %s failed: %s The site stays in maintenance; restore your database backup before rolling back.', $schema_from, $schema_to, $e->getMessage() ),
					0,
					$e
				);
			}
		}

		$this->state->mark_installed( array( 'app' => \SMLISER_VER, 'schema' => $schema_to ) );

		$journal['stage']          = self::STAGE_FINISHED;
		$journal['finished_at']    = gmdate( DATE_ATOM );
		$journal['schema_changed'] = $changed;
		$this->write_journal( $journal );

		$this->restore_flag( $journal['previous_flag'] ?? null );
		$this->discard_working_files();

		return array( 'from' => (string) $journal['from'], 'to' => (string) $journal['to'], 'schema_changed' => $changed );
	}

	/*
	|-------------
	| Rolling back
	|-------------
	*/

	/**
	 * Put the previous version's files back.
	 *
	 * Works on an update that is applying (interrupted), swapped, or finished
	 * without a database change. A finished update that migrated the database
	 * cannot be rolled back here: the old code would run on the new schema.
	 *
	 * @return string The version restored.
	 * @throws UpdateException When there is nothing to roll back, or it is not safe.
	 */
	public function rollback() : string {
		$journal = $this->read_journal();
		$stage   = $journal['stage'] ?? null;

		if ( null === $journal || ! in_array( $stage, array( self::STAGE_APPLYING, self::STAGE_SWAPPED, self::STAGE_FINISHED ), true ) ) {
			throw new UpdateException( 'There is no update to roll back.' );
		}

		if ( self::STAGE_FINISHED === $stage && ! empty( $journal['schema_changed'] ) ) {
			throw new UpdateException( sprintf( 'The update to %s migrated the database, so its files cannot be rolled back on their own. Restore a full backup instead.', $journal['to'] ) );
		}

		$previous_flag = self::STAGE_FINISHED === $stage ? $this->flag->read() : ( $journal['previous_flag'] ?? null );

		if ( self::STAGE_FINISHED === $stage ) {
			$this->flag->write( MaintenanceFlag::REASON_UPDATE, sprintf( '%s is being restored. Please try again in a few minutes.', \SMLISER_APP_NAME ), 120 );
		}

		$journal['previous_flag'] = $previous_flag;
		$this->restore( $journal );

		if ( self::STAGE_FINISHED === $stage ) {
			$state  = $this->state->read();
			$schema = is_array( $state ) ? (string) ( $state['versions']['schema'] ?? \SMLISER_DB_VER ) : \SMLISER_DB_VER;

			$this->state->mark_installed( array( 'app' => (string) $journal['from'], 'schema' => $schema ) );
		}

		return (string) $journal['from'];
	}

	/*
	|--------
	| Status
	|--------
	*/

	/**
	 * The journal of the last update, if any.
	 *
	 * @return array|null
	 */
	public function status() : ?array {
		return $this->read_journal();
	}

	/**
	 * Directory downloads go into, created when missing.
	 *
	 * @return string
	 * @throws UpdateException When it cannot be created.
	 */
	public function downloads_dir() : string {
		$dir = $this->path( 'downloads' );
		$this->make_dir( $dir );

		return $dir;
	}

	/*
	|----------
	| Internals
	|----------
	*/

	/**
	 * Refuse to start while another update is unfinished.
	 *
	 * @return void
	 * @throws UpdateException
	 */
	private function assert_idle() : void {
		$stage = $this->read_journal()['stage'] ?? null;

		if ( self::STAGE_APPLYING === $stage || self::STAGE_SWAPPED === $stage ) {
			throw new UpdateException( 'A previous update is not finished. Finish it or roll it back first.' );
		}
	}

	/**
	 * Undo a partial or complete swap from the journal, then restore the maintenance flag.
	 *
	 * @param array $journal The journal.
	 * @return void
	 * @throws UpdateException When a file cannot be put back (the site stays in maintenance).
	 */
	private function restore( array $journal ) : void {
		$backup = $this->path( 'backup' );
		$failed = $this->path( 'failed' );

		foreach ( array_reverse( $journal['added'] ?? array() ) as $relative ) {
			if ( $this->fs->is_file( $this->root . $relative ) ) {
				$this->fs->delete( $this->root . $relative );
			}
		}

		foreach ( array_reverse( $journal['replaced'] ?? array() ) as $relative ) {
			if ( $this->fs->is_file( $backup . '/' . $relative ) ) {
				$this->rename( $backup . '/' . $relative, $this->root . $relative );
			}
		}

		foreach ( array_reverse( $journal['dirs'] ?? array() ) as $entry ) {
			$live = $this->root . $entry['dir'];

			if ( $this->fs->is_dir( $backup . '/' . $entry['dir'] ) || ! $entry['had_live'] ) {
				if ( $this->fs->is_dir( $live ) ) {
					$this->rename( $live, $failed . '/' . $entry['dir'] );
				}

				if ( $entry['had_live'] ) {
					$this->rename( $backup . '/' . $entry['dir'], $live );
				}
			}
		}

		$journal['stage']          = self::STAGE_ROLLED_BACK;
		$journal['rolled_back_at'] = gmdate( DATE_ATOM );
		$this->write_journal( $journal );

		$this->restore_flag( $journal['previous_flag'] ?? null );
		$this->remove( $failed );
		$this->remove( $backup );
		$this->discard_working_files();
	}

	/**
	 * Put back the maintenance flag that existed before the update, or clear ours.
	 *
	 * @param array|null $previous Flag contents before the update; null when there was none.
	 * @return void
	 */
	private function restore_flag( ?array $previous ) : void {
		if ( null === $previous ) {
			$this->flag->clear();
			return;
		}

		$this->flag->write(
			(string) ( $previous['reason'] ?? MaintenanceFlag::REASON_MAINTENANCE ),
			(string) ( $previous['message'] ?? '' ),
			(int) ( $previous['retry_after'] ?? 0 )
		);
	}

	/**
	 * The loose files a release replaces: its root files and allowed public
	 * files, never the installation's own; manifest.json last, so a manifest
	 * on disk always describes files that are in place.
	 *
	 * @param ReleaseManifest $manifest The new release's manifest.
	 * @return string[] Relative paths.
	 */
	private function root_files( ReleaseManifest $manifest ) : array {
		$files = array();

		foreach ( array_keys( $manifest->files ) as $relative ) {
			$top_level = ! str_contains( $relative, '/' );

			if ( ( $top_level && ! in_array( $relative, self::PROTECTED_FILES, true ) ) || in_array( $relative, self::PUBLIC_FILES, true ) ) {
				$files[] = $relative;
			}
		}

		$files[] = ReleaseManifest::FILE;

		return $files;
	}

	/**
	 * Check the release's PHP version and extension requirements.
	 *
	 * @param array $requires The manifest's "requires": php constraint and extensions.
	 * @return void
	 * @throws UpdateException When a requirement is not met.
	 */
	private function assert_requirements( array $requires ) : void {
		$php = (string) ( $requires['php'] ?? '' );

		if ( '' !== $php && false === self::php_satisfies( $php, PHP_VERSION ) ) {
			throw new UpdateException( sprintf( 'The new version needs PHP %s; this server runs %s.', $php, PHP_VERSION ) );
		}

		$missing = array_filter(
			(array) ( $requires['extensions'] ?? array() ),
			static fn( $extension ) : bool => is_string( $extension ) && ! extension_loaded( $extension )
		);

		if ( ! empty( $missing ) ) {
			throw new UpdateException( sprintf( 'The new version needs these PHP extensions: %s.', implode( ', ', $missing ) ) );
		}
	}

	/**
	 * Whether a PHP version satisfies a Composer-style constraint.
	 *
	 * Supports alternatives (||), several conditions (space or comma) and the
	 * operators >=, >, <=, <, =, !=, ^ and ~.
	 *
	 * @param string $constraint E.g. ">=8.4", "^8.4", ">=8.3 <9".
	 * @param string $version    The version to test.
	 * @return bool|null Null when the constraint cannot be read (treated as met).
	 */
	public static function php_satisfies( string $constraint, string $version ) : ?bool {
		foreach ( preg_split( '/\s*\|\|?\s*/', trim( $constraint ) ) ?: array() as $alternative ) {
			$all = true;

			foreach ( preg_split( '/[\s,]+/', trim( $alternative ) ) ?: array() as $condition ) {
				if ( 1 !== preg_match( '/^(>=|<=|!=|==|>|<|=|\^|~)?v?(\d+(?:\.\d+){0,2})$/', $condition, $m ) ) {
					return null;
				}

				$op    = '' === $m[1] ? '=' : $m[1];
				$bound = $m[2];
				$parts = array_map( 'intval', explode( '.', $bound ) );

				$ok = match ( $op ) {
					'^'     => version_compare( $version, $bound, '>=' ) && version_compare( $version, 0 === $parts[0] ? '0.' . ( ( $parts[1] ?? 0 ) + 1 ) : ( $parts[0] + 1 ) . '.0', '<' ),
					'~'     => version_compare( $version, $bound, '>=' ) && version_compare( $version, count( $parts ) > 2 ? $parts[0] . '.' . ( $parts[1] + 1 ) : ( $parts[0] + 1 ) . '.0', '<' ),
					'='     => 0 === strpos( $version . '.', $bound . '.' ),
					default => version_compare( $version, $bound, $op ),
				};

				$all = $all && $ok;
			}

			if ( $all ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract a zip, refusing entries that would land outside the target.
	 *
	 * @param string $zip    Zip path.
	 * @param string $target Directory to extract into.
	 * @return void
	 * @throws UpdateException When the zip cannot be read or holds unsafe paths.
	 */
	private function extract( string $zip, string $target ) : void {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new UpdateException( 'The zip PHP extension is needed to apply updates.' );
		}

		$archive = new ZipArchive();

		if ( true !== $archive->open( $zip, ZipArchive::RDONLY ) ) {
			throw new UpdateException( sprintf( 'Could not open %s as a zip archive.', basename( $zip ) ) );
		}

		$prefix = UpdateServer::APP_SLUG . '/';

		try {
			for ( $i = 0; $i < $archive->numFiles; $i++ ) {
				$name = (string) $archive->getNameIndex( $i );

				if (
					! str_starts_with( $name, $prefix )
					|| str_contains( $name, '\\' )
					|| str_contains( $name, ':' )
					|| in_array( '..', explode( '/', $name ), true )
				) {
					throw new UpdateException( sprintf( 'The package holds an unexpected path (%s); it was not extracted.', $name ) );
				}
			}

			if ( ! $archive->extractTo( $target ) ) {
				throw new UpdateException( sprintf( 'Could not extract %s; check the free disk space.', basename( $zip ) ) );
			}
		} finally {
			$archive->close();
		}
	}

	/**
	 * The staged release's root directory, with a trailing slash.
	 *
	 * @return string
	 */
	private function staged_root() : string {
		return $this->path( 'staging' ) . '/' . UpdateServer::APP_SLUG . '/';
	}

	/**
	 * A path in the working directory.
	 *
	 * @param string $name Entry name.
	 * @return string
	 */
	private function path( string $name ) : string {
		return $this->root . self::WORK_DIR . '/' . $name;
	}

	/**
	 * Rename or fail.
	 *
	 * @param string $from Source.
	 * @param string $to   Destination.
	 * @return void
	 * @throws UpdateException
	 */
	private function rename( string $from, string $to ) : void {
		if ( ! $this->fs->rename( $from, $to ) ) {
			throw new UpdateException( sprintf( 'Could not move %s to %s.', $this->relative( $from ), $this->relative( $to ) ) );
		}
	}

	/**
	 * Create a directory or fail.
	 *
	 * @param string $dir Directory.
	 * @return void
	 * @throws UpdateException
	 */
	private function make_dir( string $dir ) : void {
		if ( ! $this->fs->mkdir( $dir ) ) {
			throw new UpdateException( sprintf( 'Could not create %s; check the permissions of the application folder.', $this->relative( $dir ) ) );
		}
	}

	/**
	 * Remove a file or directory if it exists.
	 *
	 * @param string $path Path.
	 * @return void
	 */
	private function remove( string $path ) : void {
		if ( $this->fs->exists( $path ) ) {
			$this->fs->delete( $path, true );
		}
	}

	/**
	 * A path relative to the application root, for messages.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function relative( string $path ) : string {
		return str_starts_with( $path, $this->root ) ? substr( $path, strlen( $this->root ) ) : $path;
	}

	/**
	 * Take the update lock, so only one process prepares, applies or rolls back at a time.
	 *
	 * Held until unlock() or the end of the process. The finish step runs in a
	 * child of the process holding it and does not take it.
	 *
	 * @return bool False when another process holds it.
	 * @throws UpdateException When the lock file cannot be created.
	 */
	public function lock() : bool {
		if ( null !== $this->lock ) {
			return true;
		}

		$this->make_dir( $this->root . self::WORK_DIR );

		$handle = @fopen( $this->path( 'lock' ), 'c' );

		if ( false === $handle ) {
			throw new UpdateException( 'Could not create the update lock; check the permissions of the application folder.' );
		}

		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			return false;
		}

		$this->lock = $handle;

		return true;
	}

	/**
	 * Release the update lock.
	 *
	 * @return void
	 */
	public function unlock() : void {
		if ( null !== $this->lock ) {
			flock( $this->lock, LOCK_UN );
			fclose( $this->lock );
			$this->lock = null;
		}
	}

	/**
	 * Remove the staged copy, the downloads and the record of a waiting package.
	 *
	 * @return void
	 */
	public function discard_working_files() : void {
		$this->remove( $this->path( 'staging' ) );
		$this->remove( $this->path( 'downloads' ) );

		if ( null !== $this->state->update_section( InstallationState::UPDATE_READY ) ) {
			$this->state->set_update_section( InstallationState::UPDATE_READY, null );
		}
	}

	/**
	 * Whether a backup of the previous version exists, and when it was made.
	 *
	 * @return array{version: string, made_at: string}|null
	 */
	public function backup() : ?array {
		$journal = $this->read_journal();

		if ( null === $journal || ! $this->fs->is_dir( $this->path( 'backup' ) ) || self::STAGE_ROLLED_BACK === ( $journal['stage'] ?? null ) ) {
			return null;
		}

		return array( 'version' => (string) $journal['from'], 'made_at' => (string) ( $journal['started_at'] ?? '' ) );
	}

	/**
	 * Delete the backup of the previous version. Rollback is no longer possible afterwards.
	 *
	 * @return bool Whether there was a backup to delete.
	 * @throws UpdateException While an update is in progress.
	 */
	public function delete_backup() : bool {
		$this->assert_idle();

		$backup = $this->path( 'backup' );

		if ( ! $this->fs->is_dir( $backup ) ) {
			return false;
		}

		$this->remove( $backup );

		return true;
	}

	/**
	 * Read the update log from the installation state.
	 *
	 * @return array|null
	 */
	private function read_journal() : ?array {
		return $this->state->update_section( InstallationState::UPDATE_RUN );
	}

	/**
	 * Write the update log to the installation state.
	 *
	 * @param array $journal Journal.
	 * @return void
	 * @throws UpdateException When it cannot be written.
	 */
	private function write_journal( array $journal ) : void {
		try {
			$this->state->set_update_section( InstallationState::UPDATE_RUN, $journal );
		} catch ( \RuntimeException $e ) {
			throw new UpdateException( 'Could not record the update progress: ' . $e->getMessage(), 0, $e );
		}
	}
}