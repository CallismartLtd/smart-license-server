<?php
/**
 * The URL functions API.
 */

use SmartLicenseServer\Core\URL;

/**
 * The admin URL for the licenses page.
 * 
 * @return URL
 */
function smliser_license_page() : URL {
    return adminUrl( 'admin.php', ['page' => 'smliser-licenses'] );
}

/**
 * Get the repository URL.
 * 
 * @param string $context The URL context, use `admin` for the admin url
 */
function smliser_repository_url( string $context = '' ) : URL {

    if ( 'admin' === $context ) {
        $url = adminUrl( 'admin.php' )
        ->add_query_param( 'page', 'smliser-repository' );
    } else {
        $url    = url( smliser_get_repository_url_prefix() );
    }

    return $url;
}

/**
 * Bulk messages URL.
 */
function smliser_bulk_messages_url() : URL {
    $url    = adminUrl( 'admin.php' )
    ->add_query_params([
        'page'  => 'smliser-bulk-messages'
    ]);

    return $url;
}

/**
 * The settings page URL.
 */
function smliser_options_url() : URL {
    $url    = adminUrl( 'admin.php' )
    ->add_query_params([
        'page'  => 'smliser-settings'
    ]);

    return $url;
}