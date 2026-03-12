<?php
/**
 * Laravel cache adapter class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use Illuminate\Support\Facades\Cache as LaravelCache;
use Illuminate\Contracts\Cache\Repository;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Laravel cache adapter.
 *
 * Integrates with Laravel's cache system via the Cache facade.
 */
class LaravelCacheAdapter implements CacheAdapterInterface {

    /**
     * Retrieve a value from Laravel cache.
     *
     * Returns false explicitly on a miss so callers get a consistent
     * falsy sentinel regardless of what the underlying store returns.
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
     * @param int    $ttl   Time-to-live in seconds. 0 = store forever.
     * @return bool True on success.
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        return $ttl > 0
            ? LaravelCache::put( $key, $value, $ttl )
            : LaravelCache::forever( $key, $value );
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
     * Clear the entire cache store.
     *
     * @return bool True on success.
     */
    public function clear(): bool {
        return LaravelCache::flush();
    }

    /**
     * Check if a cache entry exists and has not expired.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has( string $key ): bool {
        return LaravelCache::has( $key );
    }

    /**
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'laravelcache';
    }

    public function get_name(): string {
        return 'Laravel Cache API';
    }

    public function get_settings_schema(): array {
        return [];
    }

    public function set_settings( array $settings ): void {}

    /**
     * Determine whether this adapter can run in the current environment.
     *
     * class_exists() on a facade only confirms autoloading — the facade
     * is useless if the IoC container has not been bootstrapped or the
     * cache store has not been bound. We therefore resolve the cache
     * repository contract directly from the container, which will throw
     * or return null if Laravel is not properly initialised.
     *
     * @return bool
     */
    public function is_supported(): bool {
        if ( ! class_exists( LaravelCache::class ) ) {
            return false;
        }

        try {
            // Resolve via the contract rather than the facade to avoid
            // triggering facade boot side-effects on unsupported environments.
            $resolved = app( Repository::class );
            return $resolved instanceof Repository;
        } catch ( \Throwable ) {
            return false;
        }
    }

    /**
    |----------------------
    | DIAGNOSTICS
    |----------------------
    */

    /**
     * Return runtime statistics from the active Laravel cache store.
     *
     * Laravel's Cache facade deliberately abstracts over many different
     * backends (file, database, Redis, Memcached, array, DynamoDB, etc.)
     * and provides no unified stats API — each driver exposes different
     * introspection capabilities.
     *
     * We inspect the underlying store returned by LaravelCache::getStore()
     * and extract whatever metrics are available for the known driver types:
     *
     *  - Redis store  → hits, misses, memory via the underlying Redis connection.
     *  - Memcached    → hits, misses, memory from getStats().
     *  - Array store  → entry count from the in-memory storage array.
     *  - All others   → zero defaults with the driver name in the extra bag.
     *
     * Returns a zero-default CacheStats on any failure so callers always
     * receive a valid object.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->is_supported() ) {
            return new CacheStats();
        }

        try {
            $store       = LaravelCache::getStore();
            $driver_name = class_basename( $store );

            // ── Redis store ──────────────────────────────────────────────
            if ( $store instanceof \Illuminate\Cache\RedisStore ) {
                $connection = $store->connection();
                $info       = $connection->info();

                return new CacheStats(
                    hits         : (int) ( $info['keyspace_hits']    ?? 0 ),
                    misses       : (int) ( $info['keyspace_misses']  ?? 0 ),
                    entries      : (int) $connection->dbsize(),
                    memory_used  : (int) ( $info['used_memory']      ?? 0 ),
                    memory_total : (int) ( $info['maxmemory']         ?? 0 ),
                    uptime       : (int) ( $info['uptime_in_seconds'] ?? 0 ),
                    extra        : [
                        'driver'        => $driver_name,
                        'redis_version' => $info['redis_version'] ?? '',
                        'prefix'        => $store->getPrefix(),
                    ],
                );
            }

            // ── Memcached store ──────────────────────────────────────────
            if ( $store instanceof \Illuminate\Cache\MemcachedStore ) {
                $raw = $store->getMemcached()->getStats();

                $hits         = 0;
                $misses       = 0;
                $entries      = 0;
                $memory_used  = 0;
                $memory_total = 0;
                $uptime       = 0;

                foreach ( $raw as $server_stats ) {
                    $hits         += (int) ( $server_stats['get_hits']        ?? 0 );
                    $misses       += (int) ( $server_stats['get_misses']      ?? 0 );
                    $entries      += (int) ( $server_stats['curr_items']      ?? 0 );
                    $memory_used  += (int) ( $server_stats['bytes']           ?? 0 );
                    $memory_total += (int) ( $server_stats['limit_maxbytes']  ?? 0 );
                    $uptime        = max( $uptime, (int) ( $server_stats['uptime'] ?? 0 ) );
                }

                return new CacheStats(
                    hits         : $hits,
                    misses       : $misses,
                    entries      : $entries,
                    memory_used  : $memory_used,
                    memory_total : $memory_total,
                    uptime       : $uptime,
                    extra        : [
                        'driver'       => $driver_name,
                        'server_count' => count( $raw ),
                        'prefix'       => $store->getPrefix(),
                    ],
                );
            }

            // ── Array store (testing / ephemeral) ────────────────────────
            if ( $store instanceof \Illuminate\Cache\ArrayStore ) {
                // ArrayStore::$storage is protected; access via reflection
                // to count entries without depending on internal API changes.
                $ref     = new \ReflectionProperty( $store, 'storage' );
                $storage = $ref->getValue( $store );
                $entries = is_array( $storage ) ? count( $storage ) : 0;

                return new CacheStats(
                    entries : $entries,
                    extra   : [
                        'driver'     => $driver_name,
                        'persistent' => false,
                    ],
                );
            }

            // ── Unknown / unsupported driver ─────────────────────────────
            // Return zero defaults with enough context for the dashboard
            // to explain why no metrics are available.
            return new CacheStats(
                extra: [
                    'driver'  => $driver_name,
                    'message' => "Stats are not available for the {$driver_name} driver.",
                ],
            );

        } catch ( \Throwable ) {
            return new CacheStats();
        }
    }

    /**
     * Test whether the Laravel cache API is operational.
     *
     * Performs a write → read → delete round-trip using a uniquely named
     * key so real cached data is never touched. The probe key is always
     * deleted before returning — if deletion fails the test still reports
     * the round-trip result truthfully; the short TTL (10 s) ensures the
     * probe self-expires even in the worst case.
     *
     * $settings is intentionally ignored — this adapter delegates all
     * configuration to Laravel's own config/cache.php; there is nothing
     * for us to configure here.
     *
     * @param array<string, mixed> $settings Ignored.
     * @return bool True if a full round-trip succeeds.
     */
    public function test( array $settings = [] ): bool {
        if ( ! $this->is_supported() ) {
            return false;
        }

        try {
            $probe = '__smliser_laravel_probe_' . \uniqid( '', true );

            $stored  = LaravelCache::put( $probe, 1, 10 );
            $value   = LaravelCache::get( $probe, false );
            $deleted = LaravelCache::forget( $probe );

            return $stored && $value === 1 && $deleted;
        } catch ( \Throwable ) {
            return false;
        }
    }
}