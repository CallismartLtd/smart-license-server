<?php
/**
 * System health class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Health
 */

namespace SmartLicenseServer\Environments\Application\Health;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\DatabaseInfoDTO;
use Callismart\DBPrism\Inspection\Inspector;
use SmartLicenseServer\Background\BackgroundSetup;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\CodeChangeDetector;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Boot\InstallationState;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Environments\Application\Update\UpdateService;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Utils\Format;

/**
 * Diagnostics and health checks for the running application.
 *
 * The one source of this data: the admin Tools pages render it, the console
 * prints it. It knows nothing about requests, responses or templates, and
 * returns plain arrays any front end can display.
 *
 * Everything describes the PHP process that builds the report. Run from the
 * console, that is the CLI PHP: its version, ini limits and extensions can
 * differ from the web server's, and there is no web server or document root
 * to report (the document root check is skipped).
 *
 * Each public method builds one report from scratch. Within a report,
 * directory sizes and the database info are looked up once.
 */
class SystemHealth {

	/**
	 * Directory sizes already computed for the current report, keyed by
	 * normalized path.
	 *
	 * Several diagnostics report the size of the same path (e.g.
	 * SMLISER_ROOT is both the "Application Root" required directory
	 * and the base of the total installation size), so each path is
	 * walked at most once per report.
	 *
	 * @var array<string, int>
	 */
	private array $dir_sizes = [];

	/**
	 * Lazily created inspector for the active database connection.
	 *
	 * @var Inspector|null
	 */
	private ?Inspector $inspector = null;

	/**
	 * Database info returned by the inspector, once fetched for the current report.
	 *
	 * @var DatabaseInfoDTO|null
	 */
	private ?DatabaseInfoDTO $database_info = null;

	/**
	 * The error raised by the first failed database info lookup, if any,
	 * so later callers fail the same way without querying again.
	 *
	 * @var \Throwable|null
	 */
	private ?\Throwable $database_info_error = null;

	/**
	 * Minutes a job may wait past its available time before it counts as delayed.
	 *
	 * @var int
	 */
	private const JOB_DELAY_WARNING_MINUTES = 10;

	/**
	 * Minutes past which a delayed job makes the check critical.
	 *
	 * @var int
	 */
	private const JOB_DELAY_CRITICAL_MINUTES = 60;

	/**
	 * Minutes a scheduled task may run late before it counts as overdue.
	 *
	 * Cron calls the scheduler every minute; this leaves room for a slow
	 * run without reporting a working setup.
	 *
	 * @var int
	 */
	private const TASK_OVERDUE_MINUTES = 15;

	/**
	 * Hours after which an update check counts as stale (it runs every 12 hours).
	 *
	 * @var int
	 */
	private const UPDATE_CHECK_STALE_HOURS = 48;

	public function __construct(
		protected Scheduler $scheduler,
		protected JobQueue $job_queue,
		protected URLManager $urlmanager,
		protected Settings $settings,
		protected AppInstaller $installer,
		protected Database $db,
		protected FileSystem $fs,
		protected SchemaRegistry $schema,
		protected UpdateService $updates,
		protected InstallationState $state,
		protected MaintenanceFlag $maintenance,
		protected CodeChangeDetector $code_changes,
		protected BackgroundSetup $background
	) {}

	/*
	|--------------------
	| REPORTS
	|--------------------
	*/

	/**
	 * Read-only facts about the running environment, grouped by category
	 * (PHP, database, directories, background processing).
	 *
	 * Nothing here is a pass/fail judgment; that is what checks() is for.
	 * Only the requested sections are built, so asking for some spares the
	 * work of the others. The Server and Directories sections are slow:
	 * they walk the installation folder for its size.
	 *
	 * @param string[]|null $sections Section names from diagnostic_sections(), in
	 *                                the order wanted; null for all, in their own order.
	 * @return array<string, array<string, string>> Section => [ label => value ].
	 * @throws \InvalidArgumentException When a section name is unknown.
	 */
	public function diagnostics( ?array $sections = null ) : array {
		$builders = $this->diagnostic_builders();
		$sections ??= \array_keys( $builders );
		$unknown  = \array_diff( $sections, \array_keys( $builders ) );

		if ( [] !== $unknown ) {
			throw new \InvalidArgumentException( \sprintf( 'Unknown diagnostics section(s): %s.', \implode( ', ', $unknown ) ) );
		}

		$this->fresh();

		$diagnostics = [];

		foreach ( $sections as $section ) {
			$diagnostics[ $section ] = $builders[ $section ]();
		}

		return $diagnostics;
	}

	/**
	 * The names of the diagnostics sections, in display order.
	 *
	 * @return string[]
	 */
	public function diagnostic_sections() : array {
		return \array_keys( $this->diagnostic_builders() );
	}

	/**
	 * The quick pass/info/warning/critical checks against the running environment.
	 *
	 * Each check that is not passing carries a concrete recommendation.
	 * The database connection is checked separately (database_check()).
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	public function checks() : array {
		$this->fresh();

		return $this->run_health_checks();
	}

	/**
	 * Check the database connection.
	 *
	 * Separate from checks(): inspecting the connection also measures the
	 * database size, which can be slow.
	 *
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}
	 */
	public function database_check() : array {
		$this->fresh();

		try {
			$info  = $this->get_database_info();
			$check = $this->make_check(
				'database_connection',
				'Database Connection',
				'pass',
				\sprintf( 'Connected to %s%s.', $info->product ?? $info->engine, $info->version ? " {$info->version}" : '' )
			);
		} catch ( \Throwable $e ) {
			$check = $this->make_check(
				'database_connection',
				'Database Connection',
				'critical',
				'Unable to inspect the active database connection.',
				$e->getMessage()
			);
		}

		return $check;
	}

	/**
	 * The header of a support report: what is installed, where, and when.
	 *
	 * @return array{product: string, version: string, schema: string, site: string}
	 */
	public function report_meta() : array {
		return [
			'product' => \SMLISER_APP_NAME,
			'version' => \SMLISER_VER,
			'schema'  => \SMLISER_DB_VER,
			'site'    => (string) $this->urlmanager->url(),
		];
	}

