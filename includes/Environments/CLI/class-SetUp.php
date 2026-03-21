<?php
/**
 * CLI environment bootstrap class file.
 *
 * Provides the Smart License Server environment adapter for command-line
 * execution. Follows the same pattern as the WordPress SetUp class —
 * extends Config, sets static::$envProvider, builds the $env array
 * from environment variables, and calls $this->setup().
 *
 * No hook system, no admin UI, no REST registration. The CLI caller
 * (cli.php) drives execution directly after construction.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\CLI
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\CLI;

use SmartLicenseServer\Config;
use SmartLicenseServer\Core\DBConfigDTO;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Database\Schema\DBTables;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\Monetization\ProviderCollection;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * CLI environment adapter.
 *
 * Bootstraps Smart License Server for command-line usage.
 * Reads all configuration from environment variables so the
 * adapter has zero WordPress dependency.
 */
class SetUp extends Config {

    /*
    |----------------------
    | SINGLETON
    |----------------------
    */

    /**
     * Singleton instance.
     *
     * @var static|null
     */
    private static ?self $instance = null;

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * Private constructor — use instance().
     */
    private function __construct() {
        $this->setProps();
    }

    /**
     * Get or create the singleton instance.
     *
     * @return static
     */
    public static function instance(): static {
        if ( static::$instance === null ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /*
    |----------------------
    | BOOTSTRAP
    |----------------------
    */

    /**
     * Read environment variables, build the $env array, and call setup().
     *
     * Mirrors SetUp::setProps() in the WordPress adapter — sets
     * static::$envProvider, populates $this->dbConfig, and delegates
     * the rest to Config::setup().
     *
     * @throws EnvironmentBootstrapException On missing required env variables.
     */
    private function setProps(): void {
        static::$envProvider = $this;

        $db_host    = $_ENV['SMLISER_DB_HOST']     ?? '127.0.0.1';
        $db_port    = (int) ( $_ENV['SMLISER_DB_PORT'] ?? 3306 );
        $db_name    = $_ENV['SMLISER_DB_NAME']     ?? '';
        $db_user    = $_ENV['SMLISER_DB_USER']     ?? '';
        $db_pass    = $_ENV['SMLISER_DB_PASSWORD'] ?? '';
        $db_charset = $_ENV['SMLISER_DB_CHARSET']  ?? 'utf8mb4';
        $db_prefix  = $_ENV['SMLISER_DB_PREFIX']   ?? '';

        $app_url     = rtrim( $_ENV['SMLISER_APP_URL']    ?? '', '/' );
        $repo_path   = rtrim( $_ENV['SMLISER_REPO_PATH']  ?? dirname( SMLISER_PATH ), '/' );
        $uploads_dir = rtrim( $_ENV['SMLISER_UPLOADS_DIR'] ?? $repo_path . '/uploads', '/' );

        if ( empty( $db_name ) || empty( $db_user ) ) {
            throw new EnvironmentBootstrapException(
                'missing_db_config',
                'CLI environment requires SMLISER_DB_NAME and SMLISER_DB_USER to be set.'
            );
        }

        if ( empty( $app_url ) ) {
            throw new EnvironmentBootstrapException(
                'missing_app_url',
                'CLI environment requires SMLISER_APP_URL to be set.'
            );
        }

        $this->dbConfig = new DBConfigDTO( [
            'driver'   => 'mysql',
            'host'     => $db_host,
            'port'     => $db_port,
            'database' => $db_name,
            'username' => $db_user,
            'password' => $db_pass,
            'charset'  => $db_charset,
        ] );

        // Store app_url for the URL methods.
        $this->app_url = $app_url;

        $env = [
            'db_prefix'        => $db_prefix,
            'absolute_path'    => SMLISER_PATH,
            'repo_path'        => $repo_path,
            'uploads_dir'      => $uploads_dir,
            'rest_api_provider' => new CLIRESTProvider(),
        ];

        $this->setup( $env );
    }

    /*
    |--------------------------------------------
    | EnvironmentProviderInterface implementation
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     *
     * No-op in CLI — monetization providers are loaded on demand
     * by the job handlers that need them, not at bootstrap time.
     */
    public function load_monetization_providers(): void {
        ProviderCollection::auto_load();
    }

    /**
     * {@inheritdoc}
     *
     * Returns a URL built from the configured SMLISER_APP_URL.
     */
    public static function url( string $path = '', array $qv = [] ): URL {
        $base = static::$envProvider->app_url ?? '';
        return ( new URL( $base ) )
            ->append_path( $path )
            ->add_query_params( $qv );
    }

    /**
     * {@inheritdoc}
     *
     * No admin panel in CLI — returns the base URL.
     */
    public static function adminUrl( string $path = '', array $qv = [] ): URL {
        return static::url( $path, $qv );
    }

    /**
     * {@inheritdoc}
     *
     * Constructs the REST API URL from the app URL and namespace.
     */
    public static function restAPIUrl( string $path = '', array $qv = [] ): URL {
        $namespace = static::$envProvider->restProvider()->namespace();
        return static::url( 'wp-json/' . $namespace . '/' . ltrim( $path, '/' ), $qv );
    }

    /**
     * {@inheritdoc}
     *
     * No assets directory served in CLI — returns the base URL.
     */
    public static function assets_url( string $path = '' ): URL {
        return static::url( 'assets/' . ltrim( $path, '/' ) );
    }

    /**
     * {@inheritdoc}
     *
     * No-op in CLI — filesystem errors are not printed to a browser.
     * Errors will surface naturally as exceptions during job execution.
     */
    public function check_filesystem_errors(): void {}

    /**
     * {@inheritdoc}
     *
     * No-op in CLI — URL rewriting is a web server concern.
     */
    public function route_register(): void {}

    /*
    |--------------------------------------------
    | CLI-SPECIFIC MAINTENANCE METHODS
    |--------------------------------------------
    */

    /**
     * Create any missing database tables.
     *
     * Mirrors Installer::maybe_create_tables() from the WordPress adapter
     * without the WordPress dependency.
     *
     * @return void
     */
    public function install_tables(): void {
        $db     = smliser_db();
        $tables = DBTables::table_names();

        foreach ( $tables as $table ) {
            $existing = $db->get_var( 'SHOW TABLES LIKE ?', [ $table ] );

            if ( $table !== $existing ) {
                $this->create_table( $table, DBTables::get( $table ) );
                echo sprintf( '  Created table: %s' . PHP_EOL, $table );
            } else {
                echo sprintf( '  Already exists: %s' . PHP_EOL, $table );
            }
        }
    }

    /**
     * Install default permission roles.
     *
     * Mirrors Installer::install_default_roles() from the WordPress adapter.
     *
     * @return void
     */
    public function install_default_roles(): void {
        $default_roles = DefaultRoles::all();

        foreach ( $default_roles as $slug => $roledata ) {
            $role = new Role;
            $role->set_capabilities( $roledata['capabilities'] );
            $role->set_label( $roledata['label'] );
            $role->set_is_canonical( $roledata['is_canonical'] );
            $role->set_slug( $slug );

            try {
                $role->save();
                echo sprintf( '  Role installed: %s' . PHP_EOL, $slug );
            } catch ( \Throwable $e ) {
                echo sprintf( '  Role skipped (%s): %s' . PHP_EOL, $slug, $e->getMessage() );
            }
        }
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Create a single database table from a column definition array.
     *
     * @param string   $table_name Table name constant.
     * @param string[] $columns    Column definition strings from DBTables.
     * @return void
     */
    private function create_table( string $table_name, array $columns ): void {
        $db              = smliser_db();
        $charset_collate = $db->get_charset_collate();

        $sql  = "CREATE TABLE {$table_name} (";
        $sql .= implode( ', ', $columns );
        $sql .= ") {$charset_collate};";

        $db->query( $sql );
    }

    /*
    |--------------------------------------------
    | INTERNAL STATE
    |--------------------------------------------
    */

    /**
     * Base application URL read from SMLISER_APP_URL.
     *
     * Stored as an instance property so static URL methods can
     * access it via static::$envProvider->app_url.
     *
     * @var string
     */
    protected string $app_url = '';
}