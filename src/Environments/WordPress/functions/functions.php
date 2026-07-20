<?php
/**
 * WordPress-specific functions.
 */
namespace SmartLicenseServer\Environments\WordPress;

/**
 * Load authentication template.
 *
 * @param  string $template
 * @return string
 */
function load_auth_template( string $template ): string {
    global $wp_query;

    if ( isset( $wp_query->query_vars['smliser_auth'] ) ) {
        $template = SMLISER_PATH . 'templates/auth/auth-controller.php';
    }

    return $template;
}

/**
 * Load app authentication page header
 */
function smliser_load_auth_header() {
    $theme_template_dir = get_template_directory() . '/smliser/auth/auth-header.php';
    include_once file_exists( $theme_template_dir ) ? $theme_template_dir : SMLISER_PATH . 'templates/auth/auth-header.php';
}

/**
 * Load app authentication page footer
 */
function smliser_load_auth_footer() {
    $theme_template_dir = get_template_directory() . '/smliser/auth/auth-footer.php';
    include_once file_exists( $theme_template_dir ) ? $theme_template_dir : SMLISER_PATH . 'templates/auth/auth-footer.php';
}