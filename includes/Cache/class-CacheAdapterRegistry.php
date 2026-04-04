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
use SmartLicenseServer\Cache\Adapters\RuntimeCacheAdapter;
use SmartLicenseServer\Cache\Adapters\MemcachedCacheAdapter;
use SmartLicenseServer\Cache\Adapters\RedisCacheAdapter;
use SmartLicenseServer\Cache\Adapters\SQLiteCacheAdapter;
use SmartLicenseServer\Contracts\AbstractRegistry;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\SettingsAPI\Settings;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Cache adapters manager file.
 */
class CacheAdapterRegistry extends AbstractRegistry {
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * In-memory cache for persisted adapter options.
     *
     * Keyed by adapter ID. Busted whenever update_option() is called.
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

    const DEFAULT_ADAPTER_KEY   = 'cache_default_adapter';
    const SETTINGS_KEY          = 'cache_adapter_options';

    /**
     * Private constructor — use instance().
     */
    private function __construct( Settings $settings ) {
        $this->settings = $settings;
        $this->load_core();
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
                throw new EnvironmentBootstrapException( 'misconfiguration', 'Cache adapter collection API requires a storage API' );
            }

            static::$instance = new static( $settings );
        }

        return static::$instance;
    }

    /**
     * Return a registered adapter by ID with settings applied.
     *
     * @param string $adapter_id The adapter ID(default to the adapter in settings).
     * @return CacheAdapterInterface|null
     */
    public function get_adapter( ?string $adapter_id = null ) : ?CacheAdapterInterface {
        $adapter_id     = $adapter_id ?? $this->get_default_adapter_id();
        $class_string   = $this->get( $adapter_id );
        $adapter        = null;

        if ( $class_string ) {
            /** @var CacheAdapterInterface $adapter */
            $adapter    = new $class_string;
            $settings   = [];

            foreach ( $adapter->get_settings_schema() as $name => $_ ) {
                $settings[$name] = $this->get_option( $adapter::get_id(), $name );
            }

            $adapter->set_settings( $settings );
        }

        return $adapter;
    }

    /*
    |------------------
    | DEFAULT ADAPTER
    |------------------
    */

    /**
     * Return the default adapter ID, or null if none is set.
     *
     * @return string|null
     */
    public static function get_default_adapter_id(): string {
        return (string) static::instance()->settings->get( static::DEFAULT_ADAPTER_KEY, 'runtime', true );
    }

    /**
     * Set the default cache adapter.
     *
     * @param string $adapter_id
     * @return bool True if saved successfully.
     * @throws InvalidArgumentException If the adapter is not registered.
     */
    public static function set_default_adapter( string $adapter_id ): bool {
        if ( ! static::instance()->has( $adapter_id ) ) {
            throw new EnvironmentBootstrapException(
                'misconfiguration',
                "CacheAdapterRegistry: cannot set default — adapter '{$adapter_id}' is not registered."
            );
        }

        return static::instance()->settings->set( static::DEFAULT_ADAPTER_KEY, $adapter_id, true );
    }

    /*
    |---------------------
    | OPTIONS PERSISTENCE
    |---------------------
    */

    /**
     * Return a persisted option value for a adapter.
     *
     * Results are cached in memory for the duration of the request.
     * The cache is keyed by adapter ID and busted on write.
     *
     * @param string $adapter_id
     * @param string $option_name
     * @return mixed
     */
    public static function get_option( string $adapter_id, string $option_name ): mixed {
        if ( ! isset( static::$settings_store[ $adapter_id ] ) ) {
            $all_options = static::instance()->settings->get( static::SETTINGS_KEY, [], true );
            static::$settings_store[ $adapter_id ] = $all_options[ $adapter_id ] ?? [];
        }

        return static::$settings_store[ $adapter_id ][ $option_name ] ?? '';
    }

    /**
     * Persist an option value for a adapter and bust the cache.
     *
     * @param string $adapter_id
     * @param string $option_name
     * @param mixed  $value
     * @return bool
     */
    public static function update_option( string $adapter_id, string $option_name, mixed $value ): bool {
        $settings       = static::instance()->settings;
        $all_options    = $settings->get( static::SETTINGS_KEY, [], true );

        if ( ! isset( $all_options[ $adapter_id ] ) || ! is_array( $all_options[ $adapter_id ] ) ) {
            $all_options[ $adapter_id ] = [];
        }

        $all_options[ $adapter_id ][ $option_name ] = $value;

        $saved = $settings->set( static::SETTINGS_KEY, $all_options, true );

        if ( $saved ) {
            // Bust the cache for this adapter so the next get_option() reads fresh data.
            unset( static::$settings_store[ $adapter_id ] );
        }

        return $saved;
    }

    /**
     * Persist all settings for a adapter at once and bust the cache.
     *
     * More efficient than calling update_option() per key when saving
     * an entire settings form submission.
     *
     * @param string               $adapter_id
     * @param array<string, mixed> $settings
     * @return bool
     */
    public static function update_adapter_settings( string $adapter_id, array $settings ): bool {
        $storage     = static::instance()->settings;
        $all_options = (array) $storage->get( static::SETTINGS_KEY, [], true );
        
        $all_options[ $adapter_id ] = $settings;
        
        $saved = $storage->set( static::SETTINGS_KEY, $all_options, true );

        if ( $saved ) {
            unset( static::$settings_store[ $adapter_id ] );
        }

        return $saved;
    }

    /*
    |----------------
    | CORE ADAPTERS
    |----------------
    */

    /**
     * Register all built-in adapters.
     *
     * Called once by instance() on first access. Third-party adapters
     * can be registered afterward via register_adapter().
     *
     * @return void
     */
    protected function load_core(): void {
        $core_adapters = [
            RuntimeCacheAdapter::class,
            ApcuCacheAdapter::class,
            MemcachedCacheAdapter::class,
            RedisCacheAdapter::class,
            SQLiteCacheAdapter::class,
        ];

        foreach ( $core_adapters as $adapter ) {
            $this->add( $adapter );
        }
    }
}