<?php
/**
 * License Server environment configuration file
 * 
 * @author Callistus
 * @package SmartLicenseServer
 * @since 0.2.0
 */

namespace SmartLicenseServer;

use mysqli;
use PDO;
use SmartLicenseServer\Background\Queue\Adapters\DatabaseJobStorageAdapter;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Background\Workers\QueueWorker;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Cache\CacheAdapterRegistry;
use SmartLicenseServer\Core\DBConfigDTO;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Adapters\DatabaseAdapterInterface;
use SmartLicenseServer\Database\Adapters\MysqliAdapter;
use SmartLicenseServer\Database\Adapters\PdoAdapter;
use SmartLicenseServer\Database\Adapters\SqliteAdapter;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\Mailer;
use SmartLicenseServer\Environments\EnvironmentProviderInterface;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\FileSystem\Adapters\DirectFileSystem;
use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Http\HttpClient;
use SmartLicenseServer\Monetization\MonetizationRegistry;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\SettingsAPI\Providers\Options;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;
use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\ClientDashboard\AuthTemplateRegistry;
use SmartLicenseServer\ClientDashboard\ClientDashboardRegistry;
use SmartLicenseServer\Database\Adapters\PostgresAdapter;
use SmartLicenseServer\Events\Bootstrap\EnvironmentBooted;
use SmartLicenseServer\Events\Bootstrap\EnvironmentReady;
use SmartLicenseServer\Events\EventServiceProvider;
use SmartLicenseServer\Security\Context\IdentityProviderInterface;
use SmartLicenseServer\Templates\TemplateDiscovery;
use SmartLicenseServer\Templates\TemplateLocator;
use SQLite3;

