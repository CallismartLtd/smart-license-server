<?php
/**
 * Cache manager class file.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Cache manager singleton.
 *
 * Provides a unified cache API for Smart License Server.
 *
 *
 * Methods are proxied to the underlying adapter:
 *
 * @method mixed get(string $key) Retrieve a cached value by key.
 * @method bool set(string $key, mixed $value, int $ttl = 0) Store a value in the cache.
 * @method bool delete(string $key) Delete a cache entry by key.
 * @method bool clear() Clear all cache entries.
 * @method CacheStats get_stats() Return runtime statistics for this cache adapter.
 * @method bool is_supported() Tells whether the adapter can run in the host environment.
 * @method bool test( array<string, mixed> $settings ) Test whether the adapter can connect and operate with the supplied settings.
 * @method void set_settings( array<string, mixed> $settings )Set adapter configuration.
 * @method array get_settings_schema() Return required configuration fields.
 * @method string get_name() Get the underlining cache adapter name.
 * @method string get_id() Get the underlining cache adapter ID.
 * @method bool has( string $key ) Check if a cache entry exists.
 * @method
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
    public function __construct( CacheAdapterInterface $adapter ) {
        $this->adapter = $adapter;
    }

    /**
     * Default cache ttl
     * 
     * @return int
     */
    public function default_ttl() : int {
        return (int) max( 0, smliser_settings()->get( 'default_cache_ttl', 0 ) );
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
