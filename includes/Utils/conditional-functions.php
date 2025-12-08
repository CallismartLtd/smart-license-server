<?php
/**
 * Conditional function file
 */
use SmartLicenseServer\Exceptions\Exception;

if ( ! function_exists( 'is_json' ) ) {
    /**
     * Check if a given string is a valid JSON.
     *
     * @param  mixed  $value  The value to test.
     * @return bool
     */
    function is_json( $value ) {
        if ( ! is_string( $value ) ) {
            return false;
        }

        json_decode( $value );

        return ( json_last_error() === JSON_ERROR_NONE );
    }    
}

if ( ! function_exists( 'is_base64_encoded' ) ) {
    /**
     * Check if a string is base64 encoded.
     *
     * @param  mixed  $value
     * @return bool
     */
    function is_base64_encoded( $value ) {
        if ( ! is_string( $value ) ) {
            return false;
        }

        // Basic pattern: only A-Z, a-z, 0-9, +, /, and =
        if ( ! preg_match( '/^[A-Za-z0-9\/\r\n+]*={0,2}$/', $value ) ) {
            return false;
        }

        // Validate by decoding and re-encoding
        $decoded = base64_decode( $value, true );

        return $decoded !== false && base64_encode( $decoded ) === $value;
    }
}

/**
 * Check whether the given value is an error instance.
 * @param mixed $value The value to check.
 * @return bool True if the value is an instance of a known error class, false otherwise.
 */
function is_smliser_error( $value ) {
    if ( function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) {
        return true;
        
    } elseif ( $value instanceof WP_Error ) {
        return true;
    }

    return ( $value instanceof Exception );
}