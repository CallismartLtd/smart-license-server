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
 * Escape output in HTML
 * 
 * @param string $value
 * @return string
 */
function escHtml( string $input ) : string {
    return Sanitizer::esc_html( $input );
}