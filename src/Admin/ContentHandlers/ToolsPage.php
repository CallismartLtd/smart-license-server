<?php
/**
 * The admin system page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateLocator;
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Utils\Format;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\DatabaseInfoDTO;
use Callismart\DBPrism\Inspection\Inspector;
use SmartLicenseServer\Assets\AssetsManager;

/**
 * The admin system operations page handler.
 */
class ToolsPage implements AdminPageInterface {

	/**
	 * Directory sizes already computed during this request, keyed by
	 * normalized path.
	 *
	 * Several diagnostics report the size of the same path (e.g.
	 * SMLISER_ROOT is both the "Application Root" required directory
	 * and the base of the total installation size), so each path is
	 * walked at most once per request.
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
	 * Database info returned by the inspector, once fetched.
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

	public function __construct(
		protected Scheduler $scheduler,
		protected JobQueue $job_queue,
		protected TemplateLocator $locator,
		protected URLManager $urlmanager,
		protected Settings $settings,
		protected AppInstaller $installer,
		protected Database $db,
		protected FileSystem $fs,
		protected SchemaRegistry $schema,
		protected AssetsManager $assets_manager
	) {
		$this->register_assets();
	}

	protected function register_assets() : void {
		$this->assets_manager->set_script_category(
			'site-health',
			AssetsManager::CATEGORY_ADMIN_DASHBOARD
		);
	}

	/*
	|--------------------
	| PAGE HANDLERS
	|--------------------
	*/

	/**
	 * Render the scheduled tasks page.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function schedules_page( Request $request ) : void {
		$tasks          = $this->scheduler->get_tasks_with_state();
		$page_handler   = $this;

		$vars   = \compact( 'tasks', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.index', $vars );        
	}

	/**
	 * Render the queue monitor page (active jobs and failed jobs archive).
	 *
	 * @param Request $request
	 * @return void
	 */
	public function queues_page( Request $request ) : void {
		$section    = $request->query( 'section' );
		$page       = (int) $request->get( 'page', 1 );
		$limit      = (int) $request->get( 'limit', 25 );
		$queue      = $request->get( 'queue', null );
		$status     = $request->get( 'status', null );
		$jobs       = 'failed' === $section
			? $this->job_queue->get_failed_jobs( $page, $limit, $queue )
			: $this->job_queue->get_jobs( $page, $limit, $queue, $status );
		$log_rentention = (int) $this->settings->get( Settings::LOG_RETENTION_DAYS, 30 );
		$queue_stats    = [];

		foreach ( [ 'pending', 'running', 'failed', 'completed' ] as $st ) {
			$queue_stats[$st] = $this->job_queue->count_jobs_by_status( $st );
		}

		$page_handler   = $this;
		$urlmanager     = $this->urlmanager;
		$vars           = \compact(
			'jobs', 'page_handler', 'request', 'urlmanager', 'queue_stats',
			'log_rentention'
		);
		$this->locator->render( 'admin.contents.system.queues', $vars ); 
	}

	/**
	 * Render the system diagnostics page.
	 *
	 * Read-only reference data about the running environment, grouped
	 * by category — PHP, database, directories, background processing.
	 * Nothing here is a pass/fail judgment; that's what site_health_page()
	 * is for.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function system_diagnostics_page( Request $request ) : void {
		$diagnostics    = $this->collect_diagnostics();
		$page_handler   = $this;

		$vars = \compact( 'diagnostics', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.diagnostics', $vars );
	}

	/**
	 * Render the site health page.
	 *
	 * A checklist of pass/warning/critical checks against the running
	 * environment, each with a plain-English explanation and — for
	 * anything not passing — a concrete recommendation.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function site_health_page( Request $request ) : void {
		$checks            = $this->run_health_checks();
		$page_handler      = $this;
		$vars = \compact( 'checks', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.system.health', $vars );
	}

	/*
	|--------------------
	| MENU REGISTRATION
	|--------------------
	*/

	public function get_menu_key() : string {
		return 'tools';
	}

	public function get_menu_data(): array {
		return [
			'title'         => 'Tools',
			'icon'          => 'ti ti-tool',
			'handler'       => $this,
			'slug'          => 'tools',
			'visibility'    => true
		];
	}