	/**
	 * Start a new report: forget what the previous one looked up.
	 *
	 * The caches only spare one report from repeating work. A long-running
	 * process (the interactive console, a worker) may build several reports,
	 * and each must see the system as it is now.
	 *
	 * @return void
	 */
	private function fresh() : void {
		$this->dir_sizes           = [];
		$this->database_info       = null;
		$this->database_info_error = null;
	}

	/*
	|--------------------
	| CACHED LOOKUPS
	|--------------------
	*/

	/**
	 * Get the recursive size of a directory in bytes, walking each
	 * path at most once per report.
	 *
	 * @param string $path Absolute directory path.
	 * @return int
	 */
	private function get_dir_size( string $path ) : int {
		$key = \rtrim( $path, '/\\' );

		if ( ! isset( $this->dir_sizes[ $key ] ) ) {
			$this->dir_sizes[ $key ] = (int) \smliser_dirsize( $path, false );
		}

		return $this->dir_sizes[ $key ];
	}

	/**
	 * Get the inspector for the active database connection.
	 *
	 * @return Inspector
	 */
	private function get_inspector() : Inspector {
		return $this->inspector ??= new Inspector( $this->db );
	}

	/**
	 * Get the active database info, querying the inspector at most once
	 * per report. A failed lookup is remembered and rethrown on later
	 * calls in the same report rather than retried.
	 *
	 * @return DatabaseInfoDTO The DatabaseInfoDTO returned by Inspector::get_database_info().
	 * @throws \Throwable When the database cannot be inspected.
	 */
	private function get_database_info() : DatabaseInfoDTO {
		if ( null !== $this->database_info_error ) {
			throw $this->database_info_error;
		}

		if ( null === $this->database_info ) {
			try {
				$this->database_info = $this->get_inspector()->get_database_info();
			} catch ( \Throwable $e ) {
				$this->database_info_error = $e;
				throw $e;
			}
		}

		return $this->database_info;
	}

	/*
	|--------------------
	| DIAGNOSTICS DATA
	|--------------------
	*/

	/**
	 * The builder of each diagnostics section, keyed by section name in
	 * display order.
	 *
	 * @return array<string, callable(): array<string, string>>
	 */
	private function diagnostic_builders() : array {
		return [
			'Server'                => fn() : array => $this->collect_server_diagnostics(),
			'Installation'          => fn() : array => $this->collect_installation_diagnostics(),
			'Updates'               => fn() : array => $this->collect_update_diagnostics(),
			'Required Extensions'   => fn() : array => $this->collect_required_extension_diagnostics(),
			'Loaded Extensions'     => fn() : array => $this->collect_loaded_extension_diagnostics(),
			'Database'              => fn() : array => $this->collect_database_diagnostics(),
			'Directories'           => fn() : array => $this->collect_directory_diagnostics(),
			'Background Processing' => fn() : array => $this->collect_background_diagnostics(),
		];
	}

	/**
	 * Report the queue, the scheduled tasks and log retention.
	 *
	 * @return array<string, string>
	 */
	private function collect_background_diagnostics() : array {
		$queue_stats = [];
		foreach ( [ 'pending', 'running', 'failed', 'completed' ] as $status ) {
			$queue_stats[ $status ] = $this->job_queue->count_jobs_by_status( $status );
		}

		$tasks   = $this->scheduler->get_tasks_with_state();
		$waiting = $this->delayed_jobs( 0 );
		$overdue = $this->overdue_tasks();

		return [
			'Pending Jobs'   => (string) $queue_stats['pending'],
			'Running Jobs'   => (string) $queue_stats['running'],
			'Failed Jobs'    => (string) $queue_stats['failed'],
			'Completed Jobs' => (string) $queue_stats['completed'],
			'Waiting Since'  => null === $waiting['oldest']
				? 'No job is waiting'
				: \sprintf( '%d job(s) ready to run; the oldest since %s', $waiting['count'], $this->format_time( $waiting['oldest'] ) ),
			'Scheduled Tasks' => (string) \count( $tasks ),
			'Overdue Tasks'   => empty( $overdue )
				? 'None'
				: \implode( ', ', \array_map( static fn( array $task ) : string => $task['label'], $overdue ) ),
			'Last Task Run' => $this->format_time( $this->last_task_run() ) ?: 'Never',
			'Log Retention' => (string) $this->settings->get( Settings::LOG_RETENTION_DAYS, 30 ) . ' days',
		];
	}

	/**
	 * Report the state of what AppInstaller sets up beyond directories
	 * and extensions: config files, registered tables, and default roles.
	 *
	 * @return array<string, string>
	 */
	private function collect_installation_diagnostics() : array {
		$state    = $this->state->read();
		$versions = \is_array( $state ) && \is_array( $state['versions'] ?? null ) ? $state['versions'] : [];
		$flag     = $this->maintenance->read();

		$rows = [
			'State File' => match ( true ) {
				false === $state => 'Damaged or unreadable (' . $this->state->path() . ')',
				null === $state  => 'Missing (' . $this->state->path() . ')',
				default          => 'OK',
			},
			'Installed'               => \is_array( $state ) && ! empty( $state['installed_at'] ) ? (string) $state['installed_at'] : 'No',
			'Running Code Version'    => \SMLISER_VER,
			'Recorded App Version'    => (string) ( $versions['app'] ?? 'Not recorded' ),
			'Code Schema Version'     => \SMLISER_DB_VER,
			'Recorded Schema Version' => (string) ( $versions['schema'] ?? 'Not recorded' ),
			'Maintenance Mode'        => null === $flag
				? 'Off'
				: \sprintf( 'On (%s%s)', $this->maintenance->reason(), empty( $flag['since'] ) ? '' : ', since ' . $flag['since'] ),
			'.env File'                    => Format::yes_no( \file_exists( \SMLISER_ROOT . '.env' ) ),
			'.htaccess File (Apache only)' => Format::yes_no( \file_exists( \SMLISER_ROOT . 'public/.htaccess' ) ),
		];

		try {
			$inspector = $this->get_inspector();
			$tables    = $this->schema->get_all_tables();
			$missing   = [];

			foreach ( $tables as $table ) {
				if ( ! $inspector->table_exists( $table->get_name() ) ) {
					$missing[] = $table->get_name();
				}
			}

			$rows['Database Tables'] = empty( $missing )
				? \sprintf( 'All %d present', \count( $tables ) )
				: \sprintf( '%d of %d missing (%s)', \count( $missing ), \count( $tables ), \implode( ', ', $missing ) );
		} catch ( \Throwable $e ) {
			$rows['Database Tables'] = 'Unable to inspect';
		}

		$roles         = DefaultRoles::all();
		$missing_roles = [];

		foreach ( \array_keys( $roles ) as $slug ) {
			if ( ! Role::get_by_slug( $slug ) ) {
				$missing_roles[] = $slug;
			}
		}

		$rows['Default Roles'] = empty( $missing_roles )
			? \sprintf( 'All %d installed', \count( $roles ) )
			: \sprintf( '%d of %d missing (%s)', \count( $missing_roles ), \count( $roles ), \implode( ', ', $missing_roles ) );

		return $rows;
	}

