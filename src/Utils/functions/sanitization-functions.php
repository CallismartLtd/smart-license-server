<?php
/**
 * Sanitization functions file
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

use SmartLicenseServer\Utils\Sanitizer;

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
 * @param string $input
 * @return string
 */
function escHtml( mixed $input ) : string {
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