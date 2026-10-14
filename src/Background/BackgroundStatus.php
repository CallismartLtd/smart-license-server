<?php
/**
 * Background status class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Background
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Background;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Heartbeats of whatever runs background work (storage/background.json).
 *
 * Every runner records when it last ran and how, so the site can say
 * "the scheduler last ran 40 seconds ago from cron (PHP 8.4 CLI)" instead
 * of guessing from overdue tasks:
 *
 *   {
 *     "cron_url":  { "at": "...", "php": "8.4.21", "sapi": "fpm-fcgi", "release": "fastcgi_finish_request", "tasks": 2, "jobs": 5, "seconds": 1.4 },
 *     "cli_cron":  { ... },
 *     "worker":    { "at": "...", "php": "8.4.21", "sapi": "cli", "pid": 1234 }
 *   }
 *
 * A file, not settings: the cron URL and the worker write it often and
 * must not need the database to do so. Writes are locked and atomic.
 */
final class BackgroundStatus {

	/**
	 * Runner sources.
	 */
	public const SOURCE_CRON_URL      = 'cron_url';      // The public cron URL, called by an external service.
	public const SOURCE_CLI_CRON      = 'cli_cron';      // `smliser cron`, from crontab or a hosting panel.
	public const SOURCE_SCHEDULE_RUN  = 'schedule_run';  // `smliser schedule run`.
	public const SOURCE_WORKER        = 'worker';        // `smliser queue work`, a long-running worker.
	public const SOURCE_ADMIN         = 'admin';         // The admin Schedules and Queue Monitor buttons.

	/**
	 * File name, inside the storage directory.
	 */
	public const FILE = 'background.json';

	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs   Filesystem API.
	 * @param string     $file Absolute path to the status file.
	 */
	public function __construct(
		private FileSystem $fs,
		private string $file
	) {}

	/**
	 * Create the status for the standard runtime layout.
	 *
	 * @param FileSystem $fs
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self( $fs, rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/' . self::FILE );
	}

	/**
	 * Record that a runner ran now.
	 *
	 * @param string               $source One of the SOURCE_* constants.
	 * @param array<string, mixed> $data   What it did (tasks, jobs, seconds...).
	 * @return void
	 */
	public function record( string $source, array $data = array() ) : void {
		$entry = array(
			'at'   => gmdate( DATE_ATOM ),
			'php'  => PHP_VERSION,
			'sapi' => PHP_SAPI,
		) + $data;

		$lock_file	= $this->file . '.lock';
		$lock		= @fopen( $lock_file, 'c' );

		if ( false === $lock || ! flock( $lock, LOCK_EX ) ) {
			return;
		}

		try {
			$all            = $this->read();
			$all[ $source ] = $entry;

			$this->fs->put_contents( $this->file, json_encode( $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
			$this->fs->delete( $lock_file );
		}
	}

	/**
	 * Every recorded heartbeat.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function read() : array {
		if ( ! $this->fs->is_file( $this->file ) ) {
			return array();
		}

		$data = json_decode( (string) $this->fs->get_contents( $this->file ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * One runner's last heartbeat.
	 *
	 * @param string $source
	 * @return array<string, mixed>|null
	 */
	public function last( string $source ) : ?array {
		$entry = $this->read()[ $source ] ?? null;

		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * Seconds since a runner's last heartbeat.
	 *
	 * @param string $source
	 * @return int|null Null when it never ran.
	 */
	public function age( string $source ) : ?int {
		$at = strtotime( (string) ( $this->last( $source )['at'] ?? '' ) );

		return false === $at ? null : max( 0, time() - $at );
	}

	/**
	 * The most recent heartbeat of the runners that run due work (not the
	 * admin buttons), with its source.
	 *
	 * @return array{source: string, at: string, age: int}|null
	 */
	public function latest_runner() : ?array {
		$latest = null;

		foreach ( array( self::SOURCE_CLI_CRON, self::SOURCE_SCHEDULE_RUN, self::SOURCE_CRON_URL, self::SOURCE_WORKER ) as $source ) {
			$age = $this->age( $source );

			if ( null !== $age && ( null === $latest || $age < $latest['age'] ) ) {
				$latest = array( 'source' => $source, 'at' => (string) $this->last( $source )['at'], 'age' => $age );
			}
		}

		return $latest;
	}
}