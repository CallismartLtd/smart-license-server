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
function smliser_safe_json_encode( mixed $data, int $flags = 0, int $depth = 512 ) {
	if ( function_exists( 'wp_json_encode' ) ) {
		return wp_json_encode( $data, $flags, $depth );
	}

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
function smliser_send_json( $data, $status_code = 200, $flags = 0 ) {
    if ( function_exists( 'wp_send_json' ) ) {
        wp_send_json( $data, $status_code, $flags );
    }

    if ( ! headers_sent() ) {
        status_header( $status_code );
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
    if ( function_exists( 'wp_send_json_error' ) && ( ! $data instanceof Exception ) ) {
        wp_send_json_error( $data, $status_code, $flags );
    }

    $response = array( 'success' => false );

    if ( isset( $data ) ) {
        if ( is_smliser_error( $data ) ) {
            /**
             * @var SmartLicenseServer\Exception $data
             */
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
function smliser_send_json_success( $data = null, $status_code = 200, $flags = 0 ) {
    if ( function_exists( 'wp_send_json_success' ) ) {
        wp_send_json_success( $data, $status_code, $flags );
    }

    $response = array( 'success' => true );

    if ( isset( $data ) ) {
        $response['data'] = $data;
    }

    smliser_send_json( $response, $status_code, $flags );
}

/**
 * Recursively clean strings to ensure valid UTF-8, used for `smliser_safe_json_encode()` handling.
 *
 * @param mixed $data Data to sanitize.
 * @return mixed UTF-8 cleaned data.
 */
function smliser_utf8ize( mixed $data ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			unset( $data[ $key ] );
			$data[ smliser_utf8ize( $key ) ] = smliser_utf8ize( $value );
		}
	} elseif ( is_string( $data ) ) {
		return mb_convert_encoding( $data, 'UTF-8', 'UTF-8' );
	} elseif ( is_object( $data ) ) {
		$vars = get_object_vars( $data );
		foreach ( $vars as $key => $value ) {
			$data->$key = smliser_utf8ize( $value );
		}
	}

	return $data;
}