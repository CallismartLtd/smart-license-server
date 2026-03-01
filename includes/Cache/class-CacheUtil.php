<?php
/**
 * Cache utilities.
 *
 * @package SmartLicenseServer\Cache
 * @since 1.0.0
 */

namespace SmartLicenseServer\Cache;

defined( 'SMLISER_ABSPATH' ) || exit;

final class CacheUtil {

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
    public static function make_key( string $method, array $params = [] ) : string {
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
    public static function normalize_params( $value ) {
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
    public static function is_assoc_array( array $array ) : bool {
        return array_keys( $array ) !== range( 0, count( $array ) - 1 );
    }
}
