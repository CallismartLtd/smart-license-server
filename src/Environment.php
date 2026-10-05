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

use SmartLicenseServer\Schema\TableName;
use Callismart\DBPrism\Adapters\Contracts\DatabaseAdapterInterface;
use Callismart\DBPrism\Adapters\NullDBAdapter;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use Callismart\Http\HttpClient;
use SmartLicenseServer\Background\Queue\Adapters\DatabaseJobStorageAdapter;
use SmartLicenseServer\Background\Queue\Adapters\JobStorageAdapterInterface;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Cache\Adapters\SQLiteCacheAdapter;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Cache\CacheAdapterRegistry;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Email\EmailProviderIcons;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Email\Providers\AmazonSESProvider;
use SmartLicenseServer\Email\Providers\BrevoProvider;
use SmartLicenseServer\Email\Providers\MailgunProvider;
use SmartLicenseServer\Email\Providers\PHPMailProvider;
use SmartLicenseServer\Email\Providers\PostmarkProvider;
use SmartLicenseServer\Email\Providers\ResendProvider;
use SmartLicenseServer\Email\Providers\SendGridProvider;
use SmartLicenseServer\Email\Providers\SMTPProvider;
use SmartLicenseServer\FileSystem\Adapters\DirectFileSystem;
use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\Schema\DatabaseAdapterRegistry;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\Providers\Options;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateDiscovery;
use SmartLicenseServer\Templates\TemplateLocator;
use SmartLicenseServer\Utils\MDParser;

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
     * Resolved database configuration: false until resolved, null when no
     * database is configured.
     *
     * @var DBConfigDTO|false|null
     */
    private DBConfigDTO|false|null $databaseConfig = false;

    /**
     * Class constructor.
     * 
     * Creation of a new smart license application must be done using the child class
     * `static::create()` method passing the runtime configuration DTO to it.
     *
     * @param Container     $container The dependency injection container.
     * @param RuntimeConfig $runtime   Runtime configuration.
     */
    final private function __construct(
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
            SchemaRegistry::class,
            SchemaRegistry::instance()->set_prefix( $this->runtime->db_table_prefix )
        );

        $this->container->singleton(
            DatabaseAdapterRegistry::class,
            fn ( Container $c ) : DatabaseAdapterRegistry =>
                DatabaseAdapterRegistry::instance( $c )
        );

        $this->container->singleton(
            CacheAdapterRegistry::class,
            fn ( Container $c ) : CacheAdapterRegistry =>
                CacheAdapterRegistry::instance( $c )
        );

        $this->container->singleton(
            HostedAppsRegistry::class,
            fn ( Container $c ) : HostedAppsRegistry =>
                HostedAppsRegistry::instance( $c )
        );

        $this->container->singleton(
            DBConfigDTO::class,
            function () : DBConfigDTO {
                $config = $this->databaseConfig();

                if ( null === $config ) {
                    throw new DatabaseException(
                        'database_not_configured',
                        'No database is configured. Check Database::has_null_adapter() before resolving the database configuration.'
                    );
                }

                return $config;
            }
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
            fn () : TemplateLocator => new TemplateLocator()
        );

        $this->container->singleton(
            TemplateDiscovery::class,
            fn ( Container $c ) : TemplateDiscovery => 
                new TemplateDiscovery( $c->get( TemplateLocator::class ) )
        );

        $this->container->set(
            SQLiteCacheAdapter::class,
            fn () : SQLiteCacheAdapter => 
                new SQLiteCacheAdapter(
                    base_dir: \SMLISER_CACHE_DIR,
                    db_filename: 'smliser-cache.sqlite',
                    stats_table: 'smliser_stats_table',
                    table: 'smliser_main_cache',
                    storage_limit: 512,
                    cache_memory: 4,
                    dir_perm: \SMLISER_DIR_PERMISSION
                )
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
            function ( Container $c ) : DatabaseAdapterInterface {
                $config   = $this->databaseConfig();

                /*
                 * No database configured yet (e.g. before installation):
                 * use a placeholder so Database and its dependents still
                 * construct. The installer swaps in a real adapter.
                 */
                if ( null === $config ) {
                    return new NullDBAdapter();
                }

                $registry = $c->get( DatabaseAdapterRegistry::class );
                $adapter  = $registry->select( $config->driver );

                return $c->get( $adapter );
            }
        );

        $this->container->set(
            DirectFileSystem::class,
            fn () : DirectFileSystem => new DirectFileSystem(
                \SMLISER_FILE_PERMISSION,
                \SMLISER_DIR_PERMISSION
            )
        );

        $this->container->singleton(
            FileSystem::class,
            fn ( Container $container ) : FileSystem =>
                FileSystem::instance(
                    $container->get( FileSystemAdapterInterface::class )
                )
        );
        $this->container->singleton(
            CacheAdapterInterface::class,
            fn ( Container $container ) : CacheAdapterInterface =>
                $container->get( CacheAdapterRegistry::class )->get_adapter()
        );
        
        $this->container->singleton(
            Cache::class,
            fn ( Container $container ) : Cache =>
                Cache::instance(
                    $container->get( CacheAdapterInterface::class ),
                    $container->get( Settings::class )
                )
        );

        $this->container->singleton(
            Settings::class,
            fn ( Container $container ) : Settings =>
                Settings::instance(
                    $container->get( SettingsStorageInterface::class )
                )
        );

        $this->container->singleton( Guard::class, fn () : Guard => new Guard() );

        $this->container->set(
            DatabaseJobStorageAdapter::class,
            fn ( Container $c ) : DatabaseJobStorageAdapter =>
                new DatabaseJobStorageAdapter(
                    $c->get( Database::class ),
                    TableName::BACKGROUND_JOBS->table(),
                    TableName::FAILED_JOBS->table()
                )
        );

        $this->container->singleton(
            JobStorageAdapterInterface::class,
            fn ( Container $c ) : JobStorageAdapterInterface =>
                $c->get( DatabaseJobStorageAdapter::class )
        );

        $this->container->singleton(
            JobQueue::class,
            fn ( Container $c ) : JobQueue =>
                new JobQueue( $c->get( JobStorageAdapterInterface::class ) )
        );

        $this->container->singleton(
            QueueWorker::class,
            fn ( Container $c ) : QueueWorker =>
                new QueueWorker(
                    queue: $c->get( JobQueue::class ),
                    container: $c,
                    memory_limit_mb: safe_worker_memory_limit_mb()
                )
        );

        $this->container->singleton(
            HttpClient::class,
            fn () : HttpClient => new HttpClient( HttpClient::auto_client() )
        );

        // Email provider registry.
        $this->container->singleton(
            EmailProvidersRegistry::class,
            fn ( Container $c ) : EmailProvidersRegistry =>
                EmailProvidersRegistry::instance( $c )
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
                MonetizationRegistry::instance( $c )
        );

        $this->container->singleton(
            Mailer::class,
            function ( Container $c ) : Mailer {
                $registry = $c->get( EmailProvidersRegistry::class );
                return new Mailer( $registry->get_provider() );
            }
        );

        $this->container->set(
            MDParser::class,
            fn () : MDParser =>
                new MDParser( [
                    'html_input'         => 'allow',
                    'allow_unsafe_links' => false,
                ] )
        );

        $this->container->set(
            SMTPProvider::class,
            fn ( Container $c ) : SMTPProvider => new SMTPProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email()
            )
        );

        $this->container->set(
            PHPMailProvider::class,
            fn ( Container $c ) : PHPMailProvider => new PHPMailProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email()
            )
        );

        $this->container->set(
            BrevoProvider::class,
            fn ( Container $c ) : BrevoProvider => new BrevoProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email(),
                $c->get( HttpClient::class )
            )
        );

        $this->container->set(
            AmazonSESProvider::class,
            fn ( Container $c ) : AmazonSESProvider => new AmazonSESProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email(),
                $c->get( HttpClient::class )
            )
        );

        $this->container->set(
            MailgunProvider::class,
            fn ( Container $c ) : MailgunProvider => new MailgunProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email(),
                $c->get( HttpClient::class )
            )
        );

        $this->container->set(
            PostmarkProvider::class,
            fn ( Container $c ) : PostmarkProvider => new PostmarkProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email(),
                $c->get( HttpClient::class )
            )
        );

        $this->container->set(
            ResendProvider::class,
            fn ( Container $c ) : ResendProvider => new ResendProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email(),
                $c->get( HttpClient::class )
            )
        );

        $this->container->set(
            SendGridProvider::class,
            fn ( Container $c ) : SendGridProvider => new SendGridProvider(
                $c->get( EmailProvidersRegistry::class )->get_default_sender_name(),
                $c->get( EmailProvidersRegistry::class )->get_default_sender_email(),
                $c->get( HttpClient::class )
            )
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
     * Concrete environments must provide this configuration, or null when no
     * database is configured yet (e.g. before installation). Null makes the
     * application run on a NullDBAdapter placeholder.
     *
     * @return DBConfigDTO|null
     */
    abstract protected function createDatabaseConfig() : ?DBConfigDTO;

    /**
     * Get the database configuration, creating it once per environment.
     *
     * @return DBConfigDTO|null Null when no database is configured.
     */
    final protected function databaseConfig() : ?DBConfigDTO {
        if ( false === $this->databaseConfig ) {
            $this->databaseConfig = $this->createDatabaseConfig();
        }

        return $this->databaseConfig;
    }

    /**
     * Get the application container.
     *
     * @return Container
     */
    public function container() : Container {
        return $this->container;
    }

    /**
     * Get the runtime configuration.
     *
     * @return RuntimeConfig
     */
    public function runtime() : RuntimeConfig {
        return $this->runtime;
    }

    /**
     * Create a new application environment instance.
     * 
     * Recommended way to instantiate the application environment.
     * NOTE: This method must be called on a concrete class extending Environment.
     * 
     * @param RuntimeConfig $runtime The immutable runtime configuration DTO.
     * @return static
     */
    public static function create( RuntimeConfig $runtime ) : static {
        return new static( new Container(), $runtime );
    }
}