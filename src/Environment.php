<?php
/**
 * License Server environment configuration file
 * 
 * @author Callistus
 * @package SmartLicenseServer
 * @since 0.2.0
 */

namespace SmartLicenseServer;

use SmartLicenseServer\Background\Queue\Adapters\DatabaseJobStorageAdapter;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Cache\CacheAdapterRegistry;
use Callismart\DBPrism\DBConfigDTO;
use SmartLicenseServer\Core\Request;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\Adapters\Contracts\DatabaseAdapterInterface;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Environments\EnvironmentProviderInterface;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\FileSystem\Adapters\DirectFileSystem;
use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;
use SmartLicenseServer\FileSystem\FileSystem;
use Callismart\Http\HttpClient;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\SettingsAPI\Providers\Options;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;
use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\ClientDashboard\AuthTemplateRegistry;
use SmartLicenseServer\ClientDashboard\ClientDashboardRegistry;
use SmartLicenseServer\Events\Bootstrap\EnvironmentBooted;
use SmartLicenseServer\Events\Bootstrap\EnvironmentBooting;
use SmartLicenseServer\Events\Bootstrap\EnvironmentReady;
use SmartLicenseServer\Events\EventServiceProvider;
use SmartLicenseServer\Schema\DatabaseAdapterRegistry;
use SmartLicenseServer\Security\Authentication\IdentityProviders\IdentityProviderInterface;
use SmartLicenseServer\Templates\TemplateDiscovery;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * The abstract application environment and service bootstrap layer. 
 * It provides the environment-independent foundation through which host-specific environments
 * integrate their implementations with the Smart License Server core.
 * 
 * This class serves as the foundation for initializing the application environment
 * in a runtime-agnostic way. It is responsible for:
 * 
 * - Parsing and validating configuration provided by the environment provider.
 * - Instantiating and wiring core services and adapters.
 * - Providing accessors for all core components.
 * 
 * Note: This class does not handle request/response lifecycles; that responsibility
 * belongs to the specific environment provider (e.g., CLI, HTTP).
 * 
 * @package SmartLicenseServer
 * @since 0.2.0
 */
abstract class Environment implements EnvironmentProviderInterface {
    /**
     * Centralized, immutable environment configuration obejct.
     */
    protected RuntimeConfig $runtime;
    /**
     * The current environment provider instance.
     * 
     * Must be set early by the child class providing execution environment
     * for Smart License Server.
     * 
     * @var EnvironmentProviderInterface
     */
    protected static EnvironmentProviderInterface $envProvider;

    /**
     * The current REST API Service Provider
     * 
     * @var RESTProviderInterface $restProvider
     */
    protected RESTProviderInterface $restProvider;

    /**
     * The cache adapter.
     * 
     * @var CacheAdapterInterface $cacheAdapter
     */
    protected CacheAdapterInterface $cacheAdapter;

    /**
     * The user settings storage provider interface.
     * 
     * @var SettingsStorageInterface $settingsStorage
     */
    protected SettingsStorageInterface $settingsStorage;

    /**
     * Filesystem API
     * 
     * @var FileSystemAdapterInterface filesystemAdapter
     */
    protected FileSystemAdapterInterface $filesystemAdapter;

    /**
     * The database API adapter.
     */
    protected DatabaseAdapterInterface $dbadapter;

    /**
     * The cache API.
     */
    protected Cache $cache;

    /**
     * Overridable properties map
     * 
     * @var array $prop_map
     */
    protected array $prop_map = [
        'filesystem_adapter'    => null,
        'settings_provider'     => null,
        'database_adapter'      => null,
        'rest_api_provider'     => null,
        'admin_menu_config'     => null,
        'identity_provider'     => null
    ];

    /**
     * The required configuration keys for the environment.
     * 
     * @var string[] $required_config
     */
    protected array $required_config = [];

    /**
     * The current request object.
     */
    protected Request $request;

    /**
     * The database API abstraction.
     * 
     * Database $database
     */
    protected Database $database;

    /**
     * The filesystem API abstraction.
     */
    protected FileSystem $filesystem;

    /**
     * The settings API abstraction.
     */
    protected Settings $settings;

    /**
     * The mailing API.
     * 
     * @var Mailer $mailer
     */
    protected Mailer $mailer;

