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
use SmartLicenseServer\Core\DBConfigDTO;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environments\CLI\CLIIdentityProvider;
use SmartLicenseServer\Exceptions\GlobalErrorHandler;

defined( 'SMLISER_ROOT' ) || exit;

/**
 * PHPUnit testing environment bootstrap.
 *
 * Provides a minimal non-interactive runtime for automated testing.
 */
class TestEnvironment extends Environment {

    /*
    |--------------
    | CONSTRUCTOR
    |--------------
    */

    /**
     * Private constructor — use boot().
     */
    private function __construct() {
        $this->setProps();
        $this->setPrincipal();
    }

    /**
     * Boot the test environment.
     *
     * @return void
     */
    public static function boot(): void {
        new static();
    }

    /*
    |----------------------
    | BOOTSTRAP
    |----------------------
    */

    /**
     * Configure the test environment.
     *
     * @return void
     */
    private function setProps(): void {

        GlobalErrorHandler::instance()
            ->bootstrap([
                'debug'             => true,
                'environment'       => 'test',
                'display_errors'    => true,
                'log_errors'        => false,
                'log_path'          => \SMLISER_ROOT . 'error.log',
            ])
            ->registerHandlers();

        static::$envProvider = $this;

        $this->dbConfig = new DBConfigDTO([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'path'      => ':memory:',
            'charset'   => 'utf8',
        ]);

        $this->app_url = 'http://localhost';

        $this->setup([
            'db_prefix'         => 'smliser_',
            'absolute_path'     => SMLISER_ROOT,
            'repo_path'         => SMLISER_ROOT,
            'uploads_dir'       => SMLISER_ROOT . 'tests/uploads',
            'secret'            => 'test-secret',
            'salt'              => 'test-salt',
            'identity_provider' => new CLIIdentityProvider(),
            'debug_mode'        => true,
        ]);
    }

    /**
     * Authenticate the test principal.
     *
     * @return void
     */
    private function setPrincipal(): void {
        $this->identityProvider->authenticate();
    }

    /*
    |----------------------------------------------
    | EnvironmentProviderInterface implementation
    |----------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public static function url( string $path = '', array $qv = [] ): URL {
        $base = static::$envProvider->app_url ?? '';

        return ( new URL( $base ) )
            ->append_path( $path )
            ->add_query_params( $qv );
    }

    /**
     * {@inheritdoc}
     */
    public static function adminUrl( string $path = '', array $qv = [] ): URL {
        return static::url( $path, $qv );
    }

    /**
     * {@inheritdoc}
     */
    public static function restAPIUrl( string $path = '', array $qv = [] ): URL {
        $namespace = static::$envProvider->restProvider()->namespace();

        return static::url( $namespace, $qv )
            ->append_path( $path );
    }

    /**
     * {@inheritdoc}
     */
    public static function assetsUrl( string $path = '', $params = [] ): URL {
        return static::url( 'assets', $params )
            ->append_path( $path );
    }

    /**
     * {@inheritdoc}
     */
    public function check_filesystem_errors(): void {}

    /**
     * {@inheritdoc}
     */
    public function route_register(): void {}

    /*
    |--------------------------------------------
    | INTERNAL STATE
    |--------------------------------------------
    */

    /**
     * Base application URL.
     *
     * @var string
     */
    protected string $app_url = '';
}