	public function get_submenu(): array {
		return [
			[
				'title'         => 'Schedules',
				'slug'          => 'schedules',
				'callback'      => [$this, 'schedules_page'],
				'visibility'    => true
			],
			[
				'title'         => 'Queue Monitor',
				'slug'          => 'queues',
				'callback'      => [$this, 'queues_page'],
				'visibility'    => true
			],
			[
				'title'         => 'System Diagnostics',
				'slug'          => 'diagnostics',
				'callback'      => [$this, 'system_diagnostics_page'],
				'visibility'    => true
			],
			[
				'title'         => 'Site Health',
				'slug'          => 'health',
				'callback'      => [$this, 'site_health_page'],
				'visibility'    => true
			],
		];
	}

	public function index_page_handler(): callable {
		return [$this, 'schedules_page'];
	}

	/**
	 * Get menu args.
	 *
	 * @return array<string, mixed>
	 */
	public function get_top_menu_args( Request $request ): array {
		$tab    = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
		$title  = match( $tab ) {
			'queues'        => 'Queue Monitor',
			'diagnostics'   => 'System Diagnostics',
			'health'        => 'Site Health',
			default         => ''
		};

		return [
			'breadcrumbs' => [
				[
					'label' => 'Schedules',
					'url'   => $this->urlmanager->admin_tools_page_url(),
					'icon'  => 'ti ti-home',
				],
				[
					'label' => $title,
				],
			],
			'actions' => [],
		];
	}

	/*
	|--------------------
	| CACHED LOOKUPS
	|--------------------
	*/

