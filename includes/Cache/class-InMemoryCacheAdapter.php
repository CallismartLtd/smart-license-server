<?php
/**
 * In-memory cache adapter class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * In-memory cache adapter.
 *
 * Provides a lightweight, temporary cache for non-framework PHP environments.
 * The cache only persists during the request lifecycle (non-persistent).
 */
class InMemoryCacheAdapter implements CacheAdapterInterface {

    /**
     * Internal cache storage.
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Store a value in the cache.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl Time-to-live in seconds
     * @return bool
     */
    public function set( string $key, $value, int $ttl = 0 ): bool {
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
     * @return mixed|null
     */
    public function get( string $key ) {
        if ( ! isset( $this->cache[ $key ] ) ) {
            return null;
        }

        $entry = $this->cache[ $key ];

        if ( $entry['expires'] !== 0 && $entry['expires'] < time() ) {
            unset( $this->cache[ $key ] );
            return null;
        }

        return $entry['value'];
    }

    /**
     * Delete a cache entry by key.
     *
     * @param string $key
     * @return bool
     */
    public function delete( string $key ): bool {
        if ( isset( $this->cache[ $key ] ) ) {
            unset( $this->cache[ $key ] );
        }
        return true;
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $key Unique cache key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has( string $key ): bool {
        return array_key_exists( $key, $this->cache );
    }

    /**
     * Clear all cache entries.
     *
     * @return bool
     */
    public function clear(): bool {
        $this->cache = [];
        return true;
    }
}