	/**
	 * Report the update status recorded in state.json, without contacting the update server.
	 *
	 * @return array<string, string>
	 */
	private function collect_update_diagnostics() : array {
		$overview = $this->updates->overview();
		$check    = $overview['check'];
		$run      = $overview['run'];
		$attempt  = $overview['attempt'];
		$backup   = $overview['backup'];
		$ready    = $overview['ready'];

		return [
			'Automatic Updates' => match ( $overview['auto'] ) {
				UpdateService::AUTO_OFF => 'Off',
				UpdateService::AUTO_ALL => 'All releases',
				default                 => 'Security releases only',
			},
			'Last Check'     => '' === (string) $check['checked_at'] ? 'Never' : (string) $check['checked_at'],
			'Latest Release' => null === $check['latest']
				? 'Unknown'
				: $check['latest'] . ( $check['security'] ? ' (security release)' : '' ) . ( $check['available'] ? ' (available)' : ' (installed)' ),
			'Last Check Error' => (string) ( $check['error'] ?? '' ) ?: 'None',
			'Update Blockers'  => empty( $overview['blockers'] ) ? 'None' : \implode( ' ', $overview['blockers'] ),
			'Queued Install'   => null === $overview['queued']
				? 'None'
				: \sprintf( '%s, by %s at %s', $overview['queued']['version'] ?? '?', $overview['queued']['by'] ?? '?', $overview['queued']['at'] ?? '?' ),
			'Verified Package' => null === $ready ? 'None' : \sprintf( '%s, prepared %s', $ready['version'] ?? '?', $ready['prepared_at'] ?? '?' ),
			'Last Update'      => null === $run
				? 'None'
				: \sprintf( '%s to %s: %s (started %s)', $run['from'] ?? '?', $run['to'] ?? '?', $run['stage'] ?? '?', $run['started_at'] ?? '?' ),
			'Last Queued Attempt' => null === $attempt
				? 'None'
				: \sprintf( '%s %s at %s', $attempt['action'] ?? '?', ! empty( $attempt['ok'] ) ? 'succeeded' : 'failed', $attempt['at'] ?? '?' ),
			'Backup' => null === $backup ? 'None' : \sprintf( 'Version %s, made %s', $backup['version'], $backup['made_at'] ),
		];
	}

	/**
	 * Jobs ready to run that have waited longer than a number of minutes.
	 *
	 * A ready job that keeps waiting means no worker is taking jobs. Looks
	 * at the first 100 pending and retrying jobs, which is enough to tell.
	 *
	 * @param int $minutes Minutes past the job's available time.
	 * @return array{count: int, oldest: ?\DateTimeImmutable}
	 */
	private function delayed_jobs( int $minutes ) : array {
		$cutoff = \time() - $minutes * 60;
		$count  = 0;
		$oldest = null;

		foreach ( [ 'pending', JobDTO::STATUS_RETRYING ] as $status ) {
			foreach ( $this->job_queue->get_jobs_by_status( 1, 100, $status ) as $job ) {
				$available = $job->get( JobDTO::KEY_AVAILABLE_AT );

				if ( ! $available instanceof \DateTimeInterface || $available->getTimestamp() > $cutoff ) {
					continue;
				}

				++$count;

				if ( null === $oldest || $available < $oldest ) {
					$oldest = \DateTimeImmutable::createFromInterface( $available );
				}
			}
		}

		return [ 'count' => $count, 'oldest' => $oldest ];
	}

	/**
	 * Scheduled tasks that should have run more than TASK_OVERDUE_MINUTES ago.
	 *
	 * A task that has never run counts once the installation is older than
	 * that grace period too.
	 *
	 * @return array<int, array{id: string, label: string, due_at: ?\DateTimeImmutable}>
	 */
	private function overdue_tasks() : array {
		$cutoff    = \time() - self::TASK_OVERDUE_MINUTES * 60;
		$state     = $this->state->read();
		$installed = \is_array( $state ) && ! empty( $state['installed_at'] ) ? \strtotime( (string) $state['installed_at'] ) : false;
		$overdue   = [];

		foreach ( $this->scheduler->get_tasks_with_state() as $id => $data ) {
			$next = $data['state']['next_run_at'];

			$late = null !== $next
				? $next->getTimestamp() < $cutoff
				: null === $data['state']['last_ran_at'] && false !== $installed && $installed < $cutoff;

			if ( $late ) {
				$overdue[] = [ 'id' => (string) $id, 'label' => $data['task']->get_label(), 'due_at' => $next ];
			}
		}

		return $overdue;
	}

	/**
	 * When any scheduled task last ran.
	 *
	 * @return \DateTimeImmutable|null
	 */
	private function last_task_run() : ?\DateTimeImmutable {
		$latest = null;

		foreach ( $this->scheduler->get_tasks_with_state() as $data ) {
			$ran = $data['state']['last_ran_at'];

			if ( null !== $ran && ( null === $latest || $ran > $latest ) ) {
				$latest = $ran;
			}
		}

		return $latest;
	}

