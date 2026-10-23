<?php
/**
 * WordPress environment bootstrap file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environments\WordPress;

use Callismart\DBPrism\Adapters\Contracts\DatabaseAdapterInterface;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Autoloader;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Contracts\URLManagerInterface;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environment;
use SmartLicenseServer\Core\CallableResolver;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\HostedApps\Storage\AppStorage;
use SmartLicenseServer\HostedApps\Storage\DirectoryAppStorage;
use SmartLicenseServer\Monetization\DownloadToken;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Authentication\Session\UserSessionCutoff;
use SmartLicenseServer\Security\CSRF\CSRF;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;
use SmartLicenseServer\SettingsAPI\Providers\WPSettingsProvider;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateDiscovery;

/**
 * Smart License Server running as a plugin in WordPress environment.
 * 
 * This class is responsible for bootstrapping the application in a WordPress environment,
 * including setting up the filesystem, database, REST API, and other core services. 
 * It also registers action hooks and filters specific to WordPress.
 */
class WordPressEnvironment extends Environment {

	protected ScriptManager $script_manager;
	protected AdminMenu $menu;
	protected RoutesManager $routes_manager;

	protected function registerDependencies(): void {
		Autoloader::add_function_dir( __DIR__ . '/functions' );

		$this->container->singleton( WordPressEnvironment::class, $this );
		$this->container->singleton(
			Request::class,
			fn () : Request => Request::createFromGlobals()
		);
		$this->container->singleton(
			DatabaseAdapterInterface::class,
			fn () : DatabaseAdapterInterface => new WPDBAdapter( $GLOBALS['wpdb'] )
		);

		$this->container->singleton(
			SettingsStorageInterface::class,
			fn ( Container $c ) : SettingsStorageInterface => new WPSettingsProvider(
				$c->get( Database::class )
			)
		);
		
		$this->container->set(
			URLManagerInterface::class,
			fn( Container $c ) : URLManagerInterface => new WPURLManager(
				settings: $c->get( Settings::class ),
				site_url: URL::from( site_url() ),
				admin_base_url: URL::from( admin_url() ),
				assets_url: URL::from( \plugin_dir_url( \SMLISER_FILE ) )->append_path( 'assets' ),
				themes_url: URL::from( \get_stylesheet_directory_uri() ),
			)
		);

		$this->container->singleton(
			DirectoryAppStorage::class,
			fn ( Container $c ) : DirectoryAppStorage => new DirectoryAppStorage(
				$c->get( FileSystem::class ),
				SMLISER_REPO_DIR,
				DirectoryAppStorage::CORE_TYPE_DIRS
			)
		);
		
		$this->container->singleton(
			AppStorage::class,
			fn( Container $c ) : AppStorage => AppStorage::instance(
				$c->get( DirectoryAppStorage::class ),
			)
		);

		$this->container->singleton(
			SessionManager::class,
			function( Container $c ) : SessionManager {
				$is_secure   = $c->get( Request::class )->isSecure();
				$cookie_name = sprintf( '%ssmliser-sid', $is_secure ? '__Host-' : '' );
				return new SessionManager(
					secret: $this->runtime->secret,
					salt: $this->runtime->salt,
					revocation_check: $c->get( UserSessionCutoff::class ),
					secure: $is_secure,
					cookie_name: $cookie_name
				);
			}
		);

		$this->container->singleton(
			CSRF::class,
			function( Container $c ) : CSRF {
				$is_secure         = $c->get( Request::class )->isSecure();
				$guest_cookie_name = sprintf( '%ssmliser-csrf', $is_secure ? '__Host-' : '' );
				return new CSRF(
					secret: $this->runtime->secret,
					salt: $this->runtime->salt,
					sessions: $c->get( SessionManager::class ),
					secure: $is_secure,
					guest_cookie: $guest_cookie_name
				);
			}
		);

		// \dd( $this->container->get( URLManager::class )->assets_url()->url() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot() : void {
		$this->container->get( RoutesManager::class )->register();
		$this->container->get( AdminDashboardRegistry::class )->remove( 'modules' );
		
		DataStore::set_database(
			$this->container->get( Database::class )
		);

		DataStore::set_cache(
			$this->container->get( Cache::class )
		);

		DataStore::set_urlmanager(
			$this->container->get( URLManager::class )
		);

		// Models derive their keys from the application secret (BootKeyringTrait).
		DownloadToken::set_keys( $this->runtime->secret, $this->runtime->salt );
		ServiceAccount::set_keys( $this->runtime->secret, $this->runtime->salt );

		// Shadow boot hosted app registry.
		$this->container->get( HostedAppsRegistry::class );
		$this->container->get( AppStorage::class );


		$this->container->get( TemplateDiscovery::class )
			->discover( 'core', \SMLISER_RUNTIME_DIR . '/templates/', 0 );

		$this->register_action_hooks();
		$this->register_filters();

		// register_activation_hook( SMLISER_FILE, [Installer::class, 'install'] );
	}