	/**
	 * Get the recursive size of a directory in bytes, walking each
	 * path at most once per request.
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
	 * per request. A failed lookup is remembered and rethrown on later
	 * calls rather than retried.
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
	 * Gather read-only diagnostic facts about the environment, grouped
	 * by category for display.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function collect_diagnostics() : array {
		$queue_stats = [];
		foreach ( [ 'pending', 'running', 'failed', 'completed' ] as $status ) {
			$queue_stats[ $status ] = $this->job_queue->count_jobs_by_status( $status );
		}

		$tasks = $this->scheduler->get_tasks_with_state();

		return [
			'Server'                    => $this->collect_server_diagnostics(),
			'Installation'              => $this->collect_installation_diagnostics(),
			'Required Extensions'       => $this->collect_required_extension_diagnostics(),
			'Loaded Extensions'         => $this->collect_loaded_extension_diagnostics(),
			'Database'                  => $this->collect_database_diagnostics(),
			'Directories'               => $this->collect_directory_diagnostics(),
			'Background Processing'     => [
				'Pending Jobs'      => (string) $queue_stats['pending'],
				'Running Jobs'      => (string) $queue_stats['running'],
				'Failed Jobs'       => (string) $queue_stats['failed'],
				'Completed Jobs'    => (string) $queue_stats['completed'],
				'Scheduled Tasks'   => (string) \count( $tasks ),
				'Log Retention'     => (string) $this->settings->get( Settings::LOG_RETENTION_DAYS, 30 ) . ' days',
			],
		];
	}

	/**
	 * Report the state of what AppInstaller sets up beyond directories
	 * and extensions: config files, registered tables, and default roles.
	 *
	 * @return array<string, string>
	 */
	private function collect_installation_diagnostics() : array {
		$rows = [
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
			$version = \phpversion( $extension );
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

		$rows = \array_merge( $rows, $this->collect_opcache_diagnostics() );
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

		$memory  = $status['memory_usage'] ?? [];
		$used    = (int) ( $memory['used_memory'] ?? 0 );
		$free    = (int) ( $memory['free_memory'] ?? 0 );
		$wasted  = (int) ( $memory['wasted_memory'] ?? 0 );
		$total   = $used + $free + $wasted;

		$interned       = $status['interned_strings_usage'] ?? [];
		$interned_used  = (int) ( $interned['used_memory'] ?? 0 );
		$interned_total = $interned_used + (int) ( $interned['free_memory'] ?? 0 );

		$stats    = $status['opcache_statistics'] ?? [];
		$hit_rate = isset( $stats['opcache_hit_rate'] ) ? \round( (float) $stats['opcache_hit_rate'], 2 ) : null;

		return [
			'OPcache'                  => 'Enabled',
			'OPcache Memory Usage'     => $total > 0
				? \sprintf( '%s / %s (%s used)', Format::bytes( $used ), Format::bytes( $total ), Format::percent( $used / $total ) )
				: Format::bytes( $used ),
			'OPcache Interned Strings' => $interned_total > 0
				? \sprintf( '%s / %s (%s used)', Format::bytes( $interned_used ), Format::bytes( $interned_total ), Format::percent( $interned_used / $interned_total ) )
				: Format::bytes( $interned_used ),
			'OPcache Hit Rate'         => null !== $hit_rate ? $hit_rate . '%' : 'Unknown',
			'OPcache Cache Full'       => Format::yes_no( ! empty( $status['cache_full'] ) ),
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
            'Engine'            => $info->engine,
            'Product'           => $info->product,
            'Version'           => $info->version,
            'Driver'            => $this->db->get_adapter()::class,

            // Database & Schema.
            'Database'          => $info->database,
            'Schema'            => $info->schema,
            'Size'              => null !== $info->size_bytes ? Format::bytes( $info->size_bytes ) : 'Unknown',
            'Charset'           => $info->charset,
            'Collation'         => $info->collation,
            'Timezone'          => $info->timezone,

            // Connection & Network
            'Server'            => $info->server,
            'Port'              => null !== $info->port ? (string) $info->port : null,
            'Transport'         => $info->transport,
            'Protocol Version'  => null !== $info->protocol_version ? (string) $info->protocol_version : null,
            'SSL'               => null !== $info->ssl ? Format::yes_no( $info->ssl ) : null,

            // Host Server Environment
            'Server Hostname'   => $info->server_hostname,
            'Server OS'         => $info->server_os,
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

		// Database connectivity/version, via the Inspector.
		try {
			$info = $this->get_database_info();
			$checks[] = [
				'id'             => 'database_connection',
				'label'          => 'Database Connection',
				'status'         => 'pass',
				'message'        => \sprintf(
					'Connected to %s%s.',
					$info->product ?? $info->engine,
					$info->version ? " {$info->version}" : ''
				),
				'recommendation' => null,
			];
		} catch ( \Throwable $e ) {
			$checks[] = [
				'id'             => 'database_connection',
				'label'          => 'Database Connection',
				'status'         => 'critical',
				'message'        => 'Unable to inspect the active database connection.',
				'recommendation' => $e->getMessage(),
			];
		}

		// Failed/stuck jobs.
		$failed_count = $this->job_queue->count_jobs_by_status( 'failed' );
		$checks[]     = [
			'id'             => 'failed_jobs',
			'label'          => 'Failed Jobs',
			'status'         => $failed_count === 0 ? 'pass' : ( $failed_count < 50 ? 'warning' : 'critical' ),
			'message'        => $failed_count === 0
				? 'No failed jobs in the queue.'
				: \sprintf( '%d failed job(s) in the archive.', $failed_count ),
			'recommendation' => $failed_count === 0
				? null
				: 'Review the Queue Monitor → Failed tab to diagnose recurring failures.',
		];

		$running_count = $this->job_queue->count_jobs_by_status( 'running' );
		$checks[]      = [
			'id'             => 'stuck_jobs',
			'label'          => 'Jobs Stuck Running',
			'status'         => $running_count === 0 ? 'pass' : 'warning',
			'message'        => $running_count === 0
				? 'No jobs currently running.'
				: \sprintf( '%d job(s) currently in a running state.', $running_count ),
			'recommendation' => $running_count === 0
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
			'id'             => 'scheduled_task_errors',
			'label'          => 'Scheduled Task Errors',
			'status'         => $tasks_with_errors === 0 ? 'pass' : 'critical',
			'message'        => $tasks_with_errors === 0
				? 'All scheduled tasks last ran without error.'
				: \sprintf( '%d scheduled task(s) recorded an error on their last run.', $tasks_with_errors ),
			'recommendation' => $tasks_with_errors === 0
				? null
				: 'Open the Schedules tab and review the error detail for each affected task.',
		];

		return $checks;
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

		$check['status']         = $protected ? 'warning' : 'critical';
		$check['message']        = $protected
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