    /**
     * All email providers registry.
     * 
     * @var EmailProvidersRegistry $emailProviders
     */
    protected EmailProvidersRegistry $emailProviders;

    /**
     * Database configuration class.
     * 
     * @var DBConfigDTO
     */
    protected DBConfigDTO $dbConfig;

    /**
     * Background job queue API.
     * 
     * @var JobQueue $job_queue
     */
    protected JobQueue $job_queue;

    /**
     * Background job worker API.
     * 
     * @var JobQueue $job_queue
     */
    protected QueueWorker $queue_worker;

    /**
     * The http client API.
     * 
     * @var HttpClient $httpClient
     */
    protected HttpClient $httpClient;

    /**
     * Monetization provider registry.
     * 
     * @var MonetizationRegistry $monetizationRegistry
     */
    protected MonetizationRegistry $monetizationRegistry;

    /**
     * Admin dashboard registry.
     * 
     * @var AdminDashboardRegistry $adminDashboardRegistry
     */
    protected AdminDashboardRegistry $adminDashboardRegistry;

    /**
     * Client dashboard registry.
     * 
     * @var ClientDashboardRegistry $clientDashboardRegistry
     */
    protected ClientDashboardRegistry $clientDashboardRegistry;

    /**
     * Authentication template registry.
     * 
     * @var AuthTemplateRegistry $authTemplateRegistry
     */
    protected AuthTemplateRegistry $authTemplateRegistry;

    /**
     * Template locator.
     * 
     * @var TemplateLocator $templateLocator
     */
    protected TemplateLocator $templateLocator;

    /**
     * The identity provider.
     */
    protected IdentityProviderInterface $identityProvider;

    /**
     * Environment constructor.
     * 
     * This is the entry point to Smart License Server, all environment providers must call
     * this method and pass the required keys.
     * 
     * @param array{
     *      filesystem_adapter?: FileSystemAdapterInterface, 
     *      settings_provider?: SettingsStorageInterface,
     *      database_adapter?: DatabaseAdapterInterface,
     *      rest_api_provider: RESTProviderInterface,
     *      identity_provider: IdentityProviderInterface,
     * } $config The overridable environment configuration options.
     * @throws EnvironmentBootstrapException If required configuration is missing or invalid.
     */
    final protected function setup( array $config ) {
        EventServiceProvider::instance()->boot();
        smliser_dispatch_event( new EnvironmentBooting() );

        $this->parse_config( $config );
        $this->setProps();
        
        smliser_dispatch_event( new EnvironmentBooted );
    }

    /*
    |-----------------------
    | HELPERS
    |-----------------------
    */

    /**
     * Parse overridable property values
     * 
     * @param array $props
     */
    private function parse_config( $props ) : void {
        $parsed_props  = array_intersect_key( 
            array_merge( $this->prop_map, $props ),
            $this->prop_map
        );

        $missing_config = [];

        foreach ( $parsed_props as $key => $value ) {
            if ( in_array( $key, $this->required_config, true ) && $value === null ) {
                $missing_config[] = $key;
            }
        }

        if ( ! empty( $missing_config ) ) {
            $message    = \sprintf( '%s environment has missing required configuration(s): %s',
                \SMLISER_APP_NAME,
                \implode( ', ', $missing_config )
            );

            throw new EnvironmentBootstrapException( 'misconfiguration', $message );
        }

        $this->prop_map  = $parsed_props;
    }

    /**
     * Sets up the class properties.
     */
    private function setProps() : void {
        $prop_map   = [
            'filesystem_adapter'    => 'filesystemAdapter',
            'settings_provider'     => 'settingsStorage',
            'database_adapter'      => 'dbadapter',
            'rest_api_provider'     => 'restProvider',
            'http_client'           => 'httpClient',
            'identity_provider'     => 'identityProvider',
        ];

        foreach ( $prop_map as $env_k => $prop_k ) {
            if ( isset( $this->{$prop_k} ) ) {
                // Preserve injected adapter if already set.
                continue;
            }

            if ( ! isset( $this->prop_map[$env_k] ) ) {
                continue;
            }

            if ( ! property_exists( $this, $prop_k ) ) {
                throw new EnvironmentBootstrapException(
                    'unsupported_config',
                    sprintf( 'The provided configuration "%s" is not supported.', $prop_k )
                );
            }
            
            $this->{$prop_k}    = $this->prop_map[$env_k];
        }

        // instanciate the cache registry.
        CacheAdapterRegistry::instance( $this->settings() );

        if ( ! isset( $this->request ) ) {
            $this->request = Request::createFromGlobals();
        }

        smliser_dispatch_event( new EnvironmentReady );
    }

