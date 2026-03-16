<?php
/**
 * In-memory cache adapter class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use SmartLicenseServer\Cache\CacheStats;
use SmartLicenseServer\Cache\Exceptions\CacheTestException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * In-memory cache adapter.
 *
 * Provides a lightweight, temporary cache for non-framework PHP environments.
 * The cache only persists during the request lifecycle (non-persistent).
 */
class RuntimeCacheAdapter implements CacheAdapterInterface {

    /**
     * Internal cache storage.
     *
     * @var array<string, array{value: mixed, expires: int}>
     */
    protected array $cache = [];

    /**
     * Tracks hit and miss counts for the lifetime of this instance.
     *
     * @var array{hits: int, misses: int}
     */
    private array $counters = [ 'hits' => 0, 'misses' => 0 ];

    /**
     * Unix timestamp of when this adapter was instantiated.
     *
     * Used to compute a meaningful uptime figure in get_stats().
     *
     * @var int
     */
    private int $born_at;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->born_at = time();
    }

    /**
     * Store a value in the cache.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl Time-to-live in seconds. 0 = infinite.
     * @return bool
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        $this->cache[ $key ] = [
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
        return true;
    }

    /**
     * Retrieve a value from the cache.
     *
     * @param string $key
     * @return mixed|false
     */
    public function get( string $key ): mixed {
        if ( ! $this->has( $key ) ) {
            ++$this->counters['misses'];
            return false;
        }

        ++$this->counters['hits'];
        return $this->cache[ $key ]['value'];
    }

    /**
     * Delete a cache entry by key.
     *
     * @param string $key
     * @return bool
     */
    public function delete( string $key ): bool {
        unset( $this->cache[ $key ] );
        return true;
    }

    /**
     * Check if a cache entry exists and has not expired.
     *
     * Expired entries are evicted lazily on access.
     *
     * @param string $key Unique cache key.
     * @return bool True if the key exists and is still valid, false otherwise.
     */
    public function has( string $key ): bool {
        if ( ! array_key_exists( $key, $this->cache ) ) {
            return false;
        }

        $entry = $this->cache[ $key ];

        if ( $entry['expires'] !== 0 && $entry['expires'] < time() ) {
            unset( $this->cache[ $key ] ); // Lazy eviction while we're here.
            return false;
        }

        return true;
    }

    /**
     * Clear all cache entries and reset hit/miss counters.
     *
     * @return bool
     */
    public function clear(): bool {
        $this->cache    = [];
        $this->counters = [ 'hits' => 0, 'misses' => 0 ];
        return true;
    }

    /*
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'runtime';
    }

    public function get_name(): string {
        return 'Runtime Cache';
    }

    public function get_settings_schema(): array {
        return [];
    }

    public function set_settings( array $settings ): void {}

    public function is_supported(): bool {
        return true; // Pure PHP — always available.
    }

    /*
    |----------------------
    | DIAGNOSTICS
    |----------------------
    */

    /**
     * Return runtime statistics derived from the in-process cache array.
     *
     * Because this adapter is entirely in-process there is no external
     * backend to query — every figure is computed directly from $this->cache
     * and the internal hit/miss counters tracked by get().
     *
     * memory_used is a best-effort estimate via serialize(); it reflects
     * the serialized byte size of all stored values rather than true heap
     * allocation, which is not accessible from userland PHP.
     *
     * memory_total is always 0 — there is no fixed memory ceiling for a
     * plain PHP array, so reporting a limit would be misleading.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        // Count only non-expired entries and estimate their memory footprint.
        $live_entries = 0;
        $memory_used  = 0;
        $now          = time();

        foreach ( $this->cache as $entry ) {
            if ( $entry['expires'] !== 0 && $entry['expires'] < $now ) {
                continue; // Skip silently; lazy eviction handles the unset.
            }

            ++$live_entries;
            $memory_used += strlen( serialize( $entry['value'] ) );
        }

        return new CacheStats(
            hits         : $this->counters['hits'],
            misses       : $this->counters['misses'],
            entries      : $live_entries,
            memory_used  : $memory_used,
            memory_total : 0,   // No fixed ceiling for a PHP array.
            uptime       : max( 0, $now - $this->born_at ),
            extra        : [
                'persistent'     => false,
                'total_slots'    => count( $this->cache ), // Includes expired-but-unevicted.
                'expired_slots'  => count( $this->cache ) - $live_entries,
            ],
        );
    }

/**
     * Test whether this adapter is operational with the supplied settings.
     *
     * RuntimeCacheAdapter has no configuration and no external connection,
     * so $settings is intentionally ignored. The test performs a full
     * write → read → delete round-trip on a temporary isolated instance
     * so that the live cache is never touched.
     *
     * @param array<string, mixed> $settings Ignored — no configuration required.
     * @return bool True if a round-trip succeeds on a clean instance.
     * @throws CacheTestException On any operational failure.
     */
    public function test( array $settings = [] ): bool {
        try {
            $sandbox = new self();
            $probe   = '__smliser_runtime_probe_' . \uniqid( '', true );

            // Write.
            if ( ! $sandbox->set( $probe, 1, 10 ) ) {
                throw new CacheTestException(
                    'Runtime cache probe write failed unexpectedly.'
                );
            }

            // Read.
            $value = $sandbox->get( $probe );

            if ( $value !== 1 ) {
                throw new CacheTestException(
                    'Runtime cache probe read returned unexpected data — the stored value was corrupted.'
                );
            }

            // Delete.
            if ( ! $sandbox->delete( $probe ) ) {
                throw new CacheTestException(
                    'Runtime cache probe delete failed unexpectedly.'
                );
            }

            if ( $sandbox->has( $probe ) ) {
                throw new CacheTestException(
                    'Runtime cache probe key still exists after deletion — eviction is not working correctly.'
                );
            }

            return true;

        } catch ( CacheTestException $e ) {
            throw $e;
        } catch ( \Throwable $e ) {
            throw new CacheTestException(
                sprintf( 'Unexpected error while testing Runtime cache — %s', $e->getMessage() ),
                0,
                $e
            );
        }
    }
}