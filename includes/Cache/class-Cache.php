<?php
/**
 * Cache manager class file.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Cache manager singleton.
 *
 * Provides a unified cache API for Smart License Server.
 *
 * Automatically detects the environment and selects the appropriate cache adapter.
 *
 * Methods are proxied to the underlying adapter:
 *
 * @method mixed get(string $key) Retrieve a cached value by key.
 * @method bool set(string $key, mixed $value, int $ttl = 0) Store a value in the cache.
 * @method bool delete(string $key) Delete a cache entry by key.
 * @method bool clear() Clear all cache entries.
 */
class Cache {

    /**
     * Singleton instance.
     *
     * @var Cache|null
     */
    protected static ?Cache $instance = null;

    /**
     * The active cache adapter instance.
     *
     * @var CacheAdapterInterface
     */
    protected CacheAdapterInterface $adapter;

    /**
     * Private constructor to enforce singleton.
     *
     * @param CacheAdapterInterface $adapter The cache adapter instance.
     */
    private function __construct( CacheAdapterInterface $adapter ) {
        $this->adapter = $adapter;
    }

    /**
     * Get the singleton instance.
     *
     * @return Cache
     */
    public static function instance(): Cache {
        if ( self::$instance === null ) {
            self::$instance = new self( static::detect_adapter() );
        }
        return self::$instance;
    }

    /**
     * Detect environment and select appropriate cache adapter.
     *
     * @return CacheAdapterInterface
     */
    protected static function detect_adapter(): CacheAdapterInterface {
        // APCu (fast native PHP cache) if available and enabled.
        // if ( extension_loaded( 'apcu' ) && ini_get( 'apc.enabled' ) ) {
        //     return new ApcuCacheAdapter();
        // }

        // WordPress.
        if ( defined( 'ABSPATH' ) && function_exists( 'wp_cache_init' ) ) {
            return new WPCacheAdapter();
        }

        // Laravel.
        if ( class_exists( 'Illuminate\Support\Facades\Cache' ) ) {
            return new LaravelCacheAdapter();
        }

        // Default to in-memory cache.
        return new InMemoryCacheAdapter();
    }


    /**
     * Get the underlying adapter instance.
     *
     * @return CacheAdapterInterface
     */
    public function get_adapter(): CacheAdapterInterface {
        return $this->adapter;
    }

    /**
     * Proxy calls to the adapter methods.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     *
     * @return mixed
     *
     * @throws \BadMethodCallException If the method does not exist in the adapter.
     */
    public function __call( string $method, array $args ) {
        if ( method_exists( $this->adapter, $method ) ) {
            return call_user_func_array( [ $this->adapter, $method ], $args );
        }

        throw new \BadMethodCallException(
            sprintf( 'Method %s::%s does not exist.', get_class( $this->adapter ), $method )
        );
    }
}
