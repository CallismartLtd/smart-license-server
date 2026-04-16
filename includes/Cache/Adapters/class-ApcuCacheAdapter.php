<?php
/**
 * APCu Cache Adapter
 *
 * Provides a persistent in-memory cache for native PHP environments
 * using the APCu extension. Falls back gracefully if APCu is unavailable.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use SmartLicenseServer\Cache\CacheStats;
use SmartLicenseServer\Cache\Exceptions\CacheTestException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Adapter for APCu-based caching.
 */
class ApcuCacheAdapter implements CacheAdapterInterface {

    /**
     * APCu availability status.
     *
     * @var bool
     */
    protected bool $enabled;

    /**
     * Constructor.
     *
     * Checks if APCu is available in this environment.
     */
    public function __construct() {
        $this->enabled = \extension_loaded( 'apcu' ) && \ini_get( 'apc.enabled' );
    }

    /**
     * Store a value in cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time-to-live in seconds. 0 = infinite.
     * @return bool True on success, false on failure.
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        if ( ! $this->enabled ) {
            return false;
        }

        return \apcu_store( $key, $value, $ttl );
    }

    /**
     * Retrieve a value from cache.
     *
     * @param string $key Cache key.
     * @return mixed The cached value, or false on a cache miss.
     */
    public function get( string $key ): mixed {
        if ( ! $this->enabled ) {
            return false;
        }

        $success = false;
        $value   = \apcu_fetch( $key, $success );

        return $success ? $value : false;
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool True on success, false on failure.
     */
    public function delete( string $key ): bool {
        if ( ! $this->enabled ) {
            return false;
        }

        return \apcu_delete( $key );
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $key Cache key.
     * @return bool True if key exists, false otherwise.
     */
    public function has( string $key ): bool {
        if ( ! $this->enabled ) {
            return false;
        }

        return \apcu_exists( $key );
    }

    /**
     * Clear all cache entries.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool {
        if ( ! $this->enabled ) {
            return false;
        }

        return \apcu_clear_cache();
    }

    /**
     * Check if APCu is available.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    public static function get_id(): string {
        return 'apcu';
    }

    public static function get_name(): string {
        return 'APCu Cache';
    }

    public function get_settings_schema(): array {
        return [];
    }

    public function set_settings( array $settings ): void {}

    public function is_supported(): bool {
        return $this->is_enabled();
    }

    /**
    |----------------------
    | DIAGNOSTICS
    |----------------------
    */

    /**
     * Return runtime statistics for the APCu backend.
     *
     * Pulls hit/miss counters from apcu_cache_info() and memory
     * figures from apcu_sma_info(). When APCu is disabled every
     * field is left at its zero default so callers always receive
     * a valid CacheStats instance.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->enabled ) {
            return new CacheStats();
        }

        $info = \apcu_cache_info( true );   // true = compact/no entry list
        $sma  = \apcu_sma_info();

        $memory_total = (int) ( $sma['num_seg'] * $sma['seg_size'] );
        $memory_used  = (int) ( $memory_total   - $sma['avail_mem'] );
        $uptime       = isset( $info['start_time'] )
            ? max( 0, time() - (int) $info['start_time'] )
            : 0;

        return new CacheStats(
            hits         : (int) ( $info['num_hits']    ?? 0 ),
            misses       : (int) ( $info['num_misses']  ?? 0 ),
            entries      : (int) ( $info['num_entries'] ?? 0 ),
            memory_used  : $memory_used,
            memory_total : $memory_total,
            uptime       : $uptime,
            extra        : [
                'num_slots'             => (int) ( $info['num_slots']     ?? 0 ),
                'num_expunges'          => (int) ( $info['expunges']      ?? 0 ),
                'expired_entries'       => (int) ( $info['expunges']      ?? 0 ),
                'num_inserts'           => (int) ( $info['num_inserts']   ?? 0 ),
                'file_upload_progress'  => (bool) \ini_get( 'apc.rfc1867' ),
            ],
        );
    }

    /**
     * Test whether APCu is operational.
     *
     * APCu has no external connection to configure, so $settings is
     * intentionally ignored — the only meaningful check is whether the
     * extension is loaded, enabled, and capable of a full round-trip.
     *
     * The probe key is namespaced and suffixed with a unique ID so it
     * cannot collide with real application keys, and it is always
     * deleted before this method returns.
     *
     * @param array<string, mixed> $settings Ignored for APCu (no config required).
     * @return bool True if APCu can store, retrieve, and delete a value.
     * @throws CacheTestException On any operational failure.
     */
    public function test( array $settings = [] ): bool {
        if ( ! $this->enabled ) {
            throw new CacheTestException(
                'APCu is not available. Ensure the APCu extension is installed and apc.enabled is set to 1 in your php.ini.'
            );
        }

        $probe = '__smliser_apcu_probe_' . \uniqid( '', true );

        try {
            // Write.
            if ( ! \apcu_store( $probe, 1, 10 ) ) {
                throw new CacheTestException(
                    'APCu probe write failed. The shared memory cache may be full or misconfigured.'
                );
            }

            // Read.
            $fetched = false;
            $value   = \apcu_fetch( $probe, $fetched );

            if ( ! $fetched ) {
                throw new CacheTestException(
                    'APCu probe read failed — the key was not found immediately after writing. Shared memory may be under pressure.'
                );
            }

            if ( $value !== 1 ) {
                throw new CacheTestException(
                    'APCu probe read returned unexpected data — the stored value was corrupted.'
                );
            }

            // Delete.
            if ( ! \apcu_delete( $probe ) ) {
                throw new CacheTestException(
                    'APCu probe delete failed — the key could not be removed from shared memory.'
                );
            }

            return true;

        } catch ( CacheTestException $e ) {
            \apcu_delete( $probe );
            throw $e;
        } catch ( \Throwable $e ) {
            \apcu_delete( $probe );
            throw new CacheTestException(
                sprintf( 'Unexpected error while testing APCu — %s', $e->getMessage() ),
                0,
                $e
            );
        }
    }
}