	/**
	 * Format a time for display, in the site's date format (UTC times are converted).
	 *
	 * @param \DateTimeInterface|null $time
	 * @return string Empty for null.
	 */
	private function format_time( ?\DateTimeInterface $time ) : string {
		if ( null === $time ) {
			return '';
		}

		return \DateTimeImmutable::createFromInterface( $time )
			->setTimezone( new \DateTimeZone( \date_default_timezone_get() ) )
			->format( \smliser_datetime_format() );
	}

	/**
	 * Gather PHP extension availability against what this application
	 * actually requires or recommends — mirroring exactly what
	 * AppInstaller::verify_environment_sanity() checks, so this page
	 * and the installer can never silently disagree about which
	 * extensions matter or whether one is loaded.
	 *
	 * "zip" is the only hard requirement (package uploads/updates fail
	 * without it). The database drivers and persistent cache adapters
	 * are "at least one of" groups — PHP ships several of the mysqli/
	 * sqlite3/pdo_* pair by default on most distributions, so these
	 * exist mainly to flag when NONE of a group is present, not to
	 * demand every one of them.
	 *
	 * @return array<string, string>
	 */
	private function collect_required_extension_diagnostics() : array {
		$rows = [
			'Zip (required)' => Format::yes_no( \extension_loaded( 'zip' ) ),
		];

		$db_extensions = [
			'mysqli'     => 'MySQL (mysqli)',
			'pdo_mysql'  => 'MySQL (PDO)',
			'sqlite3'    => 'SQLite3',
			'pdo_sqlite' => 'SQLite (PDO)',
			'pdo_pgsql'  => 'PostgreSQL (PDO)',
			'pdo'        => 'PDO Core',
		];

		foreach ( $db_extensions as $ext => $label ) {
			$rows[ 'DB Driver: ' . $label ] = Format::yes_no( \extension_loaded( $ext ) );
		}

		$cache_extensions = [
			'redis'     => 'Redis',
			'memcached' => 'Memcached',
			'apcu'      => 'APCu',
			'sqlite3'   => 'SQLite3',
		];

		$active_caches = [];
		foreach ( $cache_extensions as $ext => $label ) {
			$rows[ 'Cache: ' . $label ] = Format::yes_no( \extension_loaded( $ext ) );

			if ( \extension_loaded( $ext ) ) {
				$active_caches[] = $label;
			}
		}

		$rows['Persistent Cache Available'] = empty( $active_caches )
			? 'No — falling back to in-memory array cache'
			: 'Yes (' . \implode( ', ', $active_caches ) . ')';

		return $rows;
	}

	/**
	 * List every PHP extension currently loaded in this environment,
	 * regardless of whether this application uses it.
	 *
	 * This is a general inventory for support/debugging purposes — not
	 * scoped to app requirements (see collect_required_extension_diagnostics()
	 * for that). Sorted alphabetically for scanability.
	 *
	 * @return array<string, string>
	 */
	private function collect_loaded_extension_diagnostics() : array {
		$extensions = \get_loaded_extensions();
		\sort( $extensions, \SORT_NATURAL | \SORT_FLAG_CASE );

		$rows = [];
		foreach ( $extensions as $extension ) {
			$version            = \phpversion( $extension );
			$rows[ $extension ] = ( false !== $version && '' !== $version ) ? $version : 'Loaded';
		}

		return $rows;
	}

	/**
	 * Gather server/runtime facts — PHP, OS, architecture, web server
	 * software, OPcache status/statistics, and total installation size
	 * (application directory + database, when database size can be
	 * determined).
	 *
	 * @return array<string, string>
	 */
	private function collect_server_diagnostics() : array {
		$rows = [
			'PHP Version'         => PHP_VERSION,
			'PHP SAPI'            => \PHP_SAPI,
			'Operating System'    => \PHP_OS_FAMILY,
			'Server Architecture' => $this->format_architecture(),
			'Web Server'          => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'Document Root'       => (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ) ?: 'Unknown',
			'Memory Limit'        => \ini_get( 'memory_limit' ) ?: 'Unknown',
			'Max Execution Time'  => \ini_get( 'max_execution_time' ) . 's',
			'Timezone'            => \date_default_timezone_get(),
			'Server Time'         => ( new \DateTimeImmutable() )->format( \smliser_datetime_format() ),
			'Zip Extension'       => Format::yes_no( \extension_loaded( 'zip' ) ),
		];

		$rows                            = \array_merge( $rows, $this->collect_opcache_diagnostics() );
		$rows['Total Installation Size'] = $this->collect_installation_size();

		return $rows;
	}

	/**
	 * Build a clean "CPU architecture (bit-width)" string.
	 *
	 * php_uname( 'm' ) reports the raw machine type as the kernel names
	 * it (e.g. "x86_64", "aarch64", "AMD64", "arm64") — normalized here
	 * to a small set of readable labels rather than shown verbatim,
	 * since the same architecture is spelled differently across
	 * Linux/Windows/macOS. Bit-width comes from PHP_INT_SIZE rather
	 * than the machine string, since that reflects the actual PHP
	 * build running (relevant if a 32-bit PHP is installed on 64-bit
	 * hardware).
	 *
	 * @return string
	 */
	private function format_architecture() : string {
		$machine = \strtolower( \php_uname( 'm' ) );

		$label = match ( true ) {
			\in_array( $machine, [ 'x86_64', 'amd64' ], true )              => 'x86_64',
			\in_array( $machine, [ 'aarch64', 'arm64' ], true )             => 'ARM64',
			\str_starts_with( $machine, 'armv' )                            => 'ARM',
			\in_array( $machine, [ 'i386', 'i486', 'i586', 'i686' ], true ) => 'x86',
			default                                                         => \php_uname( 'm' ), // Unrecognized — show raw rather than guess.
		};

		return \sprintf( '%s (%d-bit)', $label, PHP_INT_SIZE * 8 );
	}

