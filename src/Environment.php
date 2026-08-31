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
use SmartLicenseServer\Background\Queue\Adapters\DatabaseJobStorageAdapter;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Cache\CacheAdapterRegistry;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\EmailProviderIcons;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\FileSystem\Adapters\DirectFileSystem;
use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\Schema\DatabaseAdapterRegistry;
use SmartLicenseServer\SettingsAPI\Providers\Options;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateDiscovery;
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
    }

    /**
     * Register dependencies supplied by the core runtime.
     *
     * These are defaults. Concrete environments may replace any of these
     * bindings by registering their own implementation.
     */
    protected function registerCoreDependencies() : void {
        $this->container->singleton(
            Environment::class,
            $this
        );

        $this->container->singleton(
            RuntimeConfig::class,
            $this->runtime
        );

        $this->container->singleton(
            DatabaseAdapterRegistry::class,
            DatabaseAdapterRegistry::instance()
        );

        $this->container->singleton(
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

        $this->container->singleton(
            TemplateLocator::class,
            fn () : TemplateLocator => new TemplateLocator
        );

        $this->container->singleton(
            TemplateDiscovery::class,
            fn ( Container $c ) : TemplateDiscovery => 
                new TemplateDiscovery( $c->get( TemplateLocator::class ) )
        );
    }

    /**
     * Register environment-independent application services.
     */
    protected function registerCoreServices() : void {
        $this->container->singleton(
            Database::class,
            function ( Container $container ) : Database {
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
                FileSystem::instance(
                    $container->get( FileSystemAdapterInterface::class )
                )
        );

        $this->container->singleton(
            Cache::class,
            fn ( Container $container ) : Cache =>
                new Cache(
                    $container->get( CacheAdapterInterface::class ),
                    $container->get( Settings::class )
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
                Settings::instance(
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

        $this->container->singleton(
            HttpClient::class,
            fn () : HttpClient => new HttpClient( HttpClient::auto_client() )
        );

        // Initialize all core service registries.
        // Cache adapter registery.
        $this->container->singleton(
            CacheAdapterRegistry::class,
            fn ( Container $c ) : CacheAdapterRegistry =>
                CacheAdapterRegistry::instance(
                    $c->get( Settings::class )
                )      
        );

        // Email provider registry.
        $this->container->singleton(
            EmailProvidersRegistry::class,
            fn ( Container $c ) : EmailProvidersRegistry =>
                EmailProvidersRegistry::instance(
                    $c->get( Settings::class ),
                    $c->get( HttpClient::class )
                )
        );

        // Email Icon registry.
        $this->container->singleton(
            EmailProviderIcons::class,
            fn ( Container $c ) : EmailProviderIcons => new EmailProviderIcons(
                $c->get( URLManager::class )
            )
        );

        // Monetization providers registry.
        $this->container->singleton(
            MonetizationRegistry::class,
            fn ( Container $c ) : MonetizationRegistry =>
                new MonetizationRegistry( $c )
        );

        $this->container->singleton(
            Mailer::class,
            function ( Container $c ) : Mailer {
                $registry   = $c->get( EmailProvidersRegistry::class );
                return new Mailer( $registry->get_provider() );
            }
                
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
    abstract public function boot() : void;

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
        return new static( new Container(), $runtime );
    }
}