/**
 * Abstract environment bootstrap class for Smart License Server.
 * 
 * This class serves as the foundation for initializing the application environment
 * in a runtime-agnostic way. It is responsible for:
 * 
 * - Parsing and validating configuration provided by the environment provider.
 * - Declaring global constants (paths, table names, keys, etc.).
 * - Instantiating and wiring core services and adapters, including:
 *   - Database, Cache, Filesystem, and Settings APIs
 *   - REST API provider
 *   - Mailing service
 *   - Job queue and background worker
 *   - HTTP client
 *   - Scheduler (lazy-loaded)
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
     * Environment configuration data.
     * 
     * @var array $env
     */
    protected array $env = [
        'db_prefix'             => '',
        'absolute_path'         => '',
        'secret'                => null,
        'salt'                  => null,
        'repo_path'             => '',
        'uploads_dir'           => '',
        'filesystem_adapter'    => null,
        'cache_adapter'         => null,
        'settings_provider'     => null,
        'database_adapter'      => null,
        'rest_api_provider'     => null,
        'admin_menu_config'     => null,
        'identity_provider'     => null,
        'debug_mode'            => false
    ];

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
     *      db_prefix: string,
     *      absolute_path: string,
     *      secret: string,
     *      salt: string,
     *      repo_path: string, 
     *      uploads_dir: string, 
     *      filesystem_adapter?: FileSystemAdapterInterface, 
     *      cache_adapter?: CacheAdapterInterface,
     *      settings_provider?: SettingsStorageInterface,
     *      database_adapter?: DatabaseAdapterInterface,
     *      rest_api_provider: RESTProviderInterface,
     *      admin_menu_config?: AdminDashboardRegistry, 
     *      identity_provider: IdentityProviderInterface,
     *      debug_mode: bool
     * } $config The environment configuration options.
     * @throws EnvironmentBootstrapException If required configuration is missing or invalid.
     */
    final protected function setup( array $config ) {
        $this->parse_config( $config );
        $this->declareGlobalConstants();        
        $this->setProps();
        
        smliser_dispatch( new EnvironmentBooted );
    }

    /*
    |-----------------------
    | HELPERS
    |-----------------------
    */

    /**
     * Parse environment configuration file to ensure that required variables are set.
     * 
     * @param array $env_config The configuration options from the environment adapter.
     */
    private function parse_config( $env_config ) : void {
        $default_config = $this->env;

        $parsed_config  = array_intersect_key( array_merge( $default_config, $env_config ), $default_config );
        $missing_config = [];

        $required_keys = ['db_prefix','absolute_path','secret', 'salt','repo_path',
            'uploads_dir', 'rest_api_provider', 'identity_provider'
        ];

        foreach ( $parsed_config as $key => $value ) {
            if ( in_array( $key, $required_keys, true ) && $value === null ) {
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

        $this->env  = $parsed_config;
    }

    /**
     * Compute a safe worker memory ceiling in megabytes.
     *
     * Reads memory_limit from the PHP ini, converts to MB, then applies
     * an 80% safety factor so the worker exits before PHP itself runs out
     * of memory. Falls back to 64MB when the ini value is unparseable or
     * unlimited (-1) to keep the worker conservative on unknown environments.
     *
     * @return int Memory limit in MB to pass to QueueWorker.
     */
    private function safe_worker_memory_limit_mb(): int {
        $raw = ini_get( 'memory_limit' );
 
        // Parse shorthand notation (128M, 256M, 1G etc.) to bytes.
        $bytes = $this->parse_ini_bytes( (string) $raw );
 
        // -1 means unlimited — be conservative on unknown environments.
        if ( $bytes <= 0 ) {
            return 64;
        }
 
        $mb = (int) ( $bytes / 1024 / 1024 );
 
        // Apply 80% safety factor and floor at 32MB.
        return max( 32, (int) ( $mb * 0.8 ) );
    }

    /**
     * Convert a PHP ini memory string to bytes.
     *
     * Handles shorthand suffixes: K (kilobytes), M (megabytes), G (gigabytes).
     * Returns -1 for unlimited (-1), 0 for unparseable values.
     *
     * @param string $val Raw ini value e.g. '128M', '1G', '-1'.
     * @return int Byte equivalent, or -1 for unlimited, or 0 on failure.
     */
    private function parse_ini_bytes( string $val ): int {
        $val = trim( $val );
 
        if ( $val === '-1' ) {
            return -1;
        }
 
        if ( ! is_numeric( $val[0] ?? '' ) ) {
            return 0;
        }
 
        $suffix = strtolower( substr( $val, -1 ) );
        $num    = (int) $val;
 
        return match ( $suffix ) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => $num,
        };
    }

    /**
     * Sets up the class properties.
     */
    private function setProps() : void {
        $prop_map   = [
            'filesystem_adapter'    => 'filesystemAdapter',
            'cache_adapter'         => 'cacheAdapter',
            'settings_provider'     => 'settingsStorage',
            // 'database_adapter'      => 'dbadapter',
            'rest_api_provider'     => 'restProvider',
            'http_client'           => 'httpClient',
            'admin_menu_config'     => 'adminDashboardRegistry',
            'identity_provider'     => 'identityProvider',
        ];

        foreach ( $prop_map as $env_k => $prop_k ) {
            if ( isset( $this->{$prop_k} ) ) {
                // Preserve injected adapter if already set.
                continue;
            }

            if ( ! isset( $this->env[$env_k] ) ) {
                continue;
            }

            if ( ! property_exists( $this, $prop_k ) ) {
                throw new EnvironmentBootstrapException(
                    'unsupported_config',
                    sprintf( 'The provided configuration "%s" is not supported.', $prop_k )
                );
            }
            
            $this->{$prop_k}    = $this->env[$env_k];
        }

        EventServiceProvider::instance()->boot();
        smliser_dispatch( new \SmartLicenseServer\Events\Bootstrap\EnvironmentBooting() );

        // Auto provisions.

        if ( ! isset( $this->database ) ) {
            $this->setGlobalDBAdapter();
        }

        if ( ! isset( $this->filesystem ) ) {
            $this->setGlobalFileSystemAdapter();
        }

        if ( ! isset( $this->settings ) ) {
            $this->setGlobalSettingsAdapter();
        }

        if ( ! isset( $this->cache ) ) {
            $this->setGlobalCacheAdapter();
        }

        if ( ! isset( $this->request ) ) {
            $this->request = new Request;
        }

        if ( ! isset( $this->job_queue ) || ! isset( $this->queue_worker ) ) {
            $this->setGlobalQueueAdapter();
        }

        smliser_dispatch( new EnvironmentReady );
    }

    /**
     * Declare global constants.
     */
    private function declareGlobalConstants() : void {
        /**
         * The application secret key.
         * 
         * @var string
         */
        define( 'SMLISER_SECRET', $this->env['secret'] );

        /**
         * The application salt used for encryption.
         * 
         * @var string
         */
        define( 'SMLISER_SALT', $this->env['salt'] );
        
        /**
         * Licenses database table name.
         *
         * Dynamically generated using the configured database prefix.
         *
         * @var string `smliser_licenses.`
         */
        define( 'SMLISER_LICENSE_TABLE', $this->db_prefix() . 'licenses' );

        /**
         * License metadata database table name.
         *
         * @var string `smliser_license_meta.`
         */
        define( 'SMLISER_LICENSE_META_TABLE', $this->db_prefix() . 'license_meta' );

        /**
         * Plugins database table name.
         *
         * @var string `smliser_plugins.`
         */
        define( 'SMLISER_PLUGINS_TABLE', $this->db_prefix() . 'plugins' );

        /**
         * Plugin metadata database table name.
         *
         * @var string `smliser_plugin_meta.`
         */
        define( 'SMLISER_PLUGINS_META_TABLE', $this->db_prefix() . 'plugin_meta' );

        /**
         * Themes database table name.
         *
         * @var string `smliser_themes.`
         */
        define( 'SMLISER_THEMES_TABLE', $this->db_prefix() . 'themes' );

        /**
         * Theme metadata database table name.
         *
         * @var string `smliser_theme_meta.`
         */
        define( 'SMLISER_THEMES_META_TABLE', $this->db_prefix() . 'theme_meta' );

        /**
         * Software database table name.
         *
         * @var string `smliser_software.`
         */
        define( 'SMLISER_SOFTWARE_TABLE', $this->db_prefix() . 'software' );

        /**
         * Software metadata database table name.
         *
         * @var string `smliser_software_meta.`
         */
        define( 'SMLISER_SOFTWARE_META_TABLE', $this->db_prefix() . 'software_meta' );

        /**
         * API credentials database table name.
         * 
         * @deprecated 0.2.0
         * @var string `smliser_api_creds.`
         */
        define( 'SMLISER_API_CRED_TABLE', $this->db_prefix() . 'api_creds' );

        /**
         * Item download token database table name.
         *
         * @var string `smliser_item_download_token.`
         */
        define( 'SMLISER_DOWNLOAD_TOKEN_TABLE', $this->db_prefix() . 'item_download_token' );

        /**
         * Application download token database table name.
         *
         * @var string `smliser_app_download_tokens.`
         */
        define( 'SMLISER_APP_DOWNLOAD_TOKEN_TABLE', $this->db_prefix() . 'app_download_tokens' );

        /**
         * Monetization records database table name.
         *
         * @var string `smliser_monetization.`
         */
        define( 'SMLISER_MONETIZATION_TABLE', $this->db_prefix() . 'monetization' );

        /**
         * Pricing tiers database table name.
         *
         * @var string `smliser_pricing_tiers.`
         */
        define( 'SMLISER_PRICING_TIER_TABLE', $this->db_prefix() . 'pricing_tiers' );

        /**
         * Bulk messages database table name.
         *
         * @var string `smliser_bulk_messages.`
         */
        define( 'SMLISER_BULK_MESSAGES_TABLE', $this->db_prefix() . 'bulk_messages' );

        /**
         * Bulk message to application mapping database table name.
         *
         * @var string `smliser_bulk_messages_apps.`
         */
        define( 'SMLISER_BULK_MESSAGES_APPS_TABLE', $this->db_prefix() . 'bulk_messages_apps' );

        /**
         * Plugin options database table name.
         *
         * @var string `smliser_options.`
         */
        define( 'SMLISER_OPTIONS_TABLE', $this->db_prefix() . 'options' );

        /**
         * Analytics event logs database table name.
         *
         * @var string `smliser_analytics_log.`
         */
        define( 'SMLISER_ANALYTICS_LOGS_TABLE', $this->db_prefix() . 'analytics_log' );

        /**
         * Daily analytics aggregation database table name.
         *
         * @var string `smliser_analytics_daily.`
         */
        define( 'SMLISER_ANALYTICS_DAILY_TABLE', $this->db_prefix() . 'analytics_daily' );

        /**
         * Resource owners database table name.
         *
         * @var string `smliser_resource_owners.`
         */
        define( 'SMLISER_OWNERS_TABLE', $this->db_prefix() . 'resource_owners' );

        /**
         * Internal users database table name.
         *
         * @var string `smliser_users.`
         */
        define( 'SMLISER_USERS_TABLE', $this->db_prefix() . 'users' );

        /**
         * Users options database table name.
         * 
         * @var string `smliser_user_options.`
         */
        define( 'SMLISER_USER_OPTIONS_TABLE', $this->db_prefix() . 'user_options' );

        /**
         * Service accounts database table name.
         *
         * @var string `smliser_service_accounts.`
         */
        define( 'SMLISER_SERVICE_ACCOUNTS_TABLE', $this->db_prefix() . 'service_accounts' );

        /**
         * Roles database table name.
         *
         * @var string `smliser_roles.`
         */
        define( 'SMLISER_ROLES_TABLE', $this->db_prefix() . 'roles' );

        /**
         * Roles database table name.
         *
         * @var string `smliser_role_caps.`
         */
        define( 'SMLISER_ROLE_CAPABILITIES_TABLE', $this->db_prefix() . 'role_caps' );

        /**
         * Roles to principals database table name.
         *
         * @var string `smliser_principal_roles.`
         */
        define( 'SMLISER_ROLE_ASSIGNMENT_TABLE', $this->db_prefix() . 'principal_roles' );

        /**
         * Organizations database table name.
         *
         * @var string `smliser_organizations.`
         */
        define( 'SMLISER_ORGANIZATIONS_TABLE', $this->db_prefix() . 'organizations' );

        /**
         * Organization members database table name.
         *
         * @var string `smliser_organization_members.`
         */
        define( 'SMLISER_ORGANIZATION_MEMBERS_TABLE', $this->db_prefix() . 'organization_members' );
        
        /**
         * Identity provider map database table name.
         *
         * @var string `smliser_identity_provider_lookup.`
         */
        define( 'SMLISER_IDENTITY_FEDERATION_TABLE', $this->db_prefix() . 'identity_provider_lookup' );

        /**
         * Background jobs queue table name.
         *
         * @var string `smliser_background_jobs`
         */
        define( 'SMLISER_BACKGROUND_JOBS_TABLE', $this->db_prefix() . 'background_jobs' );

        /**
         * Failed jobs archive table name.
         *
         * @var string `smliser_failed_jobs`
         */
        define( 'SMLISER_FAILED_JOBS_TABLE', $this->db_prefix() . 'failed_jobs' );

        /**
         * Absolute path to the Smart License Server repository root directory.
         *
         * This is the base directory where all hosted application files are stored.
         *
         * @var string
         */
        define( 'SMLISER_REPO_DIR', $this->env['repo_path'] . '/smliser-repo' );

        /**
         * Absolute path to the plugin repository directory.
         *
         * @var string
         */
        define( 'SMLISER_PLUGINS_REPO_DIR', SMLISER_REPO_DIR . '/plugins' );

        /**
         * Absolute path to the theme repository directory.
         *
         * @var string
         */
        define( 'SMLISER_THEMES_REPO_DIR', SMLISER_REPO_DIR . '/themes' );

        /**
         * Absolute path to the software repository directory.
         *
         * @var string
         */
        define( 'SMLISER_SOFTWARE_REPO_DIR', SMLISER_REPO_DIR . '/software' );

        /**
         * Absolute path to the cache directory.
         * 
         * @var string
         */
        define( 'SMLISER_CACHE_DIR', SMLISER_REPO_DIR . '/.cache' );

        /**
         * Absolute path to the trash directory.
         * 
         * @var string
         */
        define( 'SMLISER_TRASH_DIR', SMLISER_REPO_DIR . '/.trash' );

        /**
         * Absolute path to the tmp directory.
         * 
         * @var string
         */
        define( 'SMLISER_TMP_DIR', SMLISER_REPO_DIR . '/tmp' );

        /**
         * Absolute path to the uploads directory.
         * 
         * @var string
         */
        define( 'SMLISER_UPLOADS_DIR', $this->env['uploads_dir'] . '/smliser-uploads' );

        /**
         * Temporary file prefix
         */
        define( 'SMLISER_UPLOAD_TMP_PREFIX', 'smliser_tmp_' );

        /**
         * Default file permission.
         * 
         * @var int
         */
        define( 'SMLISER_FILE_PERMISSION', ( fileperms( SMLISER_ABSPATH . 'index.php' ) & 0777 | 0644 ) );

        /**
         * Default directory permission.
         * 
         * @var int
         */
        define( 'SMLISER_DIR_PERMISSION', ( fileperms( SMLISER_ABSPATH ) & 0777 | 0755 ) );

        if ( ! defined( 'APP_DEBUG' ) ) {
            /**
             * Debug mode flag.
             * 
             * @var bool
             */
            define( 'APP_DEBUG', (bool) $this->env['debug_mode'] );
        }

    }

    /**
     * Sets up the global database adapter
     */
    protected function setGlobalDBAdapter() : void {
        if ( ! isset( $this->dbadapter ) ) {
            if ( ! isset( $this->dbConfig ) ) {
                throw new EnvironmentBootstrapException( 'missing_db_config' );
            }

            $config = $this->dbConfig;

            /** @var array<class-string<DatabaseAdapterInterface>, bool> $adapters */
            $adapters   = [
                MysqliAdapter::class    => 'mysql' === $config->driver && class_exists( mysqli::class ),
                SqliteAdapter::class    => 'sqlite' === $config->driver && class_exists( SQLite3::class ),
                PostgresAdapter::class  => 'pgsql' === $config->driver && \extension_loaded( 'pgsql' ),
                PdoAdapter::class       => class_exists( PDO::class ) && in_array( $config->driver, PDO::getAvailableDrivers() ),
            ];

            foreach( $adapters as $adapter_class => $is_supported ) {
                if ( $is_supported ) {
                    $this->dbadapter = new $adapter_class( $config );
                    break;
                }
            }

            if ( ! isset( $this->dbadapter ) ) {
                throw new EnvironmentBootstrapException(
                    'no_db_adapter_found',
                    sprintf( 'No supported database adapter for "%s" driver.', $config->driver )
                    
                ); 
            }          
        }
        
        $this->database = new Database( $this->dbadapter );
    }

    /**
     * Sets up the global filesystem adapter
     */
    protected function setGlobalFileSystemAdapter() : void {

        if ( ! isset( $this->filesystemAdapter ) ) {
            $this->filesystemAdapter = new DirectFileSystem;
        }

        $this->filesystem    = new FileSystem( $this->filesystemAdapter );
    }

    /**
     * Sets up the global cache adapter
     */
    protected function setGlobalCacheAdapter() : void {

        if ( ! isset( $this->cacheAdapter ) ) {
            $this->cacheAdapter = CacheAdapterRegistry::instance( $this->settings )->get_adapter();
        }

        $this->cache    = new Cache( $this->cacheAdapter );

    }

    /**
     * Sets up the global settings adapter
     */
    protected function setGlobalSettingsAdapter() : void {
        if ( ! isset( $this->settingsStorage ) ) {
            $this->settingsStorage = new Options( $this->database );
        }

        $this->settings = new Settings( $this->settingsStorage );
    }

    /**
     * Sets up the global mailing service to use the default provider.
     */
    protected function setGlobalMailingAdapter() : void {
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
    protected function setGlobalQueueAdapter(): void {
        if ( ! isset( $this->job_queue ) ) {
            $this->job_queue = new JobQueue( new DatabaseJobStorageAdapter( $this->database ) );
        }
 
        if ( ! isset( $this->queue_worker ) ) {
            $this->queue_worker = new QueueWorker(
                $this->job_queue,
                memory_limit_mb: $this->safe_worker_memory_limit_mb(),
            );
        }
    }

    /*
    |-------------------------
    | ACCESSORS
    |-------------------------
    */

    /**
     * Get the database table prefix.
     * 
     * @return string
     */
    public function db_prefix() : string {
        return $this->env['db_prefix'];
    }

    /**
     * Get the namespace
     */
    public function rest_namespace() {
        return $this->restProvider->namespace();
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
        return $this->database;
    }

    /**
     * Get the filesystem abstraction instance.
     */
    public function filesystem() : FileSystem {
        return $this->filesystem;
    }

    /**
     * Get the cache instance
     */
    public function cache() : Cache {
        return $this->cache;
    }

    /**
     * Get the settings API instance.
     */
    public function settings() : Settings {
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
            $this->setGlobalMailingAdapter();
        }

        return $this->mailer;
    }

    /**
     * Get the job queue instance.
     */
    public function job_queue(): JobQueue {
        return $this->job_queue;
    }

    /**
     * Get the background job worker instance.
     */
    public function queue_worker(): QueueWorker {
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
        return Scheduler::instance( $this->settings );
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
            $this->monetizationRegistry = MonetizationRegistry::instance( $this->settings );
        }

        return $this->monetizationRegistry;
    }

    /**
     * Get the email provider registry.
     */
    public function emailProviders() : EmailProvidersRegistry {
        if ( ! isset( $this->emailProviders ) ) {
            $this->emailProviders = EmailProvidersRegistry::instance( $this->settings );
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
            $discovery->discover( 'core', SMLISER_PATH . '/templates/', 0 );
        }

        return $this->templateLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function adminDashboardRegistry() : AdminDashboardRegistry {
        if ( ! isset( $this->adminDashboardRegistry ) ) {
            $this->adminDashboardRegistry = new AdminDashboardRegistry;
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
     * Get the identity provider.
     */
    public function identityProvider() : IdentityProviderInterface {
        return $this->identityProvider;
    }
}

