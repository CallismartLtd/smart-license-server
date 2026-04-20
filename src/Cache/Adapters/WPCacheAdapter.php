<?php
/**
 * WordPress cache adapter class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use SmartLicenseServer\Cache\CacheStats;

defined( 'SMLISER_ABSPATH' ) || exit;

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

    /**
     * Retrieve a value from WordPress cache.
     *
     * @param string $key Cache key.
     * @return mixed|false Cached value or false if not found.
     */
    public function get( string $key ): mixed {
        $found = false;
        $value = wp_cache_get( $key, $this->group, false, $found );
        return $found ? $value : false;
    }

    /**
     * Store a value in WordPress cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to cache.
     * @param int    $ttl   Time-to-live in seconds. 0 = no expiry.
     * @return bool True on success.
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        return wp_cache_set( $key, $value, $this->group, $ttl );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool True if deleted.
     */
    public function delete( string $key ): bool {
        return wp_cache_delete( $key, $this->group );
    }

    /**
     * Clear all entries in this adapter's cache group.
     *
     * Uses wp_cache_flush_group() when the active object cache supports
     * group flushing (WP 6.1+, or persistent cache plugins that declare
     * WP_CACHE_GROUP_FLUSH). Falls back to wp_cache_flush() — which flushes
     * the entire object cache — only as a last resort, with a warning so the
     * caller is aware of the wider impact.
     *
     * @return bool True on success.
     */
    public function clear(): bool {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            return wp_cache_flush_group( $this->group );
        }

        // Fallback: flush the entire object cache. Broader than ideal but
        // unavoidable on sites without group-flush support.
        _doing_it_wrong(
            __METHOD__,
            'wp_cache_flush_group() is not available. Falling back to wp_cache_flush(), which clears the entire object cache.',
            '0.2.0'
        );

        return wp_cache_flush();
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has( string $key ): bool {
        $found = false;
        wp_cache_get( $key, $this->group, false, $found );
        return $found;
    }

    /**
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    public static function get_id(): string {
        return 'wpcache';
    }

    public static function get_name(): string {
        return 'WordPress Cache API';
    }

    public function get_settings_schema(): array {
        return [];
    }

    public function set_settings( array $settings ): void {}

    /**
     * Determine whether this adapter can run in the current environment.
     *
     * Requires WordPress core cache functions to be defined. These are
     * loaded very early in wp-settings.php, so if they are missing the
     * WordPress bootstrap has not run and this adapter cannot be used.
     *
     * @return bool
     */
    public function is_supported(): bool {
        return function_exists( 'wp_cache_get' )
            && function_exists( 'wp_cache_set' )
            && function_exists( 'wp_cache_delete' )
            && function_exists( 'wp_cache_flush' );
    }

    /**
    |----------------------
    | DIAGNOSTICS
    |----------------------
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

        if ( is_object( $wp_object_cache ) ) {
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
            extra        : [
                'persistent_cache'    => $has_persistent,
                'group_flush_support' => $has_group_flush,
                'cache_group'         => $this->group,
                'wp_cache_class'      => is_object( $wp_object_cache ) ? get_class( $wp_object_cache ) : null,
            ],
        );
    }

    /**
     * Test whether the WordPress cache API is operational.
     *
     * Uses an isolated key in a dedicated probe group (never the live group)
     * to avoid any risk of polluting or evicting real cached data. The probe
     * key is explicitly deleted before returning so it does not linger in
     * non-persistent memory for the rest of the request.
     *
     * $settings is intentionally ignored — WPCacheAdapter has no external
     * connection to configure; the only meaningful test is a live round-trip.
     *
     * @param array<string, mixed> $settings Ignored — no configuration required.
     * @return bool True if a write → read → delete round-trip succeeds.
     */
    public function test( array $settings = [] ): bool {
        if ( ! $this->is_supported() ) {
            return false;
        }

        try {
            $probe_group = 'smliser_probe';
            $probe_key   = '__smliser_wpcache_probe_' . \uniqid( '', true );

            $found  = false;
            $stored = wp_cache_set( $probe_key, 1, $probe_group, 10 );
            $value  = wp_cache_get( $probe_key, $probe_group, false, $found );
            $deleted = wp_cache_delete( $probe_key, $probe_group );

            return $stored && $found && $value === 1 && $deleted;
        } catch ( \Throwable ) {
            return false;
        }
    }
}