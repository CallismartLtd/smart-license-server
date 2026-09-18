<?php
/**
 * Application environment class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Environments\Application\Boot\BootManager;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environment;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Boot\CLIBootstrapper;
use SmartLicenseServer\Environments\Application\Boot\WebBootstrapper;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Class ApplicationEnvironment
 *
 * Smart License Server running as a standalone PHP application environment.
 * Orchestrates container bindings and boot sequences for both HTTP/Web and CLI modes
 * using context-specific bootstrappers.
 *
 * @package SmartLicenseServer\Environments\Application
 * @since 0.2.0
 */
class ApplicationEnvironment extends Environment {

    /**
     * Boot manager instance for handling environment bootstrappers.
     *
     * @var BootManager
     */
    protected BootManager $bootManager;

    /**
     * {@inheritdoc}
     */
    protected function registerDependencies() : void {
        $this->container->singleton( ApplicationEnvironment::class, $this );
        
        $this->container->singleton(
            URLManager::class,
            fn ( Container $c ) : URLManager => new URLManager(
                settings: $c->get( Settings::class ),
                app_url: url(),
                admin_base_url: url(),
                assets_url: url( '/assets/' ),
            )
        );

        $this->container->alias(
            PasswordIdentityProviderInterface::class,
            IdentityService::class
        );

        $this->container->alias(
            RESTProviderInterface::class,
            RestAPIProvider::class
        );

        /*
         * Initialize BootManager and attach context bootstrappers.
         */
        $this->bootManager = new BootManager( $this->container );
        $this->bootManager
            ->add( new CLIBootstrapper() )
            ->add( new WebBootstrapper() );

        $this->bootManager->registerAll();
    }

    /**
     * {@inheritdoc}
     */
    public function boot() : void {
        DataStore::set_database(
            $this->container->get( Database::class )
        );

        DataStore::set_cache(
            $this->container->get( Cache::class )
        );

        DataStore::set_urlmanager(
            $this->container->get( URLManager::class )
        );

        // Shadow boot hosted app registry.
        $this->container->get( HostedAppsRegistry::class );

        /*
         * Boot environment bootstrappers (routing, assets, terminal inputs, etc.).
         */
        $this->bootManager->bootAll();

        /*
         * Trigger context identity authentication.
         */
        $this->container->get( IdentityService::class )->authenticate();
    }

    /**
     * {@inheritdoc}
     */
    protected function createDatabaseConfig() : DBConfigDTO {
        $dbConfig = new DBConfigDTO([
            'driver'   => $_ENV['SMLISER_DB_DRIVER'] ?? '',
            'host'     => $_ENV['SMLISER_DB_HOST'] ?? '',
            'port'     => $_ENV['SMLISER_DB_PORT'] ?? '',
            'dbname'   => $_ENV['SMLISER_DB_NAME'] ?? '',
            'username' => $_ENV['SMLISER_DB_USER'] ?? '',
            'password' => $_ENV['SMLISER_DB_PASSWORD'] ?? '',
            'charset'  => $_ENV['SMLISER_DB_CHARSET'] ?? '',
            'prefix'   => $_ENV['SMLISER_DB_PREFIX'] ?? '',
            'path'     => $_ENV['SMLISER_DB_PATH'] ?? '',
        ]);

        if (
            'sqlite' === $dbConfig->driver
            && ! empty( $_ENV['SMLISER_SQLITE_ENCRYPTION_KEY'] )
        ) {
            $dbConfig->encryption_key = $_ENV['SMLISER_SQLITE_ENCRYPTION_KEY'];
        }

        return $dbConfig;
    }
}