	/**
	 * Compute the total installation size: the application root
	 * directory (SMLISER_ROOT), recursively, plus the database size
	 * when the active inspector can determine it.
	 *
	 * @return string
	 */
	private function collect_installation_size() : string {
		$app_size_bytes = $this->get_dir_size( \SMLISER_ROOT );

		try {
			$db_size_bytes = $this->get_database_info()->size_bytes;
		} catch ( \Throwable $e ) {
			$db_size_bytes = null;
		}

		if ( null === $db_size_bytes ) {
			return \sprintf(
				'%s (application directory only — database size could not be determined)',
				Format::bytes( $app_size_bytes )
			);
		}

		return Format::bytes( $app_size_bytes + $db_size_bytes );
	}

	/**
	 * Gather OPcache status and statistics, when the extension is
	 * loaded and enabled.
	 *
	 * @return array<string, string>
	 */
	private function collect_opcache_diagnostics() : array {
		if ( ! \function_exists( 'opcache_get_status' ) ) {
			return [ 'OPcache' => 'Not available' ];
		}

		$status = @\opcache_get_status( false );

		if ( false === $status || empty( $status['opcache_enabled'] ) ) {
			return [ 'OPcache' => 'Disabled' ];
		}

		$memory = $status['memory_usage'] ?? [];
		$used   = (int) ( $memory['used_memory'] ?? 0 );
		$free   = (int) ( $memory['free_memory'] ?? 0 );
		$wasted = (int) ( $memory['wasted_memory'] ?? 0 );
		$total  = $used + $free + $wasted;

		$interned       = $status['interned_strings_usage'] ?? [];
		$interned_used  = (int) ( $interned['used_memory'] ?? 0 );
		$interned_total = $interned_used + (int) ( $interned['free_memory'] ?? 0 );

		$stats    = $status['opcache_statistics'] ?? [];
		$hit_rate = isset( $stats['opcache_hit_rate'] ) ? \round( (float) $stats['opcache_hit_rate'], 2 ) : null;

		return [
			'OPcache'              => 'Enabled',
			'OPcache Memory Usage' => $total > 0
				? \sprintf( '%s / %s (%s used)', Format::bytes( $used ), Format::bytes( $total ), Format::percent( $used / $total ) )
				: Format::bytes( $used ),
			'OPcache Interned Strings' => $interned_total > 0
				? \sprintf( '%s / %s (%s used)', Format::bytes( $interned_used ), Format::bytes( $interned_total ), Format::percent( $interned_used / $interned_total ) )
				: Format::bytes( $interned_used ),
			'OPcache Hit Rate'   => null !== $hit_rate ? $hit_rate . '%' : 'Unknown',
			'OPcache Cache Full' => Format::yes_no( ! empty( $status['cache_full'] ) ),
		];
	}

	/**
	 * Check existence/readability/writability/size of every directory
	 * the application requires, using the same list AppInstaller uses
	 * during setup.
	 *
	 * Per-directory size only — the combined installation size (which
	 * includes the database) is reported separately under Server, not
	 * summed here, since summing these specific directories would
	 * double-count nested paths (several of these are subdirectories
	 * of SMLISER_ROOT) and still wouldn't include the database.
	 *
	 * Sizes come from get_dir_size(), so a path already walked for the
	 * installation size (SMLISER_ROOT) is not walked again.
	 *
	 * @return array<string, string>
	 */
	private function collect_directory_diagnostics() : array {
		$rows = [];

		foreach ( $this->installer->get_required_directories() as $label => $path ) {
			if ( ! $this->fs->is_dir( $path ) ) {
				$rows[ $label ] = 'Missing';
				continue;
			}

			$readable = \is_readable( $path );
			$writable = \is_writable( $path );
			$size     = $this->get_dir_size( $path );

			$rows[ $label ] = \sprintf(
				'Readable: %s, Writable: %s, Size: %s',
				Format::yes_no( $readable ),
				Format::yes_no( $writable ),
				Format::bytes( $size )
			);
		}

		return $rows;
	}

	/**
	 * Gather database engine/connection facts via the DBPrism Inspector.
	 *
	 * Only non-null fields from DatabaseInfoDTO are shown — not every
	 * engine exposes every field (e.g. SQLite has no port/server).
	 *
	 * @return array<string, string>
	 */
	private function collect_database_diagnostics() : array {
		try {
			$info = $this->get_database_info();
		} catch ( \Throwable $e ) {
			return [ 'Status' => 'Unable to inspect connection: ' . $e->getMessage() ];
		}

		$rows = [
			// Engine & Driver Identity
			'Engine'  => $info->engine,
			'Product' => $info->product,
			'Version' => $info->version,
			'Driver'  => $this->db->get_adapter()::class,

			// Database & Schema.
			'Database'  => $info->database,
			'Schema'    => $info->schema,
			'Size'      => null !== $info->size_bytes ? Format::bytes( $info->size_bytes ) : 'Unknown',
			'Charset'   => $info->charset,
			'Collation' => $info->collation,
			'Timezone'  => $info->timezone,

			// Connection & Network
			'Server'           => $info->server,
			'Port'             => null !== $info->port ? (string) $info->port : null,
			'Transport'        => $info->transport,
			'Protocol Version' => null !== $info->protocol_version ? (string) $info->protocol_version : null,
			'SSL'              => null !== $info->ssl ? Format::yes_no( $info->ssl ) : null,

			// Host Server Environment
			'Server Hostname' => $info->server_hostname,
			'Server OS'       => $info->server_os,
		];

		return \array_filter( $rows, static fn( $value ) => null !== $value && '' !== $value );
	}

	/*
	|--------------------
	| HEALTH CHECKS
	|--------------------
	*/