	protected function createDatabaseConfig(): ?DBConfigDTO {
		global $wpdb;

		$parsed = $wpdb->parse_db_host( (string) $wpdb->dbhost );
		[ $host, $port, $socket ] = false === $parsed ? [ (string) $wpdb->dbhost, null, null ] : $parsed;
		return new DBConfigDTO( [
			'driver'   => 'mysql',
			'host'     => '' === $host ? 'localhost' : $host,
			'port'     => $port,
			'socket'   => $socket,
			'dbname'   => $wpdb->dbname,
			'username' => $wpdb->dbuser,
			'password' => $wpdb->dbpassword,
			'charset'  => $wpdb->charset ?: null,
		] );
	}

	/**
	 * Register action hooks
	 */
	protected function register_action_hooks() : void {
		$actions = [
			[
				'hook'     => 'set_current_user',
				'callback' => [IdentityService::class, 'authenticate'],
			],
			[
				'hook'     => 'deleted_user',
				'callback' => [IdentityService::class, 'desync_user'],
			],
			[
				'hook'     => 'admin_menu',
				'callback' => [AdminMenu::class, 'register_menus'],
			],
			[
				'hook'     => 'admin_menu',
				'callback' => [AdminMenu::class, 'submenu_index_name'],
				'priority' => 999,
			],
			[
				'hook'     => 'admin_notices',
				'callback' => [ $this, 'check_filesystem_errors'],
			],
			[
				'hook'     => 'admin_notices',
				'callback' => [$this, 'print_admin_notices'],
			],
			[
				'hook'     => 'init',
				'callback' => [RoutesManager::class, 'route_register'],
			],
			[
				'hook'     => 'init',
				'callback' => [$this, 'schedule_events'],
				'priority' => 10,
			],
			[
				'hook'     => 'init',
				'callback' => [ScriptManager::class, 'register_scripts'],
				'priority' => 10,
			],
			[
				'hook'     => 'init',
				'callback' => [ScriptManager::class, 'register_styles'],
				'priority' => 10,
			],
			[
				'hook'     => 'smliser_auth_page_header',
				'callback' => '\SmartLicenseServer\Environments\WordPress\load_auth_header',
			],
			[
				'hook'     => 'smliser_auth_page_footer',
				'callback' => '\SmartLicenseServer\Environments\WordPress\load_auth_footer',
			],
			[
				'hook'     => 'admin_init',
				'callback' => [Dispatcher::class, 'init_request'],
			],
			[
				'hook'     => 'template_redirect',
				'callback' => [Dispatcher::class, 'init_request'],
			],
			[
				'hook'     => 'wp_enqueue_scripts',
				'callback' => [ScriptManager::class, 'enqueue_styles'],
			],
			[
				'hook'     => 'wp_enqueue_scripts',
				'callback' => [ScriptManager::class, 'enqueue_scripts'],
			],
			[
				'hook'     => 'admin_enqueue_scripts',
				'callback' => [ScriptManager::class, 'enqueue_styles'],
			],
			[
				'hook'     => 'admin_head',
				'callback' => '\SmartLicenseServer\Environments\WordPress\set_early_theme_attribute',
				'priority' => 1, // Runs at the very beginning of <head>
			],
			[
				'hook'     => 'admin_enqueue_scripts',
				'callback' => [ScriptManager::class, 'enqueue_scripts'],
			],
			// [
			// 	'hook'     => 'smliser_process_queue',
			// 	'callback' => [QueueWorker::class, 'process_within_time_budget'],
			// ],
			// [
			// 	'hook'     => 'smliser_run_scheduler',
			// 	'callback' => [Scheduler::class, 'run_due_tasks'],
			// ],
			[
				'hook'     => 'cli_init',
				'callback' => [$this, 'setup_cli'],
			],
		];

		$resolver	= $this->container->get( CallableResolver::class );
		foreach ( $actions as $action ) {
			add_action(
				$action['hook'],
				$resolver->resolve( $action['callback'] ),
				$action['priority'] ?? 10,
				$action['accepted_args'] ?? 1
			);
		}

	}

