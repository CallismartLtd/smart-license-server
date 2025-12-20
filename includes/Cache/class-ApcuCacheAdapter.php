<?php
/**
 * APCu Cache Adapter
 *
 * Provides a persistent in-memory cache for native PHP environments
 * using the APCu extension. Falls back gracefully if APCu is unavailable.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

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
    protected $enabled;

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
     * @param string $key Cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl Time-to-live in seconds. 0 = infinite.
     *
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
     * @param mixed  $default Default value if key is not found.
     *
     * @return mixed
     */
    public function get( string $key, mixed $default = null ): mixed {
        if ( ! $this->enabled ) {
            return $default;
        }

        $success = false;
        $value   = \apcu_fetch( $key, $success );

        return $success ? $value : $default;
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     *
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
     *
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
}