	/**
	 * Run a set of pass/warning/critical checks against the running
	 * environment.
	 *
	 * Only checks that are quick: the site health page runs these on every
	 * load. Anything that queries the database heavily or contacts another
	 * server is a separate check (see database_check(), and SystemManagement).
	 *
	 * Reuses AppInstaller::verify_environment_sanity() for PHP version,
	 * required extensions, database driver availability, and persistent
	 * cache — the same logic the installer itself relies on — so this
	 * page and the installer can never silently disagree about what
	 * "healthy" means.
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	private function run_health_checks() : array {
		$checks = [];

		$this->installer->verify_environment_sanity(
			function ( string $check, string $status, string $message ) use ( &$checks ) {
				// 'OK' (critical check, passing) and 'RECOMMENDED' both
				// arrive via the success callback.
				$checks[] = [
					'id'             => Format::slugify( $check ),
					'label'          => $check,
					'status'         => 'RECOMMENDED' === $status ? 'info' : 'pass',
					'message'        => $message,
					'recommendation' => null,
				];
			},
			function ( string $check, string $status, string $message ) use ( &$checks ) {
				// 'CRITICAL' and 'WARNING' both arrive via the failure callback.
				$checks[] = [
					'id'             => Format::slugify( $check ),
					'label'          => $check,
					'status'         => 'CRITICAL' === $status ? 'critical' : 'warning',
					'message'        => $message,
					'recommendation' => $message,
				];
			}
		);

		// Directory read/write checks, same list AppInstaller uses.
		foreach ( $this->installer->get_required_directories() as $label => $path ) {
			$exists   = $this->fs->is_dir( $path );
			$readable = $exists && \is_readable( $path );
			$writable = $exists && \is_writable( $path );
			$ok       = $exists && $readable && $writable;

			$checks[] = [
				'id'      => 'dir_' . Format::slugify( $label ),
				'label'   => $label,
				'status'  => $ok ? 'pass' : 'critical',
				'message' => match ( true ) {
					! $exists   => \sprintf( '%s does not exist.', $path ),
					! $readable => \sprintf( '%s exists but is not readable.', $path ),
					! $writable => \sprintf( '%s exists but is not writable.', $path ),
					default     => \sprintf( '%s is readable and writable.', $path ),
				},
				'recommendation' => $ok
					? null
					: \sprintf( 'Create %s if missing and ensure the web server process can read and write to it.', $path ),
			];
		}

		// Web server document root: only public/ should be reachable.
		$document_root_check = $this->check_document_root();

		if ( null !== $document_root_check ) {
			$checks[] = $document_root_check;
		}

		// Database Connection is a separate check (database_check()):
		// inspecting the connection also measures the database size, which can be slow.

		// Failed/stuck jobs.
		$failed_count = $this->job_queue->count_jobs_by_status( 'failed' );
		$checks[]     = [
			'id'      => 'failed_jobs',
			'label'   => 'Failed Jobs',
			'status'  => 0 === $failed_count ? 'pass' : ( $failed_count < 50 ? 'warning' : 'critical' ),
			'message' => 0 === $failed_count
				? 'No failed jobs in the queue.'
				: \sprintf( '%d failed job(s) in the archive.', $failed_count ),
			'recommendation' => 0 === $failed_count
				? null
				: 'Review the Queue Monitor → Failed tab to diagnose recurring failures.',
		];

		$running_count = $this->job_queue->count_jobs_by_status( 'running' );
		$checks[]      = [
			'id'      => 'stuck_jobs',
			'label'   => 'Jobs Stuck Running',
			'status'  => 0 === $running_count ? 'pass' : 'warning',
			'message' => 0 === $running_count
				? 'No jobs currently running.'
				: \sprintf( '%d job(s) currently in a running state.', $running_count ),
			'recommendation' => 0 === $running_count
				? null
				: 'If this count stays elevated across page reloads, a worker may have died mid-job. Run `queue release-stale` from the CLI.',
		];

		// Scheduled task errors.
		$tasks_with_errors = 0;
		foreach ( $this->scheduler->get_tasks_with_state() as $data ) {
			if ( ! empty( $data['state']['last_error'] ) ) {
				$tasks_with_errors++;
			}
		}
		$checks[] = [
			'id'      => 'scheduled_task_errors',
			'label'   => 'Scheduled Task Errors',
			'status'  => 0 === $tasks_with_errors ? 'pass' : 'critical',
			'message' => 0 === $tasks_with_errors
				? 'All scheduled tasks last ran without error.'
				: \sprintf( '%d scheduled task(s) recorded an error on their last run.', $tasks_with_errors ),
			'recommendation' => 0 === $tasks_with_errors
				? null
				: 'Open the Schedules tab and review the error detail for each affected task.',
		];

		return \array_merge(
			$checks,
			$this->installation_state_checks(),
			$this->background_health_checks(),
			$this->update_health_checks()
		);
	}

	/**
	 * Installation state checks: the state file, maintenance mode, and an
	 * update whose finish step has not run.
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	private function installation_state_checks() : array {
		$checks = [];
		$state  = $this->state->read();

		if ( false === $state ) {
			$checks[] = $this->make_check(
				'installation_state',
				'Installation State',
				'critical',
				\sprintf( '%s is damaged or unreadable. Updates and the installer refuse to change it.', $this->state->path() ),
				'Restore the file from a backup, or check its permissions. Do not delete it: it records that this site is installed.'
			);
		}

		$reason = $this->maintenance->reason();

		if ( null !== $reason ) {
			$checks[] = $this->make_check(
				'maintenance_mode',
				'Maintenance Mode',
				MaintenanceFlag::REASON_UPDATE === $reason ? 'critical' : 'warning',
				MaintenanceFlag::REASON_UPDATE === $reason
					? 'The site is in maintenance because an update is being installed or did not finish. Visitors cannot use the site.'
					: \sprintf(
						'The site is in maintenance mode (%s). Visitors cannot use the site%s.',
						$reason,
						$this->maintenance->background_paused() ? ', and scheduled tasks and queued jobs wait until it ends' : ''
					),
				MaintenanceFlag::REASON_UPDATE === $reason
					? 'If no update is running, open the Updates page, or run `smliser update status` to finish or roll it back.'
					: 'Turn maintenance mode off once the work is done: Tools > Maintenance, or `smliser maintenance off`.'
			);
		}

		if ( CodeChangeDetector::REASON_CHANGED === $this->code_changes->reason() ) {
			$checks[] = $this->make_check(
				'unfinished_update',
				'Unfinished Update',
				'critical',
				\sprintf(
					'The code on disk is version %s, but state.json records %s: an update was installed but its finish step has not run. Scheduled tasks and queued jobs are paused until it has.',
					\SMLISER_VER,
					$this->code_changes->installed_version() ?? 'another version'
				),
				'Run `smliser update finish` from the console, or roll the update back.'
			);
		}

		return $checks;
	}

	/**
	 * Background processing checks: delayed queue jobs and overdue scheduled tasks.
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	private function background_health_checks() : array {
		$checks  = $this->background_setup_checks();
		$delayed = $this->delayed_jobs( self::JOB_DELAY_WARNING_MINUTES );

		if ( 0 === $delayed['count'] ) {
			$checks[] = $this->make_check( 'delayed_jobs', 'Queue Worker', 'pass', 'No job has been waiting to run for more than ' . self::JOB_DELAY_WARNING_MINUTES . ' minutes.' );
		} else {
			$critical = $delayed['oldest']->getTimestamp() < \time() - self::JOB_DELAY_CRITICAL_MINUTES * 60;
			$checks[] = $this->make_check(
				'delayed_jobs',
				'Queue Worker',
				$critical ? 'critical' : 'warning',
				\sprintf(
					'%d job(s) are ready to run but have not been picked up; the oldest has waited since %s. Emails, notifications and updates are delayed until a worker takes them.',
					$delayed['count'],
					$this->format_time( $delayed['oldest'] )
				),
				'Tools > Background Processing shows what runs jobs on this site and how to set it up. To clear the backlog now, use "Process queue" on the Queue Monitor page.'
			);
		}

		$overdue = $this->overdue_tasks();
		$total   = \count( $this->scheduler->get_tasks() );

		if ( empty( $overdue ) ) {
			$checks[] = $this->make_check( 'overdue_tasks', 'Scheduler', 'pass', 'Every scheduled task ran on time.' );
		} else {
			$all      = \count( $overdue ) === $total;
			$last_run = $this->last_task_run();
			$checks[] = $this->make_check(
				'overdue_tasks',
				'Scheduler',
				$all ? 'critical' : 'warning',
				$all
					? \sprintf(
						'No scheduled task is running on time%s, so the scheduler is not being called. Update checks, license expiry and clean-up tasks are not running.',
						null === $last_run ? ' (none has ever run)' : ' (the last ran ' . $this->format_time( $last_run ) . ')'
					)
					: \sprintf(
						'%d of %d scheduled tasks are more than %d minutes overdue: %s.',
						\count( $overdue ),
						$total,
						self::TASK_OVERDUE_MINUTES,
						\implode( ', ', \array_map( static fn( array $task ) : string => $task['label'], $overdue ) )
					),
				$all
					? 'Set up background processing in Tools > Background Processing. To run the due tasks now, use "Run due tasks" on the Schedules page.'
					: 'Open the Schedules page: a task that keeps failing or running too long can hold the others back. "Run due tasks" runs them now.'
			);
		}

		return $checks;
	}

	/**
	 * Whether anything runs background work, from the heartbeats, and a
	 * recommendation when it runs from the cron URL.
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	private function background_setup_checks() : array {
		$detected = $this->background->detect();
		$last     = $detected['last'];
		$page     = 'Tools > Background Processing';

		if ( null === $detected['mode'] ) {
			if ( null === $last ) {
				return [ $this->make_check(
					'background_processing',
					'Background Processing',
					'critical',
					'Background processing has never run on this site. Update checks, security updates, license expiry, emails and clean-up wait until an administrator runs them.',
					\sprintf( 'Set it up in %s. On hosting without cron jobs, an external cron service calling the cron URL every 5 minutes is enough.', $page )
				) ];
			}

			if ( $detected['worker'] ) {
				return [ $this->make_check(
					'background_processing',
					'Background Processing',
					'warning',
					'The queue worker is running, but nothing runs the scheduled tasks, so update checks, license expiry and clean-up do not happen.',
					\sprintf( 'Add the scheduler cron job shown in %s under "Server cron and queue worker".', $page )
				) ];
			}

			return [ $this->make_check(
				'background_processing',
				'Background Processing',
				'critical',
				\sprintf(
					'Background processing stopped: it last ran %s ago (%s). Scheduled tasks and queued jobs are waiting.',
					BackgroundSetup::describe_age( $last['age'] ),
					$this->runner_label( $last['source'] )
				),
				\sprintf( 'Check that the cron job or external service still runs. %s shows each runner\'s last run and the configuration for this server.', $page )
			) ];
		}

		$source = $this->runner_label( $last['source'] ?? '' );
		$checks = [ $this->make_check(
			'background_processing',
			'Background Processing',
			'pass',
			\sprintf(
				'%s runs background work; it last ran %s ago.',
				$this->background->modes()[ $detected['mode'] ]['label'] ?? $source,
				BackgroundSetup::describe_age( (int) ( $last['age'] ?? 0 ) )
			)
		) ];

		if ( BackgroundSetup::MODE_URL === $detected['mode'] ) {
			$checks[] = $this->make_check(
				'background_alternative',
				'Background Processing Reliability',
				'info',
				'Background work runs only when the external cron service calls the cron URL. If that service stops, so do updates and emails, and each call is limited like any web request.',
				\sprintf( 'If your hosting offers cron jobs, use the hosting panel cron job in %s instead; on a VPS, use cron with a queue worker.', $page )
			);
		}

		return $checks;
	}

	/**
	 * A readable name for a heartbeat source.
	 *
	 * @param string $source One of BackgroundStatus::SOURCE_*.
	 * @return string
	 */
	private function runner_label( string $source ) : string {
		foreach ( $this->background->runners() as $runner ) {
			if ( $runner['source'] === $source ) {
				return $runner['label'];
			}
		}

		return $source;
	}