	/**
	 * Register filters
	 */
	protected function register_filters() : void {
		$filters = [
			[
				'hook'          => 'redirect_canonical',
				'callback'      => [$this, 'disable_redirect_on_downloads'],
				'priority'      => 10,
				'accepted_args' => 2,
			],
			[
				'hook'     => 'template_include',
				'callback' => __NAMESPACE__ . '\\load_auth_template',
			],
			[
				'hook'     => 'cron_schedules',
				'callback' => [$this, 'register_cron'],
			],
			[
				'hook'     => 'query_vars',
				'callback' => [RoutesManager::class, 'query_vars'],
			],
		];

		$resolver	= $this->container->get( CallableResolver::class );
		foreach ( $filters as $filter ) {
			add_filter(
				$filter['hook'],
				$resolver->resolve( $filter['callback'] ),
				$filter['priority'] ?? 10,
				$filter['accepted_args'] ?? 1
			);
		}
	}

	/**
	 * Get the routes manager instance.
	 * 
	 * @return RoutesManager
	 */
	public function get_routes_manager() : RoutesManager {
		return $this->routes_manager;
	}

	/**
	 * {@inheritdoc}
	 * 
	 * Check key filesystem directories for read/write access and print admin notice if not writable.
	 *
	 * Uses a transient to avoid repeated expensive filesystem checks.
	 *
	 * @return void
	 */
	public function check_filesystem_errors() : void {
		$transient_key  = 'smliser_fs_check_results';
		$cached_results = [];// get_transient( $transient_key );

		// if ( false === $cached_results ) {
		// 	$dirs_to_check = [
		// 		\SMLISER_REPO_DIR,
		// 		\SMLISER_UPLOADS_DIR,
		// 		\SMLISER_WP_PLUGINS_REPO_DIR,
		// 		\SMLISER_WP_THEMES_REPO_DIR,
		// 		\SMLISER_SOFTWARE_REPO_DIR,
		// 	];

		// 	$cached_results = $this->->test_dirs_read_write( $dirs_to_check );
		// 	set_transient( $transient_key, $cached_results, HOUR_IN_SECONDS );
		// }

		$errors = [];

		foreach ( $cached_results as $dir => $status ) {
			$issues = [];

			if ( ! $status['readable'] ) {
				$issues[] = __( 'not readable', 'smliser' );
			}
			if ( ! $status['writable'] ) {
				$issues[] = __( 'not writable', 'smliser' );
			}

			if ( ! empty( $issues ) ) {
				$errors[ $dir ] = $issues;
			}
		}

		if ( empty( $errors ) ) {
			return;
		}

		$rows = '';
		foreach ( $errors as $dir => $issues ) {
			$badge_html = '';
			foreach ( $issues as $issue ) {
				$badge_html .= sprintf(
					'<span style="display:inline-block;margin-left:6px;padding:1px 7px;border-radius:3px;background:#b32d2e;color:#fff;font-size:11px;font-weight:600;vertical-align:middle;">%s</span>',
					esc_html( $issue )
				);
			}

			$rows .= sprintf(
				'<tr><td style="padding:4px 0;font-family:monospace;font-size:13px;color:#3c434a;">%s</td><td style="padding:4px 0 4px 12px;white-space:nowrap;">%s</td></tr>',
				esc_html( $dir ),
				$badge_html
			);
		}

		$dir_count = count( $errors );
		$heading   = sprintf(
			/* translators: 1: Plugin name, 2: Number of affected directories */
			_n(
				'%1$s detected a filesystem permission problem in %2$d directory.',
				'%1$s detected filesystem permission problems in %2$d directories.',
				$dir_count,
				'smliser'
			),
			'<strong>' . SMLISER_APP_NAME . '</strong>',
			$dir_count
		);

		$message = sprintf(
			'<p>%s</p>
            <table style="border-collapse:collapse;margin:4px 0 8px;">%s</table>
            <p style="margin:0;">%s</p>',
			$heading,
			$rows,
			sprintf(
				/* translators: %s: chmod command example */
				__( 'Please check directory ownership and permissions. You may need to run %s or contact your hosting provider.', 'smliser' ),
				'<code>chmod -R 755</code>'
			)
		);

		wp_admin_notice( $message, [ 'type' => 'error', 'dismissible' => true ] );
	}

