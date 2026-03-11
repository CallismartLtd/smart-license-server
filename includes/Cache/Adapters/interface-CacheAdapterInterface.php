<?php
/**
 * Interface for cache adapters.
 *
 * Ensures a unified API for caching across different environments
 * (WordPress, Laravel, and pure PHP memory-based cache).
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

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

    /**
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    /**
     * Get the adapter ID
     * 
     * @return string
     */
    public function get_id() : string;

    /**
     * Get provider display name.
     *
     * Example: "Redis Cache", "Memcached", "APCu Cache".
     *
     * @return string
     */
    public function get_name() : string;

    /**
     * Return required configuration fields.
     *
     * This allows the system to dynamically build a settings UI.
     *
     * Example return structure:
     * [
     *     'hostname' => [
     *         'type'        => 'text',
     *         'label'       => 'Host Name',
     *         'required'    => true,
     *         'default'     => localhost
     *         'description' => 'The redis server hostname.'
     *     ]
     * ]
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_settings_schema() : array;

    /**
     * Set provider configuration.
     *
     * @param array<string, mixed> $settings
     * @return void
     */
    public function set_settings( array $settings ) : void;

    /**
     * Tells whether the adapter can run in the host environment.
     * 
     * @return bool
     */
    public function is_supported() : bool;
}
