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
     * Set adapter configuration.
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

    /**
    |----------------------
    | DIAGNOSTICS
    |----------------------
    */

    /**
     * Return runtime statistics for this cache adapter.
     *
     * Implementers must populate a {@see CacheStats} value object with
     * whatever metrics the underlying backend exposes. Fields that are not
     * available for a given backend should be left at their default (0 / null).
     *
     * Example (APCu):
     * ```php
     * public function get_stats(): CacheStats {
     *     $info = apcu_cache_info( true );
     *     $sma  = apcu_sma_info();
     *
     *     return new CacheStats(
     *         hits         : $info['num_hits'],
     *         misses       : $info['num_misses'],
     *         entries      : $info['num_entries'],
     *         memory_used  : $sma['num_seg'] * $sma['seg_size'] - $sma['avail_mem'],
     *         memory_total : $sma['num_seg'] * $sma['seg_size'],
     *         uptime       : $info['start_time'] ? time() - $info['start_time'] : 0,
     *     );
     * }
     * ```
     *
     * @return CacheStats
     */
    public function get_stats() : CacheStats;

    /**
     * Test whether the adapter can connect and operate with the supplied settings.
     *
     * Implementations should:
     *  1. Apply $settings temporarily (do NOT persist them via set_settings()).
     *  2. Attempt a write → read → delete round-trip against the backend.
     *  3. Return true only when all three steps succeed.
     *  4. Restore the previous configuration before returning.
     *
     * This method must never throw — all exceptions must be caught internally
     * and result in a false return value.
     *
     * Example (Redis):
     * ```php
     * public function test( array $settings ): bool {
     *     try {
     *         $client = new \Redis();
     *         $client->connect( $settings['hostname'], (int) $settings['port'] );
     *
     *         if ( ! empty( $settings['password'] ) ) {
     *             $client->auth( $settings['password'] );
     *         }
     *
     *         $probe = '__smliser_probe_' . uniqid();
     *         $client->set( $probe, '1', 10 );
     *         $ok = $client->get( $probe ) === '1';
     *         $client->del( $probe );
     *
     *         return $ok;
     *     } catch ( \Throwable $e ) {
     *         return false;
     *     }
     * }
     * ```
     *
     * @param array<string, mixed> $settings Settings to test, shaped like get_settings_schema().
     * @return bool True if the adapter is reachable and functional with these settings.
     */
    public function test( array $settings ) : bool;
}