	/**
	 * Print admin notices
	 */
	public function print_admin_notices() {
		// $repo_version = \smliser_settings()->get( 'smliser_repo_version', 0 );
		// if ( SMLISER_VER === $repo_version ) {
		// 	return;
		// }
		?>
        <div class="notice notice-info">
            <p>
                <?php printf(
                	/* translators: %s: plugin name */
                	escHtml( '%s requires an update.' ) . ' <a id="smliser-update-btn" style="cursor:pointer;">' . escHtml( 'Click here to update now.' ) . '</a>',
                	esc_html( SMLISER_APP_NAME )
                );?>              
                
            </p>
            <p id="smliser-click-notice" style="display: none">Update started in the backgroud <span class="ti ti-circle-check" style="color: blue"></span></p>
        </div>
        <?php
	}

	/**
	 * Disable unintended http 301 redirect code during file downloads, this ensures
	 * we handle the responses to the download url the proper way instead of the default 301 returned initially
	 * by WordPress.
	 * 
	 * @param string $redirect_url The redirected url.
	 * @param string $requested_url The client requested url.
	 * @return false|string False when accessing downloads page, perform redirect when not.
	 * @since 0.2.0
	 */
	public function disable_redirect_on_downloads( $redirect_url, $requested_url ) {
		// $download_slug = (string) $this->url( smliser_get_download_url_prefix() );
		// if ( false !== strpos( $requested_url, $download_slug ) ) {
		// 	return false;
		// }

		return $redirect_url;
	}

	/**
	 * Register Custom WordPress Cron Intervals.
	 * 
	 * @param array $schedules
	 */
	public function register_cron( $schedules ) : array {
		// Safety checks.
		if ( ! is_array( $schedules ) ) {
			$schedules = (array) $schedules;
		}

		$schedules['smliser_every_minute'] = [
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'Every Minute',
		];

		$schedules['smliser_three_minutely'] = [
			'interval' => 3 * MINUTE_IN_SECONDS,
			'display'  => 'Three Minutely',
		];

		$schedules['smliser_4_hourly'] = [
			'interval' => 4 * HOUR_IN_SECONDS,
			'display'  => 'Four Hourly',
		];

		return $schedules;
	}

	/**
	 * Schedules events.
	 */
	public function schedule_events() {
		if ( ! wp_next_scheduled( 'smliser_process_queue' ) ) {
			wp_schedule_event( time(), 'smliser_every_minute', 'smliser_process_queue' );
		}
		
		if ( ! wp_next_scheduled( 'smliser_run_scheduler' ) ) {
			wp_schedule_event( time(), 'smliser_every_minute', 'smliser_run_scheduler' );
		}
	}

	/**
	 * Bootstraps in WP_CLI environment.
	 *
	 * This method runs on `cli_init` action.
	 */
	public function setup_cli() : void {
		// ( new WPCLIRunner(
		//     CommandRegistry::instance(),
		//     new WPCLIInput(),
		//     new WPCLIOutput(),
		//     new Terminal(),
		//     new OptionParser()
		// ) )->init();
	}
}
