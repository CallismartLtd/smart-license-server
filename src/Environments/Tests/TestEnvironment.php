<?php
/**
 * Test environment bootstrap class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Test
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Tests;

use SmartLicenseServer\Environment;
use Callismart\DBPrism\DBConfigDTO;
use SmartLicenseServer\Core\DotEnv;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environments\CLI\CLIIdentityProvider;
use SmartLicenseServer\Exceptions\GlobalErrorHandler;

/**
 * PHPUnit testing environment bootstrap.
 *
 * Provides a minimal non-interactive runtime for automated testing.
 */
class TestEnvironment extends Environment {

    private function __construct() {
        $this->loadDotEnv();
        $this->setProps();
        $this->setPrincipal();
    }

    public static function boot(): void {
        new static();
    }

    /*
    |----------------------
    | BOOTSTRAP
    |----------------------
    */

    private function loadDotEnv(): void {
        ( new DotEnv( SMLISER_ROOT ) )->load();
    }

    private function setProps(): void {

        GlobalErrorHandler::instance()
            ->bootstrap([
                'debug'          => true,
                'environment'    => 'test',
                'display_errors' => true,
                'log_errors'     => false,
                'log_path'       => SMLISER_ROOT . 'error.log',
            ])
            ->registerHandlers();

        static::$envProvider = $this;

        /**
         * Read DB config from .env (same pattern as CLI)
         */
        $db_driver = $_ENV['SMLISER_DB_DRIVER'] ?? 'mysql';

        $this->dbConfig = new DBConfigDTO([
            'driver'   => $db_driver,
            'host'     => $_ENV['SMLISER_DB_HOST'] ?? '127.0.0.1',
            'port'     => (int) ($_ENV['SMLISER_DB_PORT'] ?? 3306),
            'database' => $_ENV['SMLISER_DB_NAME'] ?? 'test',
            'username' => $_ENV['SMLISER_DB_USER'] ?? 'root',
            'password' => $_ENV['SMLISER_DB_PASSWORD'] ?? '',
            'charset'  => $_ENV['SMLISER_DB_CHARSET'] ?? 'utf8mb4',
        ]);

        $this->setup([
            'db_prefix'         => $_ENV['SMLISER_DB_PREFIX'] ?? 'smwoo_',
            'absolute_path'     => SMLISER_ROOT,
            'repo_path'         => $_ENV['SMLISER_REPO_PATH'] ?? SMLISER_ROOT,
            'uploads_dir'       => $_ENV['SMLISER_UPLOADS_DIR'] ?? SMLISER_ROOT . 'tests/uploads',
            'secret'            => $_ENV['SMLISER_SECRET'] ?? 'test-secret',
            'salt'              => $_ENV['SMLISER_SALT'] ?? 'test-salt',
            'rest_api_provider' => new \SmartLicenseServer\Environments\CLI\CLIRESTProvider(),
            'identity_provider' => new CLIIdentityProvider(),
            'debug_mode'        => true,
        ]);
    }

    private function setPrincipal(): void {
        $this->identityProvider->authenticate();
    }

    /*
    |----------------------
    | URL PROVIDERS
    |----------------------
    */

    public static function url( string $path = '', array $qv = [] ): URL {
        return ( new URL( '' ) )
            ->append_path( $path )
            ->add_query_params( $qv );
    }

    public static function adminUrl( string $path = '', array $qv = [] ): URL {
        return static::url( $path, $qv );
    }

    public static function restAPIUrl( string $path = '', array $qv = [] ): URL {
        $namespace = static::$envProvider->restProvider()->namespace();

        return static::url( $namespace, $qv )
            ->append_path( $path );
    }

    public static function assetsUrl( string $path = '', $params = [] ): URL {
        return static::url( 'assets', $params )
            ->append_path( $path );
    }

    public function check_filesystem_errors(): void {}
    public function route_register(): void {}
}