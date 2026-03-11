<?php
/**
 * Cache adapter collection class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

use SmartLicenseServer\Cache\Adapters\ApcuCacheAdapter;
use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Cache\Adapters\InMemoryCacheAdapter;
use SmartLicenseServer\Cache\Adapters\MemcachedCacheAdapter;
use SmartLicenseServer\Cache\Adapters\RedisCacheAdapter;
use SmartLicenseServer\Cache\Adapters\SqliteCacheAdapter;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\SettingsAPI\Settings;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Cache adapters manager file.
 */
class CacheAdapterCollection {
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * Registered providers keyed by adapter ID.
     *
     * @var array<string, CacheAdapterInterface>
     */
    protected array $adapters = [];

    /**
     * In-memory cache for persisted adapter options.
     *
     * Keyed by provider ID. Busted whenever update_option() is called.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $settings_store = [];

    /**
     * The settings API used to set/get settings from storage.
     * 
     * @var Settings $settings
     */
    protected Settings $settings;

    const DEFAULT_PROVIDER_KEY      = 'cache_default_adapter';
    const SETTINGS_KEY              = 'cache_adapter_options';

    /**
     * Private constructor — use instance() or create().
     */
    private function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /*
    |------------------
    | SINGLETON ACCESS
    |------------------
    */

    /**
     * Return the singleton instance, creating and loading it if needed.
     *
     * @param Settings|null $settings The storage API, required on first initialization.
     * @return static
     */
    public static function instance( ?Settings $settings = null ): static {
        if ( static::$instance === null ) {
            if ( ! $settings ) {
                throw new EnvironmentBootstrapException( 'mis_configuration', 'Cache adapter collection API requires a storage API' );
            }

            static::$instance = new static( $settings );
            static::$instance->load_core_providers();
        }

        return static::$instance;
    }

    /*
    |---------------------
    | PROVIDER MANAGEMENT
    |---------------------
    */

    /**
     * Register a cache adapter.
     *
     * Replaces any previously registered provider with the same ID.
     *
     * @param CacheAdapterInterface $provider
     * @return void
     */
    public function register_provider( CacheAdapterInterface $provider ): void {
        $this->adapters[ $provider->get_id() ] = $provider;
    }

    /**
     * Unregister a provider by its ID.
     *
     * @param string $provider_id
     * @return bool True if the provider was found and removed, false otherwise.
     */
    public function unregister_provider( string $provider_id ): bool {
        if ( ! isset( $this->adapters[ $provider_id ] ) ) {
            return false;
        }

        unset( $this->adapters[ $provider_id ] );
        return true;
    }

    /**
     * Check whether a provider is registered.
     *
     * @param string $provider_id
     * @return bool
     */
    public function has_provider( string $provider_id ): bool {
        return isset( $this->adapters[ $provider_id ] );
    }

    /**
     * Return a registered provider by ID without settings applied.
     *
     * @param string $provider_id
     * @return CacheAdapterInterface|null
     */
    public function get_provider( string $provider_id ): ?CacheAdapterInterface {
        return $this->adapters[ $provider_id ] ?? null;
    }

    /**
     * Return all registered providers.
     *
     * @param bool $assoc Whether to key the array by provider ID.
     * @return array<string, CacheAdapterInterface>|CacheAdapterInterface[]
     */
    public function get_providers( bool $assoc = true ): array {
        return $assoc ? $this->adapters : array_values( $this->adapters );
    }

    /**
     * Return a provider with its persisted settings applied.
     *
     * If no provider ID is given, the default provider is used.
     *
     * A clone is returned so the shared registered instance is never
     * mutated — concurrent calls with different settings stay isolated.
     *
     * @param string|null $provider_id
     * @return CacheAdapterInterface|null
     * @throws InvalidArgumentException If settings validation fails.
     */
    public function get_provider_with_settings( ?string $provider_id = null ): ?CacheAdapterInterface {
        $provider_id = $provider_id ?? static::get_default_provider_id();

        if ( $provider_id === null ) {
            return null;
        }

        $provider = $this->get_provider( $provider_id );

        if ( $provider === null ) {
            return null;
        }

        // Clone before applying settings to protect the shared registered instance.
        $provider = clone $provider;

        $saved_settings = [];
        foreach ( $provider->get_settings_schema() as $key => $_ ) {
            $saved_settings[ $key ] = static::get_option( $provider_id, $key );
        }

        $provider->set_settings( $saved_settings );

        return $provider;
    }