	/**
	 * Update checks, from the last recorded check (the update server is not contacted).
	 *
	 * @return array<int, array{id: string, label: string, status: string, message: string, recommendation: ?string}>
	 */
	private function update_health_checks() : array {
		$overview = $this->updates->overview();
		$check    = $overview['check'];
		$checks   = [];

		// What prevents updating; an unfinished update is reported above.
		$blockers = \array_values( \array_diff_key( $this->updates->blockers(), [ 'unfinished' => true ] ) );

		$checks[] = empty( $blockers )
			? $this->make_check( 'update_readiness', 'Update Readiness', 'pass', 'Nothing prevents installing updates.' )
			: $this->make_check(
				'update_readiness',
				'Update Readiness',
				'critical',
				'Updates cannot be installed: ' . \implode( ' ', $blockers ),
				'Fix the problems listed; security releases cannot be installed until then. The Updates page shows the same list.'
			);

		$checked_at = \strtotime( (string) $check['checked_at'] );

		if ( null !== $check['error'] ) {
			$checks[] = $this->make_check(
				'update_check',
				'Update Check',
				'warning',
				'The last check for updates failed: ' . $check['error'],
				'Check outbound HTTPS access to the update server (see "Update Server Connection"), then use "Check now" on the Updates page.'
			);
		} elseif ( false === $checked_at || $checked_at < \time() - self::UPDATE_CHECK_STALE_HOURS * 3600 ) {
			$checks[] = $this->make_check(
				'update_check',
				'Update Check',
				'warning',
				false === $checked_at
					? 'This site has never checked for updates.'
					: \sprintf( 'The last check for updates was on %s; the scheduler checks every 12 hours.', $check['checked_at'] ),
				'Make sure the scheduler runs (see "Scheduler"), or use "Check now" on the Updates page.'
			);
		} elseif ( $check['available'] ) {
			$checks[] = $this->make_check(
				'update_check',
				'Update Check',
				$check['security'] ? 'critical' : 'warning',
				\sprintf(
					'Version %s is available%s; this site runs %s.',
					$check['latest'],
					$check['security'] ? ' and fixes a security issue' : '',
					\SMLISER_VER
				),
				null !== $overview['queued']
					? 'An install is queued; make sure the queue worker is running.'
					: 'Install it from the Updates page.'
			);
		} else {
			$checks[] = $this->make_check( 'update_check', 'Update Check', 'pass', \sprintf( '%s %s is the latest version (checked %s).', \SMLISER_APP_NAME, \SMLISER_VER, $check['checked_at'] ) );
		}

		$attempt = $overview['attempt'];

		if ( null !== $attempt && empty( $attempt['ok'] ) && null === $overview['queued'] ) {
			$checks[] = $this->make_check(
				'update_attempt',
				'Last Update Attempt',
				'warning',
				\sprintf( 'The last queued %s, at %s, failed.', 'rollback' === ( $attempt['action'] ?? '' ) ? 'rollback' : 'update', $attempt['at'] ?? '?' ),
				'The Updates page shows its output under History.'
			);
		}

		if ( UpdateService::AUTO_OFF === $overview['auto'] ) {
			$checks[] = $this->make_check(
				'automatic_updates',
				'Automatic Updates',
				'info',
				'Automatic updates are off, so security releases are only installed when an administrator does it.',
				'Consider installing security releases automatically (Updates page).'
			);
		}

		return $checks;
	}