    /**
     * Sets up the database adapter
     */
    public function setDBAdapter() : void {
        if ( ! isset( $this->dbadapter ) ) {
            if ( ! isset( $this->dbConfig ) ) {
                throw new EnvironmentBootstrapException( 'missing_db_config' );
            }

            $db_registry        = DatabaseAdapterRegistry::instance();
            $adapter            = $db_registry->select( $this->dbConfig->driver );

            $this->dbadapter    = new $adapter( $this->dbConfig );
        }
        
        $this->database = new Database( $this->dbadapter );
    }

    /**
     * Sets up the global filesystem adapter
     */
    public function setFileSystemAdapter() : void {
        if ( ! isset( $this->filesystemAdapter ) ) {
            $this->filesystemAdapter = new DirectFileSystem;
        }

        $this->filesystem    = new FileSystem( $this->filesystemAdapter );
    }

    /**
     * Sets up the global cache adapter.
     * 
     * @param bool $force Whether to force reloading the cache provider.
     */
    public function setCacheAdapter( bool $force = false ) : void {

        if ( $force ) {
            $this->cacheAdapter = CacheAdapterRegistry::instance( $this->settings() )->get_adapter();
        }

        if ( ! isset( $this->cacheAdapter ) ) {
            $this->cacheAdapter = CacheAdapterRegistry::instance( $this->settings() )->get_adapter();
        }

        $this->cache    = new Cache( $this->cacheAdapter );

    }

    /**
     * Sets up the global settings adapter
     */
    public function initSettingsAdapter() : void {
        if ( ! isset( $this->settingsStorage ) ) {
            $this->settingsStorage = new Options( $this->database() );
        }

        $this->settings = new Settings( $this->settingsStorage );
    }

    /**
     * Sets up the global mailing service to use the default provider.
     */
    public function setMailingAdapter() : void {
        // Instantiate the email registry with storage.
        $registry       = $this->emailProviders();
        $this->mailer   = new Mailer( $registry->get_provider() );
    }

    /**
     * Sets the global background job queue adapter.
     *
     * Derives a safe memory ceiling from the PHP runtime ini value so
     * the worker never assumes a fixed limit that may be wrong in production.
     * Uses 80% of the actual memory_limit as the worker ceiling, leaving
     * headroom for WordPress core, plugins, and the request itself.
     *
     * Does not override adapter or worker instances already set by the
     * environment (e.g. a test environment injecting a mock worker).
     */
    public function setQueueAdapter(): void {
        if ( ! isset( $this->job_queue ) ) {
            $this->job_queue = new JobQueue( new DatabaseJobStorageAdapter( $this->database() ) );
        }
 
        if ( ! isset( $this->queue_worker ) ) {
            $this->queue_worker = new QueueWorker(
                $this->job_queue,
                memory_limit_mb: safe_worker_memory_limit_mb(),
            );
        }
    }

    /*
    |-------------------------
    | ACCESSORS
    |-------------------------
    */

    /**
     * Get the namespace.
     * 
     * @return string[]
     */
    public function rest_namespaces() : array {
        return array_map( [$this, 'apply_rest_prefix'], $this->restProvider->namespaces() );
    }

    /**
     * Apply REST API prefix.
     * 
     * @return string
     */
    public function apply_rest_prefix( string $value ) : string {
        return "smliser/$value";
    }

    /**
     * Get the REST API provider instance.
     */
    public function restProvider() : RESTProviderInterface {
        return $this->restProvider;
    }

    /**
     * Get the database instance.
     */
    public function database() : Database {
        if ( ! isset( $this->database ) ) {
            $this->setDBAdapter();

            if ( ! $this->database->is_connected() ) {
                $error_message = \smliser_debug_enabled() 
                ? $this->database->get_last_error()
                : '';
                
                throw new EnvironmentBootstrapException( 'database_connect_error', $error_message );
            }
        }

        return $this->database;
    }

    /**
     * Get the filesystem abstraction instance.
     */
    public function filesystem() : FileSystem {
        if ( ! isset( $this->filesystem ) ) {
            $this->setFileSystemAdapter();
        }

        return $this->filesystem;
    }

