<?php
/**
 * Cache-aware trait.
 *
 * @package SmartLicenseServer\Cache
 * @since 1.0.0
 */

namespace SmartLicenseServer\Cache;

defined( 'SMLISER_ABSPATH' ) || exit;

trait CacheAwareTrait {

    /**
     * Build a cache key scoped to the calling class.
     *
     * @param string $method Method name.
     * @param array  $params Parameters.
     * @return string
     */
    protected static function make_cache_key( string $method, array $params = [] ) : string {
        return CacheUtil::make_key(
            static::class . '::' . $method,
            $params
        );
    }

    /**
     * Retrieve a value from cache.
     *
     * @param string $key
     * @return mixed|null
     */
    protected static function cache_get( string $key ) {
        return Cache::instance()->get( $key );
    }

    /**
     * Store a value in cache.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl
     * @return bool
     */
    protected static function cache_set( string $key, $value, int $ttl = 0 ) : bool {
        return Cache::instance()->set( $key, $value, $ttl );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key
     * @return bool
     */
    protected static function cache_delete( string $key ) : bool {
        return Cache::instance()->delete( $key );
    }

    /**
     * Clear the entire cache record
     * 
     * @return bool
     */
    protected static function cache_clear() : bool {
        return Cache::instance()->clear();
    }
}
