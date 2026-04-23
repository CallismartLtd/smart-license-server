<?php
/**
 * Sanitization functions file
 * 
 * @author Callistus Nwachukwu
 */

use SmartLicenseServer\Utils\Sanitizer;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Recursively unslash the give data
 * 
 * @param string|array $data
 */
function unslash( $data ) {
    return Sanitizer::unslash( $data );
}

/**
 * Escape a string for safe HTML output.
 * 
 * @param string $value
 * @return string
 */
function escHtml( string $input ) : string {
    return Sanitizer::esc_html( $input );
}

/**
 * Escape a url for safe HTML output.
 * 
 * @param string $url
 * @return string
 */
function escUrl( string $url ) : string {
    return Sanitizer::esc_url( $url );
}