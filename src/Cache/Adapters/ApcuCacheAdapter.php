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
     * Checks if APCu is available and enabled for the current runtime (Web or CLI).
     */
    public function __construct() {
        $cliEnabled    = \PHP_SAPI === 'cli' && (bool) \ini_get( 'apc.enable_cli' );
        $webEnabled    = \PHP_SAPI !== 'cli' && (bool) \ini_get( 'apc.enabled' );
        $this->enabled = \extension_loaded( 'apcu' ) && ( $cliEnabled || $webEnabled );
    }

    /**
     * Retrieve a cached value by key.
     *
     * @param string $key Unique cache key.
     * @return mixed Returns the cached value or false if not found.
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
     * Store a value in the cache.
     *
     * @param string $key   Unique cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time-to-live in seconds. 0 = forever.
     * @return bool True on success, false on failure.
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        if ( ! $this->enabled ) {
            return false;
        }

        return \apcu_store( $key, $value, $ttl );
    }

    /**
     * Delete a cache entry by key.
     *
     * @param string $key Unique cache key.
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
     * @param string $key Unique cache key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has( string $key ): bool {
        if ( ! $this->enabled ) {
            return false;
        }

        return \apcu_exists( $key );
    }

    /**
     * Clear the entire cache.
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
    |--------------------------------------------------------------------------
    | ATOMIC OPERATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Atomically read, modify, and rewrite a cached value via a callback.
     *
     * Utilizes native `apcu_entry()` for memory-level write locks. If the key is
     * missing, `$default` is passed to the callback. If the callback returns `false`,
     * the update is aborted.
     *
     * @param string                 $key      Unique cache key.
     * @param callable(mixed): mixed $callback Receives ($currentValue), returns updated value.
     * @param int                    $ttl      Time-to-live in seconds for updated entry.
     * @param mixed                  $default  Fallback value passed to callback if key does not exist.
     * @return mixed Returns the updated value on success, or false on failure.
     */
    public function modify( string $key, callable $callback, int $ttl = 0, mixed $default = null ): mixed {
        if ( ! $this->enabled ) {
            return false;
        }

        // Check key existence beforehand to supply the correct `$current` argument to the callback.
        $exists = \apcu_exists( $key );
        $aborted = false;

        $result = \apcu_entry( $key, function ( string $entryKey ) use ( $exists, $callback, $default, &$aborted ) {
            $current = $exists ? \apcu_fetch( $entryKey ) : $default;
            $updated = $callback( $current );

            if ( false === $updated ) {
                $aborted = true;
                return $current; // Returning original value aborts modification intent.
            }

            return $updated;
        }, $ttl );

        return $aborted ? false : $result;
    }

    /**
     * Atomically increment a numeric cache value.
     *
     * @param string $key     Unique cache key.
     * @param int    $offset  Amount to increment by.
     * @param int    $initial Default value if key does not exist.
     * @param int    $ttl     Time-to-live in seconds if item is created.
     * @return int|false New integer value on success, false on failure.
     */
    public function increment( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ): int|bool {
        if ( ! $this->enabled ) {
            return false;
        }

        $success = false;
        $value   = \apcu_inc( $key, $offset, $success, $ttl );

        if ( $success ) {
            return $value;
        }

        // Key does not exist: atomically initialize via modify.
        return $this->modify(
            $key,
            static function ( mixed $current ) use ( $offset, $initial ): int {
                $base = \is_numeric( $current ) ? (int) $current : $initial;
                return $base + $offset;
            },
            $ttl,
            $initial
        );
    }

    /**
     * Atomically decrement a numeric cache value.
     *
     * @param string $key     Unique cache key.
     * @param int    $offset  Amount to decrement by.
     * @param int    $initial Default value if key does not exist.
     * @param int    $ttl     Time-to-live in seconds if item is created.
     * @return int|false New integer value on success, false on failure.
     */
    public function decrement( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ): int|bool {
        if ( ! $this->enabled ) {
            return false;
        }

        $success = false;
        $value   = \apcu_dec( $key, $offset, $success, $ttl );

        if ( $success ) {
            return $value;
        }

        // Key does not exist: atomically initialize via modify.
        return $this->modify(
            $key,
            static function ( mixed $current ) use ( $offset, $initial ): int {
                $base = \is_numeric( $current ) ? (int) $current : $initial;
                return $base - $offset;
            },
            $ttl,
            $initial
        );
    }

    /**
    |--------------------------------------------------------------------------
    | CONFIGURATION & SUPPORT
    |--------------------------------------------------------------------------
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

    public function is_active(): bool {
        return $this->enabled && \function_exists( 'apcu_enabled' ) && \apcu_enabled();
    }

    /**
    |--------------------------------------------------------------------------
    | DIAGNOSTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Return runtime statistics for the APCu backend.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->enabled ) {
            return new CacheStats();
        }

        $info = \apcu_cache_info( true );
        $sma  = \apcu_sma_info();

        if ( false === $info || false === $sma ) {
            return new CacheStats();
        }

        $memoryTotal = (int) ( ( $sma['num_seg'] ?? 0 ) * ( $sma['seg_size'] ?? 0 ) );
        $memoryUsed  = (int) ( $memoryTotal - ( $sma['avail_mem'] ?? 0 ) );
        $uptime      = isset( $info['start_time'] )
            ? max( 0, time() - (int) $info['start_time'] )
            : 0;

        return new CacheStats(
            hits        : (int) ( $info['num_hits']    ?? 0 ),
            misses      : (int) ( $info['num_misses']  ?? 0 ),
            entries     : (int) ( $info['num_entries'] ?? 0 ),
            memory_used : $memoryUsed,
            memory_total: $memoryTotal,
            uptime      : $uptime,
            status      : $this->is_active(),
            extra       : [
                'num_slots'            => (int) ( $info['num_slots']   ?? 0 ),
                'expired_entries'      => (int) ( $info['expunges']    ?? 0 ),
                'num_inserts'          => (int) ( $info['num_inserts'] ?? 0 ),
                'file_upload_progress' => (bool) \ini_get( 'apc.rfc1867' ),
            ],
        );
    }

    /**
     * Test whether APCu is operational.
     *
     * @param array<string, mixed> $settings Settings to test.
     * @return bool True if APCu can store, retrieve, and delete a value.
     * @throws CacheTestException On any operational failure.
     */
    public function test( array $settings = [] ): bool {
        if ( ! $this->enabled ) {
            throw new CacheTestException(
                'APCu is not available. Ensure the APCu extension is installed and apc.enabled (or apc.enable_cli for CLI environments) is set to 1 in your php.ini.'
            );
        }

        $probe = '__smliser_apcu_probe_' . \uniqid( '', true );

        try {
            if ( ! \apcu_store( $probe, 1, 10 ) ) {
                throw new CacheTestException(
                    'APCu probe write failed. Shared memory may be full or misconfigured.'
                );
            }

            $fetched = false;
            $value   = \apcu_fetch( $probe, $fetched );

            if ( ! $fetched ) {
                throw new CacheTestException(
                    'APCu probe read failed — the key was not found immediately after writing.'
                );
            }

            if ( $value !== 1 ) {
                throw new CacheTestException(
                    'APCu probe read returned unexpected data — the stored value was corrupted.'
                );
            }

            return true;
        } catch ( CacheTestException $e ) {
            throw $e;
        } catch ( \Throwable $e ) {
            throw new CacheTestException(
                sprintf( 'Unexpected error while testing APCu — %s', $e->getMessage() ),
                0,
                $e
            );
        } finally {
            \apcu_delete( $probe );
        }
    }

    /**
     * Check if APCu is available.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }
}