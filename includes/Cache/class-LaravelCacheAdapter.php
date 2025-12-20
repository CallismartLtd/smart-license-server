<?php
/**
 * Laravel cache adapter class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

use Illuminate\Support\Facades\Cache as LaravelCache;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Laravel cache adapter.
 *
 * Integrates with Laravel cache system using the Cache facade.
 */
class LaravelCacheAdapter implements CacheAdapterInterface {

    /**
     * Retrieve a value from Laravel cache.
     *
     * @param string $key Cache key.
     * @return mixed|false Cached value or false if not found.
     */
    public function get( string $key ): mixed {
        return LaravelCache::get( $key, false );
    }

    /**
     * Store a value in Laravel cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to cache.
     * @param int    $ttl   Time-to-live in seconds.
     * @return bool True on success.
     */
    public function set( string $key, $value, int $ttl = 0 ): bool {
        if ( $ttl > 0 ) {
            return LaravelCache::put( $key, $value, $ttl );
        }
        return LaravelCache::forever( $key, $value );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool True on success.
     */
    public function delete( string $key ): bool {
        return LaravelCache::forget( $key );
    }

    /**
     * Clear all cache.
     *
     * @return bool True on success.
     */
    public function clear(): bool {
        return LaravelCache::flush();
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $key Unique cache key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has( string $key ): bool {
        return LaravelCache::has( $key );
    }
}
