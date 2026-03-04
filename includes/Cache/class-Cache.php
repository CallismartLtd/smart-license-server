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
    public function __construct( CacheAdapterInterface $adapter ) {
        $this->adapter = $adapter;
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
