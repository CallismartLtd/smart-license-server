<?php
/**
 * Interface for cache adapters.
 *
 * Ensures a unified API for caching across different environments
 * (WordPress, Laravel, and pure PHP memory-based cache).
 *
 * @author Callistus Nwachukwu
 * @since 0.2.0
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use SmartLicenseServer\Cache\CacheStats;
use SmartLicenseServer\Contracts\ServiceProviderInterface;

interface CacheAdapterInterface extends ServiceProviderInterface {

    /**
     * Retrieve a cached value by key.
     *
     * @param string $key Unique cache key.
     * @return mixed Returns the cached value or false if not found.
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
    public function set( string $key, mixed $value, int $ttl = 0 ): bool;

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

    /**
    |--------------------------------------------------------------------------
    | ATOMIC OPERATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Atomically read, modify, and rewrite a cached value via a callback.
     *
     * The callable receives the current cached value (or $default if missing/expired)
     * and returns the new value to store. If the callback returns false, the write is aborted.
     *
     * @param string                        $key      Unique cache key.
     * @param callable(mixed): mixed        $callback Receives ($currentValue), returns updated value.
     * @param int                           $ttl      Time-to-live in seconds for updated entry.
     * @param mixed                         $default  Fallback value passed to callback if key does not exist.
     * @return mixed Returns the updated value on success, or false on failure.
     */
    public function modify( string $key, callable $callback, int $ttl = 0, mixed $default = null ): mixed;

    /**
     * Atomically increment a numeric cache value.
     *
     * @param string $key   Unique cache key.
     * @param int    $offset Amount to increment by.
     * @param int    $initial Default value if key does not exist.
     * @param int    $ttl   Time-to-live in seconds if item is created.
     * @return int|false New integer value on success, false on failure.
     */
    public function increment( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ): int|bool;

    /**
     * Atomically decrement a numeric cache value.
     *
     * @param string $key   Unique cache key.
     * @param int    $offset Amount to decrement by.
     * @param int    $initial Default value if key does not exist.
     * @param int    $ttl   Time-to-live in seconds if item is created.
     * @return int|false New integer value on success, false on failure.
     */
    public function decrement( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ): int|bool;

    /**
    |--------------------------------------------------------------------------
    | CONFIGURATION & SUPPORT
    |--------------------------------------------------------------------------
    */

    /**
     * Return required configuration fields.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_settings_schema(): array;

    /**
     * Set adapter configuration.
     *
     * @param array<string, mixed> $settings
     * @return void
     */
    public function set_settings( array $settings ): void;

    /**
     * Tells whether the adapter can run in the host environment.
     * 
     * @return bool
     */
    public function is_supported(): bool;

    /**
     * Tells whether the cache is active.
     * 
     * @return bool
     */
    public function is_active(): bool;

    /**
    |--------------------------------------------------------------------------
    | DIAGNOSTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Return runtime statistics for this cache adapter.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats;

    /**
     * Test whether the adapter can connect and operate with the supplied settings.
     *
     * @param array<string, mixed> $settings Settings to test.
     * @return bool
     */
    public function test( array $settings ): bool;
}