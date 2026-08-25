<?php
/**
 * In-memory cache adapter class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache\Adapters
 */

namespace SmartLicenseServer\Cache\Adapters;

use Throwable;
use SmartLicenseServer\Cache\CacheStats;
use SmartLicenseServer\Cache\Exceptions\CacheTestException;

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
     * Tracks operation counters for the lifetime of this instance.
     *
     * @var array{hits: int, misses: int, writes: int}
     */
    private array $counters = [
        'hits'   => 0,
        'misses' => 0,
        'writes' => 0,
    ];

    /**
     * Unix timestamp of when this adapter was instantiated.
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

    /*
    |--------------------------------------------
    | ServiceProviderInterface Implementation
    |--------------------------------------------
    */

    public function register(): void {}

    public function boot(): void {}

    /*
    |--------------------------------------------
    | CacheAdapterInterface — READ
    |--------------------------------------------
    */

    public function get( string $key ): mixed {
        if ( ! $this->has( $key ) ) {
            ++$this->counters['misses'];
            return false;
        }

        ++$this->counters['hits'];
        return $this->cache[ $key ]['value'];
    }

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

    /*
    |--------------------------------------------
    | CacheAdapterInterface — WRITE & CLEANUP
    |--------------------------------------------
    */

    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        $this->cache[ $key ] = [
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];

        ++$this->counters['writes'];
        return true;
    }

    public function delete( string $key ): bool {
        unset( $this->cache[ $key ] );
        return true;
    }

    public function clear(): bool {
        $this->cache    = [];
        $this->counters = [
            'hits'   => 0,
            'misses' => 0,
            'writes' => 0,
        ];
        return true;
    }

    public function prune_expired(): int {
        $pruned = 0;
        $now    = time();

        foreach ( $this->cache as $key => $entry ) {
            if ( $entry['expires'] !== 0 && $entry['expires'] < $now ) {
                unset( $this->cache[ $key ] );
                ++$pruned;
            }
        }

        return $pruned;
    }

    /*
    |--------------------------------------------
    | ATOMIC OPERATIONS
    |--------------------------------------------
    */

    public function modify( string $key, callable $callback, int $ttl = 0, mixed $default = null ): mixed {
        try {
            $current = $this->get( $key );
            if ( false === $current ) {
                $current = $default;
            }

            $updated = $callback( $current );

            if ( false === $updated ) {
                return false;
            }

            if ( $this->set( $key, $updated, $ttl ) ) {
                return $updated;
            }

            return false;
        } catch ( Throwable ) {
            return false;
        }
    }

    public function increment( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ): int|bool {
        return $this->modify(
            $key,
            static function ( mixed $value ) use ( $offset, $initial ): int {
                $num = is_numeric( $value ) ? (int) $value : $initial;
                return $num + $offset;
            },
            $ttl,
            $initial
        );
    }

    public function decrement( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ): int|bool {
        return $this->modify(
            $key,
            static function ( mixed $value ) use ( $offset, $initial ): int {
                $num = is_numeric( $value ) ? (int) $value : $initial;
                return $num - $offset;
            },
            $ttl,
            $initial
        );
    }

    /*
    |--------------------------------------------
    | ADAPTER IDENTITY & CONFIGURATION
    |--------------------------------------------
    */

    public static function get_id(): string {
        return 'runtime';
    }

    public static function get_name(): string {
        return 'Runtime Cache';
    }

    public function get_settings_schema(): array {
        return [];
    }

    public function set_settings( array $settings ): void {}

    public function is_supported(): bool {
        return true; // Pure PHP — always available.
    }

    public function is_active(): bool {
        return true;
    }

    /*
    |--------------------------------------------
    | DIAGNOSTICS & STATS
    |--------------------------------------------
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
            status       : $this->is_active(),
            extra        : [
                'persistent'        => false,
                'total_slots'       => count( $this->cache ), // Includes expired-but-unevicted.
                'expired_entries'   => count( $this->cache ) - $live_entries,
            ],
        );
    }
    public function test( array $settings = [] ): bool {
        try {
            $sandbox = new self();
            $probe   = '__smliser_runtime_probe_' . \uniqid( '', true );

            if ( ! $sandbox->set( $probe, 1, 10 ) ) {
                throw new CacheTestException( 'Runtime cache probe write failed unexpectedly.' );
            }

            $value = $sandbox->get( $probe );

            if ( $value !== 1 ) {
                throw new CacheTestException( 'Runtime cache probe read returned unexpected data.' );
            }

            if ( ! $sandbox->delete( $probe ) ) {
                throw new CacheTestException( 'Runtime cache probe delete failed unexpectedly.' );
            }

            if ( $sandbox->has( $probe ) ) {
                throw new CacheTestException( 'Runtime cache probe key still exists after deletion.' );
            }

            return true;
        } catch ( CacheTestException $e ) {
            throw $e;
        } catch ( Throwable $e ) {
            throw new CacheTestException(
                sprintf( 'Unexpected error while testing Runtime cache — %s', $e->getMessage() ),
                0,
                $e
            );
        }
    }
}