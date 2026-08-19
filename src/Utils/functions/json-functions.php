<?php
/**
 * JSON functions API
 */

/**
 * Safely encodes data to JSON, emulating WordPress' wp_json_encode().
 *
 * Ensures consistent encoding across environments, handling
 * non-UTF8 characters and partial encoding failures gracefully.
 *
 * @param mixed $data  Data to encode.
 * @param int   $flags Optional. Bitmask of JSON encode options. Default 0.
 * @param int   $depth Optional. Set the maximum depth. Default 512.
 * 
 * @return string|false The JSON encoded string, or false on failure.
 */
function smliser_safe_json_encode( mixed $data, int $flags = 0, int $depth = 512 ) : string|false {

	// Attempt normal JSON encoding first.
	$json = json_encode( $data, $flags, $depth );

	if ( false !== $json && JSON_ERROR_NONE === json_last_error() ) {
		return $json;
	}

	// If encoding fails, try to clean invalid UTF-8 recursively.
	$clean_data = smliser_utf8ize( $data );

	$json = json_encode( $clean_data, $flags, $depth );

	if ( false !== $json && JSON_ERROR_NONE === json_last_error() ) {
		return $json;
	}

	return false;
}

/**
 * Send a json response
 * 
 * @param mixed $data Data to encode and send.
 */
function smliser_send_json( $data, $status_code = 200, $flags = 0 ) : never {
    if ( ! headers_sent() ) {
        http_response_code( $status_code );
        header( 'Content-Type: application/json; charset=' . smliser_settings()->get( 'charset', 'UTF-8', false ) );
    }

    echo smliser_safe_json_encode( $data, $flags ); // phpcs:ignore
    exit;
}

/**
 * Send json error response
 * 
 * @param mixed $data Data to encode and send.
 * @param int $status_code HTTP status code.
 * @param int $flags JSON encode flags.
 */
function smliser_send_json_error( $data = null, $status_code = 400, $flags = 0 ) {
    $response = array( 'success' => false );

    if ( isset( $data ) ) {
        if ( $data instanceof \SmartLicenseServer\Exceptions\Exception ) {
            $error_data = $data->to_array();
            if ( smliser_debug_enabled() ) {
                unset( $error_data['trace'] );
            } 

            $response['data'] = $error_data;

            if ( isset( $data->get_error_data()['status'] ) ) {
                $status_code = $data->get_error_data()['status'];
            }
        } else {
            $response['data'] = $data;
        }
    }

    smliser_send_json( $response, $status_code, $flags );
}

/**
 * Send json success response
 * 
 * @param mixed $data Data to encode and send.
 * @param int $status_code HTTP status code.
 * @param int $flags JSON encode flags.
 */
function smliser_send_json_success( mixed $data = null, $status_code = 200, $flags = 0 ) {
    $response = ['success' => true];

    if ( isset( $data ) ) {
        $response['data'] = $data;
    }

    smliser_send_json( $response, $status_code, $flags );
}

/**
 * Recursively clean strings to ensure valid UTF-8 for safe JSON encoding.
 *
 * @param mixed $data Data to sanitize.
 * @return mixed UTF-8 cleaned data.
 */
function smliser_utf8ize( mixed $data ): mixed {
    if ( is_string( $data ) ) {
        // Native, high-performance C-level UTF-8 scrubbing (PHP 7.2+).
        return function_exists( 'mb_scrub' ) 
            ? mb_scrub( $data, 'UTF-8' ) 
            : mb_convert_encoding( $data, 'UTF-8', 'UTF-8' );
    }

    if ( is_array( $data ) ) {
        $clean = [];
        foreach ( $data as $key => $value ) {
            $clean_key = is_string( $key ) ? smliser_utf8ize( $key ) : $key;
            $clean[ $clean_key ] = smliser_utf8ize( $value );
        }
        return $clean;
    }

    if ( is_object( $data ) ) {
        // Respect objects with custom JSON serialization.
        if ( $data instanceof \JsonSerializable ) {
            return smliser_utf8ize( $data->jsonSerialize() );
        }

        // Clone to avoid mutating the original object instance.
        $cloned = clone $data;

        // Clean public properties for standard stdClass objects.
        foreach ( get_object_vars( $data ) as $key => $value ) {
            $cloned->$key = smliser_utf8ize( $value );
        }

        return $cloned;
    }

    // Scalar types (int, float, bool, null, resources) return unchanged.
    return $data;
}