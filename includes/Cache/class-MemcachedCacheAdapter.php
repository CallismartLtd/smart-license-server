<?php
/**
 * Memcached cache adapter.
 *
 * Provides a Memcached-backed implementation of the CacheAdapterInterface.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

use Memcached;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Memcached cache adapter.
 */
class MemcachedCacheAdapter implements CacheAdapterInterface {

    /**
     * Memcached client instance.
     *
     * @var Memcached
     */
    protected Memcached $memcached;

    /**
     * Cache key prefix.
     *
     * @var string
     */
    protected string $prefix = '';

    /**
     * Constructor.
     *
     * @param Memcached $memcached Memcached client instance.
     * @param string    $prefix    Optional key prefix.
     */
    public function __construct( Memcached $memcached, string $prefix = '' ) {
        $this->memcached = $memcached;
        $this->prefix    = $prefix;
    }

    /**
     * Build the cache key.
     *
     * @param string $key Cache key.
     * @return string
     */
    protected function key( string $key ): string {
        return $this->prefix . $key;
    }

    /**
     * Retrieve a cached value.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    public function get( string $key ): mixed {
        return $this->memcached->get( $this->key( $key ) );
    }

    /**
     * Store a value in cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time-to-live in seconds. 0 = forever.
     * @return bool
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        return $this->memcached->set( $this->key( $key ), $value, $ttl );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete( string $key ): bool {
        return $this->memcached->delete( $this->key( $key ) );
    }

    /**
     * Determine whether a key exists.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has( string $key ): bool {
        $this->memcached->get( $this->key( $key ) );

        return Memcached::RES_NOTFOUND !== $this->memcached->getResultCode();
    }

    /**
     * Clear the cache.
     *
     * @return bool
     */
    public function clear(): bool {
        return $this->memcached->flush();
    }
}