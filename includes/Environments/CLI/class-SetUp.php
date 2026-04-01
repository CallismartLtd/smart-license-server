<?php
/**
 * CLI environment bootstrap class file.
 *
 * Provides the Smart License Server environment adapter for command-line
 * execution. Follows the same pattern as the WordPress SetUp class —
 * extends Environment, sets static::$envProvider, builds the $env array
 * from environment variables, and calls $this->setup().
 *
 * No hook system, no admin UI, no REST registration. The CLI caller
 * (smliser) drives execution directly after construction.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\CLI
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\CLI;

use SmartLicenseServer\Cache\CacheAdapterCollection;
use SmartLicenseServer\Environment;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\Runners\CLIRunner;
use SmartLicenseServer\Console\Runners\InteractiveShell;
use SmartLicenseServer\Core\DBConfigDTO;
use SmartLicenseServer\Core\DotEnv;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * CLI environment adapter.
 *
 * Bootstraps Smart License Server for command-line usage.
 * Reads all configuration from environment variables so the
 * adapter has zero WordPress dependency.
 */
class SetUp extends Environment {

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
        $this->loadDotEnv();
        $this->setProps();
        $this->setPrincipal();
        $this->init();
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
     * Load the .env file from the root directory.
     * 
     * @return void
     */
    private function loadDotEnv() : void {
        ( new DotEnv( SMLISER_ABSPATH ) )
        ->load();
    }

    /**
     * Read environment variables, build the $env array, and call setup().
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

        $this->dbConfig = new DBConfigDTO([
            'driver'   => 'mysql',
            'host'     => $db_host,
            'port'     => $db_port,
            'database' => $db_name,
            'username' => $db_user,
            'password' => $db_pass,
            'charset'  => $db_charset,
        ]);

        // Store app_url for the URL methods.
        $this->app_url = $app_url;

        $env = [
            'db_prefix'        => $db_prefix,
            'absolute_path'    => SMLISER_ABSPATH,
            'repo_path'        => $repo_path,
            'uploads_dir'      => $uploads_dir,
            'rest_api_provider' => new CLIRESTProvider(),
            'secret'            => $_ENV['SMLISER_SECRET'] ?? '',
            'salt'              => $_ENV['SMLISER_SALT'] ?? '',
        ];

        $this->setup( $env );

        // Initialize the cache adapter if configured.
        if ( isset( $_ENV['SMLISER_CACHE_ADAPTER'] ) ) {
            $adapter_id = $_ENV['SMLISER_CACHE_ADAPTER'];
            CacheAdapterCollection::instance()->set_default_adapter( $adapter_id );    
        }

    }

    /**
     * Sets the principal/current actor for the CLI environment.
     * 
     * @return void
     */
    private function setPrincipal() : void {
        ( new CLIIdentityProvider() )
        ->authenticate();
    }

    /**
     * Initialize the CLI envionment and hand over the request to the runner.
     * 
     * @return void
     */
    private function init() : void {
        $registry   = CommandRegistry::instance();
        $argv       = $GLOBALS['argv'] ?? [];
        $runner     = isset( $argv[1] ) ? new CLIRunner( $registry, $argv ) : new InteractiveShell( $registry );
        
        $runner->register();
    }

    /*
    |--------------------------------------------
    | EnvironmentProviderInterface implementation
    |--------------------------------------------
    */

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