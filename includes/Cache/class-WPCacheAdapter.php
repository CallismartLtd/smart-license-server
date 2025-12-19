<?php
/**
 * WordPress cache adapte class filer.
 *
 * @
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * WordPress cache adapter.
 *
 * Integrates with WordPress caching functions (object cache / plugins).
 */
class WPCacheAdapter implements CacheAdapterInterface {

    /**
     * Cache group
     */
    protected string $group = 'smliser';
    /**
     * Retrieve a value from WordPress cache.
     *
     * @param string $key Cache key.
     * @return mixed|null Cached value or null if not found.
     */
    public function get( string $key ) {
        $value = wp_cache_get( $key, $this->group );
        return $value !== false ? $value : null;
    }

    /**
     * Store a value in WordPress cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to cache.
     * @param int    $ttl   Time-to-live in seconds (ignored in WP object cache).
     * @return bool True on success.
     */
    public function set( string $key, $value, int $ttl = 0 ): bool {
        return wp_cache_set( $key, $value, $this->group, $ttl );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool True if deleted.
     */
    public function delete( string $key ): bool {
        return wp_cache_delete( $key, $this->group );
    }

    /**
     * Clear all cache.
     *
     * @return bool True on success.
     */
    public function clear(): bool {
        return \wp_cache_flush_group( $this->group );
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $key Unique cache key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has( string $key ): bool {
        return false !== wp_cache_get( $key, $this->group );
    }
}
