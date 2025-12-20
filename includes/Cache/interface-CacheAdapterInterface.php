<?php
/**
 * Interface for cache adapters.
 *
 * Ensures a unified API for caching across different environments
 * (WordPress, Laravel, and pure PHP memory-based cache).
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

defined( 'SMLISER_ABSPATH' ) || exit;

interface CacheAdapterInterface {

    /**
     * Retrieve a cached value by key.
     *
     * @param string $key Unique cache key.
     * @return mixed|false Returns the cached value or false if not found.
     */
    public function get( string $key ): mixed;

    /**
     * Store a value in the cache.
     *
     * @param string $key   Unique cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time-to-live in seconds. 0 = forever.
     * @return bool True on success, false on failure.
     */
    public function set( string $key, $value, int $ttl = 0 ): bool;

    /**
     * Delete a cache entry by key.
     *
     * @param string $key Unique cache key.
     * @return bool True on success, false on failure.
     */
    public function delete( string $key ): bool;

    /**
     * Check if a cache entry exists.
     *
     * @param string $key Unique cache key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has( string $key ): bool;

    /**
     * Clear the entire cache.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool;
}
