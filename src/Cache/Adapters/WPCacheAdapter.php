<?php
/**
 * WordPress cache adapter class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache\Adapters
 */

namespace SmartLicenseServer\Cache\Adapters;

use Throwable;
use SmartLicenseServer\Cache\CacheStats;

/**
 * WordPress cache adapter.
 *
 * Integrates with WordPress caching functions (object cache / plugins).
 */
class WPCacheAdapter implements CacheAdapterInterface {

    /**
     * Cache group used for all keys managed by this adapter.
     *
     * @var string
     */
    protected string $group = 'smliser';

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
        $found = false;
        $value = wp_cache_get( $key, $this->group, false, $found );
        return $found ? $value : false;
    }

    public function has( string $key ): bool {
        $found = false;
        wp_cache_get( $key, $this->group, false, $found );
        return $found;
    }

    /*
    |--------------------------------------------
    | CacheAdapterInterface — WRITE & CLEANUP
    |--------------------------------------------
    */

    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        return wp_cache_set( $key, $value, $this->group, $ttl );
    }

    public function delete( string $key ): bool {
        return wp_cache_delete( $key, $this->group );
    }

    public function clear(): bool {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            return wp_cache_flush_group( $this->group );
        }

        _doing_it_wrong(
            __METHOD__,
            'wp_cache_flush_group() is not available. Falling back to wp_cache_flush(), which clears the entire object cache.',
            '0.2.0'
        );

        return wp_cache_flush();
    }

    /**
     * Prune expired entries.
     *
     * WP object cache handles garbage collection and TTL expiration internally.
     *
     * @return int Number of pruned items (always 0 for WP Object Cache).
     */
    public function prune_expired(): int {
        return 0;
    }

    /*
    |--------------------------------------------
    | ATOMIC OPERATIONS
    |--------------------------------------------
    */

    public function modify( string $key, callable $callback, int $ttl = 0, mixed $default = null ): mixed {
        if ( ! $this->is_active() ) {
            return false;
        }

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
        if ( ! $this->is_active() ) {
            return false;
        }

        if ( function_exists( 'wp_cache_incr' ) ) {
            if ( ! $this->has( $key ) ) {
                $this->set( $key, $initial, $ttl );
            }
            
            $result = wp_cache_incr( $key, $offset, $this->group );
            if ( false !== $result ) {
                return (int) $result;
            }
        }

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
        if ( ! $this->is_active() ) {
            return false;
        }

        if ( function_exists( 'wp_cache_decr' ) ) {
            if ( ! $this->has( $key ) ) {
                $this->set( $key, $initial, $ttl );
            }

            $result = wp_cache_decr( $key, $offset, $this->group );
            if ( false !== $result ) {
                return (int) $result;
            }
        }

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
        return 'wpcache';
    }

    public static function get_name(): string {
        return 'WP Cache';
    }

    public function get_settings_schema(): array {
        return [];
    }

    public function set_settings( array $settings ): void {}

    public function is_supported(): bool {
        return function_exists( 'wp_cache_get' )
            && function_exists( 'wp_cache_set' )
            && function_exists( 'wp_cache_delete' )
            && function_exists( 'wp_cache_flush' );
    }

    public function is_active(): bool {
        return $this->is_supported();
    }

    /*
    |--------------------------------------------
    | DIAGNOSTICS & STATS
    |--------------------------------------------
    */

    /**
     * Return runtime statistics from the WordPress object cache.
     *
     * WordPress itself does not expose a standard stats API — only the
     * built-in non-persistent cache (WP_Object_Cache) tracks hits and misses
     * via the global $wp_object_cache instance. Persistent cache plugins
     * (Redis Object Cache, W3 Total Cache, etc.) may or may not expose
     * the same interface.
     *
     * We probe $wp_object_cache defensively: if the expected properties
     * exist we read them; otherwise we fall back to 0 so the return value
     * is always a valid CacheStats. The extra bag records whether a
     * persistent cache drop-in is active so the dashboard can surface it.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->is_supported() ) {
            return new CacheStats();
        }

        global $wp_object_cache;

        $hits         = 0;
        $misses       = 0;
        $entries      = 0;
        $memory_used  = 0;

        if (  $wp_object_cache instanceof \WP_Object_Cache ) {
            // Standard WP_Object_Cache properties — present on core and many plugins.
            $hits   = (int) ( $wp_object_cache->cache_hits   ?? 0 );
            $misses = (int) ( $wp_object_cache->cache_misses ?? 0 );

            // The internal cache array is keyed by group then by key.
            // We count only entries belonging to our group to stay scoped.
            $raw_cache = $wp_object_cache->cache ?? [];

            if ( isset( $raw_cache[ $this->group ] ) && is_array( $raw_cache[ $this->group ] ) ) {
                $entries     = count( $raw_cache[ $this->group ] );
                $memory_used = strlen( serialize( $raw_cache[ $this->group ] ) );
            }
        }

        // wp_cache_flush_group support implies a persistent cache that understands groups.
        $has_persistent   = defined( 'WP_CACHE' ) && WP_CACHE;
        $has_group_flush  = function_exists( 'wp_cache_flush_group' );

        return new CacheStats(
            hits         : $hits,
            misses       : $misses,
            entries      : $entries,
            memory_used  : $memory_used,
            memory_total : 0,   // No fixed ceiling exposed by the WP cache API.
            uptime       : 0,   // No server process — request-scoped for non-persistent cache.
            status       : $this->is_active(),
            extra        : [
                'persistent_cache'    => $has_persistent,
                'group_flush_support' => $has_group_flush,
                'cache_group'         => $this->group,
                'wp_cache_class'      => is_object( $wp_object_cache ) ? get_class( $wp_object_cache ) : null,
            ],
        );
    }

    public function test( array $settings = [] ): bool {
        if ( ! $this->is_supported() ) {
            return false;
        }

        try {
            $probe_group = 'smliser_probe';
            $probe_key   = '__smliser_wpcache_probe_' . \uniqid( '', true );

            $found   = false;
            $stored  = wp_cache_set( $probe_key, 1, $probe_group, 10 );
            $value   = wp_cache_get( $probe_key, $probe_group, false, $found );
            $deleted = wp_cache_delete( $probe_key, $probe_group );

            return $stored && $found && $value === 1 && $deleted;
        } catch ( Throwable ) {
            return false;
        }
    }
}