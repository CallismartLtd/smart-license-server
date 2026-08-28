<?php
/**
 * License Server environment configuration file
 *
 * @author Callistus
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer;

use Callismart\DBPrism\Adapters\Contracts\DatabaseAdapterInterface;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use Callismart\Http\HttpClient;
use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Background\Queue\Adapters\DatabaseJobStorageAdapter;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Cache\CacheAdapterRegistry;
use SmartLicenseServer\ClientDashboard\AuthTemplateRegistry;
use SmartLicenseServer\ClientDashboard\ClientDashboardRegistry;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\FileSystem\Adapters\DirectFileSystem;
use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Schema\DatabaseAdapterRegistry;
use SmartLicenseServer\SettingsAPI\Providers\Options;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * The abstract application environment and service bootstrap layer.
 *
 * The environment owns the dependency injection container and registers
 * environment-independent application services. Concrete environment providers
 * may override core bindings by registering their own implementations.
 *
 * @package SmartLicenseServer
 * @since 0.2.0
 */
abstract class Environment {
    /**
     * Class constructor.
     *
     * @param Container     $container The dependency injection container.
     * @param RuntimeConfig $runtime   Runtime configuration.
     */
    final protected function __construct(
        protected Container $container,
        protected RuntimeConfig $runtime
    ) {
        $this->registerCoreDependencies();
        $this->registerCoreServices();

        $this->registerDependencies();
        $this->validateEnvironment();

        $this->boot();
    }

    /**
     * Register dependencies supplied by the core runtime.
     *
     * These are defaults. Concrete environments may replace any of these
     * bindings by registering their own implementation.
     */
    protected function registerCoreDependencies() : void {
        $this->container->set(
            Environment::class,
            $this
        );

        $this->container->set(
            RuntimeConfig::class,
            $this->runtime
        );

        $this->container->set(
            DatabaseAdapterRegistry::class,
            DatabaseAdapterRegistry::instance()
        );

        $this->container->set(
            CacheAdapterRegistry::class,
            fn ( Container $container ) : CacheAdapterRegistry => $container->get( CacheAdapterRegistry::class )
        );

        $this->container->singleton(
            DBConfigDTO::class,
            fn () : DBConfigDTO => $this->createDatabaseConfig()
        );

        /*
         * Core defaults.
         */
        $this->container->singleton(
            FileSystemAdapterInterface::class,
            fn ( Container $container ) : FileSystemAdapterInterface =>
                $container->get( DirectFileSystem::class )
        );

        $this->container->singleton(
            SettingsStorageInterface::class,
            fn ( Container $container ) : SettingsStorageInterface =>
                $container->get( Options::class )
        );
    }

    /**
     * Register environment-independent application services.
     */
    protected function registerCoreServices() : void {
        $this->container->singleton(
            Database::class,
            function ( Container $container ) : Database {
                $config  = $container->get( DBConfigDTO::class );
                $adapter = $container->get( DatabaseAdapterInterface::class );

                return new Database( $adapter );
            }
        );

        $this->container->singleton(
            DatabaseAdapterInterface::class,
            function ( Container $container ) : DatabaseAdapterInterface {
                $config   = $container->get( DBConfigDTO::class );
                $registry = $container->get( DatabaseAdapterRegistry::class );
                $adapter  = $registry->select( $config->driver );

                return new $adapter( $config );
            }
        );

        $this->container->singleton(
            FileSystem::class,
            fn ( Container $container ) : FileSystem =>
                new FileSystem(
                    $container->get( FileSystemAdapterInterface::class )
                )
        );

        $this->container->singleton(
            Cache::class,
            fn ( Container $container ) : Cache =>
                new Cache(
                    $container->get( CacheAdapterInterface::class )
                )
        );

        $this->container->singleton(
            CacheAdapterInterface::class,
            fn ( Container $container ) : CacheAdapterInterface =>
                $container->get( CacheAdapterRegistry::class )->get_adapter()
        );

        $this->container->singleton(
            Settings::class,
            fn ( Container $container ) : Settings =>
                new Settings(
                    $container->get( SettingsStorageInterface::class )
                )
        );

        $this->container->singleton(
            JobQueue::class,
            fn ( Container $container ) : JobQueue =>
                new JobQueue(
                    new DatabaseJobStorageAdapter(
                        $container->get( Database::class )
                    )
                )
        );

        $this->container->singleton(
            QueueWorker::class,
            fn ( Container $container ) : QueueWorker =>
                new QueueWorker(
                    $container->get( JobQueue::class ),
                    memory_limit_mb: safe_worker_memory_limit_mb()
                )
        );

        /*
         * These are deliberately left to autowiring where their constructors
         * contain only resolvable dependencies.
         */
        $this->container->singleton(
            HttpClient::class,
            fn ( Container $container ) : HttpClient =>
                $container->get( HttpClient::class )
        );

        // $this->container->singleton(
        //     Mailer::class,
        //     fn ( Container $container ) : Mailer =>
        //         $this->createMailer( $container )
        // );

        $this->container->singleton(
            TemplateLocator::class,
            fn () : TemplateLocator =>
                new TemplateLocator()
        );

        $this->container->singleton(
            AdminDashboardRegistry::class,
            fn () : AdminDashboardRegistry =>
                new AdminDashboardRegistry()
        );

        $this->container->singleton(
            ClientDashboardRegistry::class,
            fn () : ClientDashboardRegistry =>
                new ClientDashboardRegistry()
        );

        $this->container->singleton(
            AuthTemplateRegistry::class,
            fn () : AuthTemplateRegistry =>
                new AuthTemplateRegistry()
        );
    }

    /**
     * Register dependencies supplied by the concrete environment.
     *
     * Child environments should override this method and replace bindings
     * where the host environment has a specialized implementation.
     */
    abstract protected function registerDependencies() : void;
    
    /**
     * Complete environment-specific application bootstrap.
     *
     * Called after core services and environment dependencies have been
     * registered with the container.
     */
    abstract protected function boot() : void;

    /**
     * Validate that all dependencies required by the environment are available.
     *
     * Child environments should override this method when they have mandatory
     * environment-specific bindings.
     */
    protected function validateEnvironment() : void {
    }

    /**
     * Create the database configuration.
     *
     * Concrete environments must provide this configuration.
     */
    abstract protected function createDatabaseConfig() : DBConfigDTO;

    /**
     * Create the default mailer.
     *
     * Concrete environments may override this when mail delivery differs.
     */
    // abstract protected function createMailer( Container $container ) : Mailer;

    /**
     * Get the application container.
     */
    public function container() : Container {
        return $this->container;
    }

    /**
     * Get the runtime configuration.
     */
    public function runtime() : RuntimeConfig {
        return $this->runtime;
    }

    /**
     * Create a new application environment.
     */
    public static function create( RuntimeConfig $runtime ) : static {
        return new static(
            new Container(),
            $runtime
        );
    }
}