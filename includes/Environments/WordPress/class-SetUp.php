<?php
/**
 * WordPress environment bootstrap file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environment
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Admin\AdminConfiguration;
use SmartLicenseServer\Cache\Adapters\WPCacheAdapter;
use SmartLicenseServer\Environment;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\Runners\WPCLIRunner;
use SmartLicenseServer\Core\DBConfigDTO;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Database\Adapters\WPDBAdapter;
use SmartLicenseServer\FileSystem\Adapters\WPFileSystemAdapter;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\RESTAPI\Versions\V1;
use SmartLicenseServer\SettingsAPI\Providers\WPSettingsProvider;

/**
 * WordPress Environment setup class
 */
class SetUp extends Environment {
    /**
     * The singleton instance of the environment.
     * 
     * @var self $instance
     */
    private static ?self $instance = null;

    protected IdentityService $auth;
    protected ScriptManager $script_manager;
    protected AdminMenu $menu;

    /**
     * Class constructor
     */
    private function __construct() {
        $this->setProps();

        add_action( 'set_current_user', [$this->auth, 'authenticate'] );
        add_action( 'admin_menu', [$this->menu, 'register_menus'] );
        add_action( 'admin_menu', [$this->menu, 'submenu_index_name'], 999 );

        add_action( 'admin_notices', [ $this, 'check_filesystem_errors'] );
        add_action( 'admin_notices', [$this, 'print_admin_notices'] );
        
        add_action( 'init', [$this, 'route_register'], 9 );
        add_action( 'init', [$this, 'schedule_events'], 10 );
        add_action( 'init', [$this->script_manager, 'register_scripts'], 10 );
        add_action( 'init', [$this->script_manager, 'register_styles'], 10 );
        
        add_action( 'smliser_auth_page_header', 'smliser_load_auth_header' );
        add_action( 'smliser_auth_page_footer', 'smliser_load_auth_footer' );
        
        add_action( 'admin_init', [Router::class, 'init_request'] );
        add_action( 'template_redirect', [Router::class, 'init_request'] );
        
        add_action( 'wp_enqueue_scripts', [$this->script_manager, 'enqueue_styles'] );
        add_action( 'wp_enqueue_scripts', [$this->script_manager, 'enqueue_scripts'] );
        add_action( 'admin_enqueue_scripts', [$this->script_manager, 'enqueue_styles'] );
        add_action( 'admin_enqueue_scripts', [$this->script_manager, 'enqueue_scripts'] );

        add_filter( 'redirect_canonical', [$this, 'disable_redirect_on_downloads'], 10, 2 );
        add_filter( 'template_include', [Router::class, 'load_auth_template'] );
        add_filter( 'query_vars', [$this, 'query_vars'] );
        add_filter( 'cron_schedules', [$this, 'register_cron'] );

        add_action( 'smliser_process_queue', [$this->queue_worker, 'process_within_time_budget'] );
        add_action( 'smliser_run_scheduler', [$this->scheduler(), 'run_due_tasks'] );

        register_activation_hook( SMLISER_FILE, [Installer::class, 'install'] );

        add_action( 'cli_init', [$this, 'setup_cli'] );
    }

    /**
     * Bootstrap the class properties.
     */
    private function setProps() : void {
        static::$envProvider    = $this;
        /** @var \wpdb $wpdb */
        $wpdb               = $GLOBALS['wpdb'] ?? null;
        $repo_path          = WP_CONTENT_DIR;
        $absolute_path      = ABSPATH;
        $uploads_dir        = wp_upload_dir()['basedir'];
        $db_prefix          = $wpdb?->prefix;
        $filesystem_adapter = new WPFileSystemAdapter;
        $cache_adapter      = wp_using_ext_object_cache() ? new WPCacheAdapter : null;
        $settings_provider  = new WPSettingsProvider;
        $database_adapter   = new WPDBAdapter( $wpdb );
        $rest_api_provider  = new RESTAPI( new V1 );
        $secret             = SECURE_AUTH_KEY;
        $salt               = SECURE_AUTH_SALT;
        
        $env    = compact( 'absolute_path', 'db_prefix', 'repo_path', 'uploads_dir',
        'filesystem_adapter', 'cache_adapter', 'settings_provider', 'database_adapter',
        'rest_api_provider', 'salt', 'secret'
        
        );

        $this->dbConfig = new DBConfigDTO([
            'driver'    => 'mysql',
            'host'      => DB_HOST,
            'port'      => 3306,
            'database'  => DB_NAME,
            'username'  => DB_USER,
            'password'  => DB_PASSWORD,
            'charset'   => DB_CHARSET,
            // 'path'      => ABSPATH . 'sqlite.db'
        ]);
        
        $this->setup( $env );
        
        $this->auth             = new IdentityService;
        $this->script_manager   = new ScriptManager;
        $this->menu             = new AdminMenu( new AdminConfiguration, $this->request );
    }