    /**
     * Get the cache instance
     */
    public function cache() : Cache {
        if ( ! isset( $this->cache ) ) {
            $this->setCacheAdapter();
        }

        return $this->cache;
    }

    /**
     * Get the settings API instance.
     */
    public function settings() : Settings {
        if ( ! isset( $this->settings ) ) {
            $this->initSettingsAdapter();
        }

        return $this->settings;
    }

    /**
     * Get the mailer API instance.
     * 
     * Lazily loaded by default since not all environments may require mailing capabilities, and
     * some environments may want to inject their own mailer instance (e.g. for testing or to use a different email provider).
     */
    public function mailer() : Mailer {
        if ( ! isset( $this->mailer ) ) {
            $this->setMailingAdapter();
        }

        return $this->mailer;
    }

    /**
     * Get the job queue instance.
     */
    public function job_queue(): JobQueue {
        if ( ! isset( $this->job_queue ) ) {
            $this->setQueueAdapter();
        }

        return $this->job_queue;
    }

    /**
     * Get the background job worker instance.
     */
    public function queue_worker(): QueueWorker {
        if ( ! isset( $this->queue_worker ) ) {
            $this->setQueueAdapter();
        }

        return $this->queue_worker;
    }

    /**
     * Get the environment provider instance
     */
    public static function envProvider() : static {
        return static::$envProvider;
    }

    /**
     * {@inheritDoc}
     * 
     * Intentionally lazy loaded.
     */
    public function scheduler(): Scheduler {
        return Scheduler::instance( $this->settings() );
    }

    /**
     * Get the current request object.
     * 
     * @return Request
     */
    public function request() : Request {
        return $this->request;
    }

    /**
     * {@inheritDoc}
     * 
     * Intentionally lazy loaded.
     */
    public function httpClient() : HttpClient {
        if ( ! isset( $this->httpClient ) ) {
            $this->httpClient = new HttpClient;
        }

        return $this->httpClient;
    }

    /**
    * Get the monetization provider registry.
    * 
    * Intentionally lazy loaded.
    */
    public function monetizationRegistry() : MonetizationRegistry {
        if ( ! isset( $this->monetizationRegistry ) ) {
            $this->monetizationRegistry = MonetizationRegistry::instance( $this->settings() );
        }

        return $this->monetizationRegistry;
    }

    /**
     * Get the email provider registry.
     */
    public function emailProviders() : EmailProvidersRegistry {
        if ( ! isset( $this->emailProviders ) ) {
            $this->emailProviders = EmailProvidersRegistry::instance( $this->settings() );
        }

        return $this->emailProviders;
    }

    /**
     * {@inheritdoc}
     */
    public function templateLocator() : TemplateLocator {
        if ( ! isset( $this->templateLocator ) ) {
            $this->templateLocator  = new TemplateLocator();
            $discovery              = new TemplateDiscovery( $this->templateLocator );

            // Core templates auto-discovered at priority 0.
            $discovery->discover( 'core', SMLISER_RUNTIME_DIR . '/templates/', 0 );
        }

        return $this->templateLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function adminDashboardRegistry() : AdminDashboardRegistry {
        if ( ! isset( $this->adminDashboardRegistry ) ) {
            $this->adminDashboardRegistry = new AdminDashboardRegistry();
        }
        
        return $this->adminDashboardRegistry;
    }

    /**
     * Get the client dashboard registry
     */
    public function clientDashboardRegistry() : ClientDashboardRegistry {
        if ( ! isset( $this->clientDashboardRegistry ) ) {
            $this->clientDashboardRegistry  = new ClientDashboardRegistry;
        }

        return $this->clientDashboardRegistry;
    }

    /**
     * Get the authentication template registry
     */
    public function authTemplateRegistry() : AuthTemplateRegistry {
        if ( ! isset( $this->authTemplateRegistry ) ) {
            $this->authTemplateRegistry  = new AuthTemplateRegistry;
        }

        return $this->authTemplateRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function get_runtime_config() : RuntimeConfig {
        return $this->runtime;
    }

    /**
     * Explicitly set the value of static::$envProvider to the current
     * provider instance. This ensure that both the current instance calling global functions
     * and the bootstrap instantiating the environment provider references the same object.
     * 
     * @example static::$envProvider = $this;
     */
    abstract protected function bind_instance() : void;
}