	/*
	|--------------------
	| CHECK BUILDERS
	|--------------------
	*/

	/**
	 * Build one check entry.
	 *
	 * @param string      $id             Unique check ID.
	 * @param string      $label          Check name.
	 * @param string      $status         pass, info, warning or critical.
	 * @param string      $message        What was found.
	 * @param string|null $recommendation What to do about it.
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}
	 */
	private function make_check( string $id, string $label, string $status, string $message, ?string $recommendation = null ) : array {
		return \compact( 'id', 'label', 'status', 'message', 'recommendation' );
	}

	/**
	 * Check that the web server serves the public/ folder, not the folder above it.
	 *
	 * Compares the request's document root with public/:
	 *  - the same folder: pass;
	 *  - a parent of public/ (the application folder, or a folder above it):
	 *    every file in the application folder is inside the web root. The
	 *    root .htaccess shipped with the application keeps them unreachable on
	 *    Apache and LiteSpeed, so that is a warning; on other servers, or
	 *    without that file, it is critical;
	 *  - anything else (aliases, proxies, a CLI request): not reported, since
	 *    the layout cannot be told from here.
	 *
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}|null
	 */
	private function check_document_root() : ?array {
		$document_root = \realpath( (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ) );
		$public        = \realpath( \SMLISER_ROOT . 'public' );

		if ( false === $document_root || false === $public || '' === (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ) ) {
			return null;
		}

		$check = [
			'id'             => 'document_root',
			'label'          => 'Web Server Document Root',
			'status'         => 'pass',
			'message'        => \sprintf( 'The web server serves %s, so only public files can be reached.', $public ),
			'recommendation' => null,
		];

		if ( $document_root === $public ) {
			return $check;
		}

		// The document root must be public/ itself or one of its parents to be judged here.
		if ( ! \str_starts_with( $public . \DIRECTORY_SEPARATOR, \rtrim( $document_root, '/\\' ) . \DIRECTORY_SEPARATOR ) ) {
			return null;
		}

		$software  = \strtolower( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) );
		$htaccess  = \SMLISER_ROOT . '.htaccess';
		$protected = ( \str_contains( $software, 'apache' ) || \str_contains( $software, 'litespeed' ) )
			&& $this->fs->is_file( $htaccess )
			&& \str_contains( (string) $this->fs->get_contents( $htaccess ), '# BEGIN Smart License Server' );

		$check['status']  = $protected ? 'warning' : 'critical';
		$check['message'] = $protected
			? \sprintf( 'The web server serves %s instead of its public folder. The .htaccess file in that folder keeps everything outside public unreachable, but the whole application folder depends on it.', $document_root )
			: \sprintf( 'The web server serves %s instead of its public folder, and nothing stops visitors from downloading files outside public, such as .env with the database password and application secrets.', $document_root );
		$check['recommendation'] = \sprintf(
			'In your hosting control panel (or web server configuration), set the document root of this site to %s.%s',
			$public,
			$protected
				? ' Until then, do not delete or edit the Smart License Server block in ' . $htaccess . '.'
				: ' Do this now. Ready-made configurations are in ' . \SMLISER_ROOT . 'server/.'
		);

		return $check;
	}
}