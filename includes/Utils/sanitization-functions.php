<?php
/**
 * Sanitization functions file
 * 
 * @author Callistus Nwachukwu
 */

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Recursively unslash the give data
 * 
 * @param string|array $data
 */
function unslash( $data ) {
    return wp_unslash( $data );
}