<?php
/**
 * Cache-aware trait.
 *
 * @package SmartLicenseServer\Cache
 * @since 0.2.0
 */

namespace SmartLicenseServer\Cache;

/**
 * @property Cache $cache
 */
trait CacheAwareTrait {
    /**
     * Build a cache key scoped to the calling class.
     *
     * @param string $method Method name.
     * @param array  $params Parameters.
     * @return string
     */
    protected static function make_cache_key( string $method, array $params = [] ) : string {
        return static::make_key(
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
        static::ensure_cache();
        return static::$cache->get( $key );
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
        static::ensure_cache();
        return static::$cache->set( $key, $value, $ttl );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key
     * @return bool
     */
    protected static function cache_delete( string $key ) : bool {
        static::ensure_cache();
        return static::$cache->delete( $key );
    }

    /**
     * Clear the entire cache record
     * 
     * @return bool
     */
    protected static function cache_clear() : bool {
        static::ensure_cache();
        return static::$cache->clear();
    }

    /**
     * Get default cache ttl
     * 
     * @return int
     */
    protected static function default_ttl() : int {
        static::ensure_cache();
        return static::$cache->default_ttl();
    }

    protected static function ensure_cache() : void {
        if ( isset( static::$cache ) ) {
            return;
        }

        throw new \RuntimeException(
            'The cache abstraction layer must be set early.'
        );
    }

    /**
     * Build a deterministic cache key.
     *
     * Ensures the same logical inputs always produce the same cache key,
     * regardless of array order or nested structures.
     *
     * @param string $method Method or operation name.
     * @param array  $params Parameters affecting the result.
     * @return string
     */
    protected static function make_key( string $method, array $params = [] ) : string {
        $normalized = self::normalize_params( $params );

        return sprintf(
            'smliser:%s:%s',
            $method,
            md5( \smliser_safe_json_encode( $normalized ) )
        );
    }

    /**
     * Normalize parameters into a deterministic structure.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function normalize_params( $value ) {
        // Scalars & null.
        if ( is_null( $value ) || is_scalar( $value ) ) {
            return $value;
        }

        // Arrays.
        if ( is_array( $value ) ) {
            if ( self::is_assoc_array( $value ) ) {
                ksort( $value );
            }

            foreach ( $value as $key => $val ) {
                $value[ $key ] = self::normalize_params( $val );
            }

            return $value;
        }

        // Objects.
        if ( is_object( $value ) ) {
            // Allow domain objects to define cache identity explicitly.
            if ( method_exists( $value, 'get_cache_key' ) ) {
                return $value->get_cache_key();
            }

            return array(
                '__class' => get_class( $value ),
                '__data'  => self::normalize_params( get_object_vars( $value ) ),
            );
        }

        // Fallback (resources, closures, etc).
        return gettype( $value );
    }

    /**
     * Determine if an array is associative.
     *
     * @param array $array
     * @return bool
     */
    protected static function is_assoc_array( array $array ) : bool {
        return array_keys( $array ) !== range( 0, count( $array ) - 1 );
    }
}