    /*
    |------------------
    | DEFAULT PROVIDER
    |------------------
    */

    /**
     * Return the default provider ID, or null if none is set.
     *
     * @return string|null
     */
    public static function get_default_provider_id(): string {
        return (string) static::instance()->settings->get( static::DEFAULT_PROVIDER_KEY, 'php_mail', true );
    }

    /**
     * Set the default cache adapter.
     *
     * @param string $provider_id
     * @return bool True if saved successfully.
     * @throws InvalidArgumentException If the provider is not registered.
     */
    public static function set_default_provider( string $provider_id ): bool {
        if ( ! static::instance()->has_provider( $provider_id ) ) {
            throw new EnvironmentBootstrapException(
                'mis_configuration',
                "CacheAdapterCollection: cannot set default — adapter '{$provider_id}' is not registered."
            );
        }

        return static::instance()->settings->set( static::DEFAULT_PROVIDER_KEY, $provider_id, true );
    }

    /*
    |---------------------
    | OPTIONS PERSISTENCE
    |---------------------
    */

    /**
     * Return a persisted option value for a provider.
     *
     * Results are cached in memory for the duration of the request.
     * The cache is keyed by provider ID and busted on write.
     *
     * @param string $provider_id
     * @param string $option_name
     * @return mixed
     */
    public static function get_option( string $provider_id, string $option_name ): mixed {
        if ( ! isset( static::$settings_store[ $provider_id ] ) ) {
            $all_options = static::instance()->settings->get( static::SETTINGS_KEY, [], true );
            static::$settings_store[ $provider_id ] = $all_options[ $provider_id ] ?? [];
        }

        return static::$settings_store[ $provider_id ][ $option_name ] ?? '';
    }

    /**
     * Persist an option value for a provider and bust the cache.
     *
     * @param string $provider_id
     * @param string $option_name
     * @param mixed  $value
     * @return bool
     */
    public static function update_option( string $provider_id, string $option_name, mixed $value ): bool {
        $settings       = static::instance()->settings;
        $all_options    = $settings->get( static::SETTINGS_KEY, [], true );

        if ( ! isset( $all_options[ $provider_id ] ) || ! is_array( $all_options[ $provider_id ] ) ) {
            $all_options[ $provider_id ] = [];
        }

        $all_options[ $provider_id ][ $option_name ] = $value;

        $saved = $settings->set( static::SETTINGS_KEY, $all_options, true );

        if ( $saved ) {
            // Bust the cache for this provider so the next get_option() reads fresh data.
            unset( static::$settings_store[ $provider_id ] );
        }

        return $saved;
    }

    /**
     * Persist all settings for a provider at once and bust the cache.
     *
     * More efficient than calling update_option() per key when saving
     * an entire settings form submission.
     *
     * @param string               $provider_id
     * @param array<string, mixed> $settings
     * @return bool
     */
    public static function update_provider_settings( string $provider_id, array $settings ): bool {
        $storage     = static::instance()->settings;
        $all_options = $storage->get( static::SETTINGS_KEY, [], true );
        $all_options[ $provider_id ] = $settings;
        $saved = $storage->set( static::SETTINGS_KEY, $all_options, true );

        if ( $saved ) {
            unset( static::$settings_store[ $provider_id ] );
        }

        return $saved;
    }

    /*
    |----------------
    | CORE PROVIDERS
    |----------------
    */

    /**
     * Register all built-in providers.
     *
     * Called once by instance() on first access. Third-party providers
     * can be registered afterward via register_provider().
     *
     * @return void
     */
    protected function load_core_providers(): void {
        $core_providers = [
            new ApcuCacheAdapter,
            new RedisCacheAdapter,
            new MemcachedCacheAdapter,
            new SqliteCacheAdapter,
            new InMemoryCacheAdapter,
        ];

        foreach ( $core_providers as $provider ) {
            $this->register_provider( $provider );
        }
    }
}