    /**
     * Get instance
     * 
     * @return static
     */
    public static function instance() : static {
        if ( is_null( static::$instance ) ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public static function url( string $path = '', array $qv = [] ) : URL {
        return ( new URL( site_url() ) )
        ->append_path( $path )
        ->add_query_params( $qv );
    }
    
    public static function adminUrl( string $path = '', array $qv = [] ) : URL {
        return ( new URL( admin_url() ) )
        ->append_path( $path )
        ->add_query_params( $qv );
    }

    public static function restAPIUrl( string $path = '', array $qv = [] ) : URL {
        return ( new URL( rest_url() ) )
        ->append_path( $path )
        ->add_query_params( $qv );
    }
    
    public static function assets_url( string $path = '' ) : URL {
        $path   = FileSystemHelper::join_path( '/assets/', $path );
        return ( new URL( SMLISER_URL ) )
            ->append_path( $path );
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
    public function check_filesystem_errors(): void {
        $transient_key  = 'smliser_fs_check_results';
        $cached_results = get_transient( $transient_key );

        if ( false === $cached_results ) {
            $dirs_to_check = [
                \SMLISER_REPO_DIR,
                \SMLISER_UPLOADS_DIR,
                \SMLISER_PLUGINS_REPO_DIR,
                \SMLISER_THEMES_REPO_DIR,
                \SMLISER_SOFTWARE_REPO_DIR,
            ];

            $cached_results = $this->filesystem->test_dirs_read_write( $dirs_to_check );
            set_transient( $transient_key, $cached_results, HOUR_IN_SECONDS );
        }

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
        $repo_version = \smliser_settings_adapter()->get( 'smliser_repo_version', 0 );
        if ( SMLISER_VER === $repo_version ) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p>
                <?php printf(
                    /* translators: %s: plugin name */
                    esc_html__( '%s requires an update.', 'smliser' ) . ' <a id="smliser-update-btn" style="cursor:pointer;">' . esc_html__( 'Click here to update now.', 'smliser' ) . '</a>',
                    esc_html( SMLISER_APP_NAME )
                );?>              
                
            </p>
            <p id="smliser-click-notice" style="display: none">Update started in the backgroud <span class="dashicons dashicons-yes-alt" style="color: blue"></span></p>
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
        $download_slug = $this->url( smliser_get_download_url_prefix() );
        if ( strpos( $requested_url, $download_slug ) !== false ) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Sets up custom routes.
     */
    public function route_register() :void {
        $repo_prefix = smliser_get_repository_url_prefix();
    
        add_rewrite_rule(
            '^' . $repo_prefix . '$',
            'index.php?pagename=smliser-repository',
            'top'
        );
    
        /**
         * Repository app type page matches siteurl/repository/{app_type}/ where app type can be (themes, plugins, software)
         */
        add_rewrite_rule(
            '^' . $repo_prefix . '/([^/]+)$',
            'index.php?pagename=smliser-repository&smliser_app_type=$matches[1]',
            'top'
        );

        /**
         * Repository app type page matches siteurl/repository/{app_type}/{app_slug}/
         */
        add_rewrite_rule(
            '^' . $repo_prefix . '/([^/]+)/([^/]+)/?$',
            'index.php?pagename=smliser-repository&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]',
            'top'
        );

        /**
         * Asset serving url matchs siteurl/repository/{app_type}/{app_slug}/assets/{filename}
         */
        add_rewrite_rule(
            '^' . $repo_prefix . '/([^/]+)/([^/]+)/assets/(.+)$',
            'index.php?pagename=smliser-repository-assets&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]&smliser_asset_name=$matches[3]',
            'top'
        );

        /**
         * Smliser uploads dir serving url matchs siteurl/smliser-uploads/{path_to_file}
         */
        add_rewrite_rule(
            '^smliser-uploads/(.+)$',
            'index.php?pagename=smliser-uploads&smliser_upload_path=$matches[1]',
            'top'
        );

        /*
        |------------------------
        | Software download rules
        |------------------------
        */

        $download_slug = smliser_get_download_url_prefix();

        /**
         * The base downloads page 
         */
        add_rewrite_rule(
            '^' . $download_slug . '/?$',
            'index.php?pagename=smliser-downloads',
            'top'
        );

        /**
         * Downloads category page
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]',
            'top'
        );
        
        /** 
         * License document download rule (specific)
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/([0-9]+)/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]&license_id=$matches[2]',
            'top'
        );

        /** 
         * File Download URI Rule
         */
        add_rewrite_rule(
            '^' . $download_slug . '/([^/]+)/((?![0-9]+$)[^/]+)(?:\.zip)?/?$',
            'index.php?pagename=smliser-downloads&smliser_app_type=$matches[1]&smliser_app_slug=$matches[2]',
            'top'
        );

        /**OAUTH authorization endpoint */
        add_rewrite_rule(
            '^smliser-auth/v1/authorize$',
            'index.php?smliser_auth=$matches[1]',
            'top'
        );
    }

    /**
     * Plugin Query Variables
     *
     * Adds custom query variables to WordPress recognized query variables.
     *
     * @param array $vars The existing array of query variables.
     * @return array Modified array of query variables.
     */
    public function query_vars( $vars ) {
        
        $vars[] = 'smliser_repository';
        $vars[] = 'smliser_repository_plugin_slug';
        $vars[] = 'license_id';
        $vars[] = 'smliser_app_type';
        $vars[] = 'smliser_auth';
        
        $vars[] = 'smliser_app_slug';
        $vars[] = 'smliser_asset_name';
        $vars[] = 'smliser_upload_path';
        
        return $vars;
    }

    /**
     * Register Custom WordPress Cron Intervals.
     * 
     * @param array $schedules
     */
    public function register_cron( $schedules ) : array {
        // Safety checks.
        if ( ! is_array( $schedules ) ) {
            $schedules  = (array) $schedules;
        }

        $schedules['smliser_every_minute'] = array(
            'interval' => MINUTE_IN_SECONDS,
            'display'  => 'Every Minute',
        );

        $schedules['smliser_three_minutely'] = array(
            'interval' => 3 * MINUTE_IN_SECONDS,
            'display'  => 'Three Minutely',
        );

        $schedules['smliser_4_hourly'] = array(
            'interval' => 4 * HOUR_IN_SECONDS,
            'display'  => 'Four Hourly',
        );

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
     */
    public function setup_cli() {        
        ( new WPCLIRunner( CommandRegistry::instance() ) )
        ->register();
    }
}