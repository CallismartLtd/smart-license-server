<?php
/**
 * OAUTH template controller file
 * This file determines the appropriate template file to serve base on current user's login status,
 * and query parameter validation
 */

defined( 'SMLISER_ABSPATH' ) || exit;

// Define the default values for the required parameters.
$default_params = array(
    'scope'         => '',
    'app_name'      => '',
    'return_url'    => '',
    'callback_url'  => '',
    'user_id'       => ''
);

// Filter $_GET to include only expected keys.
$filtered_get = array_intersect_key( $_GET, $default_params );

// Merge the default values with the filtered $_GET parameters.
$merged_params = array_merge( $default_params, $filtered_get );

// Sanitize all parameters.
$sanitized_params = array_map( function( $value ) {
    return sanitize_text_field( unslash( $value ) );
}, $merged_params );

// Check for missing required parameters and use smliser_abort_request() to show a message.
foreach ( $default_params as $key => $value ) {
    if ( empty( $sanitized_params[$key] ) ) {
        smliser_abort_request( sprintf( 'Missing required parameter: "%s"', esc_html( $key ) ) );
    }
}

$permission = 'Read';
$verb       = 'View';
if ( 'read_write' === $sanitized_params['scope'] ) {
    $permission = 'Read/Write';
    $verb       = 'View and manage';
} elseif( 'write' === $sanitized_params['scope'] ) {
    $permission = 'Write';
    $verb       = 'Create';
} elseif ( 'read' !== $sanitized_params['scope'] && 'read_write' !== $sanitized_params['scope'] && 'write' !== $sanitized_params['scope'] ) {
    smliser_abort_request( sprintf( 'Invalid scope: "%s".', esc_html( $sanitized_params['scope'] ) ) );
}


if ( ! is_user_logged_in() ) {
    $login_form             = SMLISER_PATH . 'templates/auth/auth-login.php';
    $theme_login_template   = get_template_directory() . '/smliser/auth/auth-login.php';

    require_once file_exists( $theme_login_template ) ? $theme_login_template : $login_form;
} else {
    $auth_template          = SMLISER_PATH . 'templates/auth/auth-temp.php';
    $theme_auth_template    = get_template_directory() . '/smliser/auth/auth-temp.php';
    require_once file_exists( $theme_auth_template ) ? $theme_auth_template : $auth_template;
}