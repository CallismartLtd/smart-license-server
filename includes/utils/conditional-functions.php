<?php
/**
 * Conditional function file
 */

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

