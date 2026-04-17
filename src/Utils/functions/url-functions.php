<?php
/**
 * The URL functions API.
 */

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\FileSystem\FileSystemHelper;

/**
 * Get the web application URL.
 * 
 * @param string $path Path(optional).
 * @param array<string, string> $params Associative array of query params.
 * @return URL
 */
function url( string $path = '', array $params = [] ) : URL {
    return smliser_envProvider()->url( $path, $params );
}

/**
 * Get the admin URL.
 * 
 * @param string $path Path(optional).
 * @param array<string, string> $params Associative array of query params.
 * @return URL
 */
function adminUrl( string $path = '', array $params = [] ) : URL {
    return smliser_envProvider()->adminUrl( $path, $params );
}

/**
 * Assets url
 * 
 * @param string $path Path(optional).
 * @param array<string, string> $params Associative array of query params.
 */
function assetsUrl( string $path = '', array $params = [] ) :URL {
    return smliser_envProvider()->assetsUrl( $path, $params );
}

/**
 * Get the REST API URL.
 * 
 * @param string $path Path(optional).
 * @param array<string, string> $params Associative array of query params.
 * @return URL
 */
function restAPIUrl( string $path = '', array $params = [] ) : URL {
    return smliser_envProvider()->restAPIUrl( $path, $params );
}

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

/**
 * Action url constructor for admin license page
 * 
 * @param string $action Action query variable for the page.
 * @param int $license_id   The ID of the license.
 * @return \SmartLicenseServer\Core\URL
 */
function smliser_license_admin_action_page( $action = 'add-new', $license_id = '' ) : URL {
    if ( 'edit' === $action || 'view' === $action ) {
        $url = smliser_license_page()->add_query_params( array(
            'tab'        => $action,
            'license_id'    => $license_id,
        ));
    } else {
        $url = smliser_license_page()->add_query_param( 'tab', $action );
    }

    return $url;
}

/**
 * Action url constructor for admin repository tabs.
 * 
 * @param string $tab The tab.
 * @param array $args An associative array the will be passed to add_query_args. 
 */
function smliser_admin_repo_tab( $tab = 'add-new', $args = array() ) : URL {

    if ( ! is_array( $args ) ) {
        if ( is_int( $args ) ) {
            $args = array( 'app_id' => $args );
        } else if ( is_string( $args ) ) {
            $args = array( 'type' => $args );
        }
    }
    
    $args['tab'] = $tab;

    return smliser_repository_url( 'admin' )->add_query_params( $args );
}

/**
 * Get access control page url.
 * 
 * @return URL
 */
function smliser_access_control_page_url() : URL {
    return adminUrl( 'admin.php' )->add_query_params([
        'page'  => 'smliser-accounts',
    ]);
}

/**
 * Get the URL origin.
 *
 * @param string $url The URL to parse.
 * @return string The base website address.
 */
function smliser_url_origin( string $url ) {
    $url    = new URL( $url );
    return $url->get_origin();
}

/**
 * Get the base downloads url prefix.
 */
function smliser_get_download_url_prefix() : string {
    return (string) smliser_settings()->get( 'download_url_prefix', 'downloads', true );
}

/**
 * Get the the base repository slug.
 * 
 * @return string
 */
function smliser_get_repository_url_prefix() : string {
    return (string) smliser_settings()->get( 'repository_url_prefix', 'repository', true );
}

/**
 * Get the client dashboard URL prefix.
 * 
 * @return string
 */
function smliser_get_client_dashboard_url_prefix() : string {
    return (string) smliser_settings()->get( 'client_dashboard_url_prefix', 'client-dashboard', true );
}

/**
 * Get the client dashboard URL.
 * 
 * @param string $path Optional path to append to the dashboard URL.
 * @param array<string, string> $params Associative array of query params.
 * @return URL
 */
function smliser_client_dashboard_url( string $path = '', array $params = [] ) : URL {
    return url( smliser_get_client_dashboard_url_prefix() )
    ->append_path( $path )
    ->add_query_params( $params );
}

/**
 * Get document download URL
 * 
 * @param int $id The document ID
 * @return URL
 */
function smliser_document_download_url( int $id = 0 ) : URL {
    $downloads_slug = smliser_get_download_url_prefix();
    $path           = implode( '/', [$downloads_slug,'document', $id] );

    return url( $path );
}

/**
 * Get the URL for a given app asset.
 *
 * @param string $type     App type ('plugin' or 'theme').
 * @param string $slug     The app slug.
 * @param string $filename The asset file name (e.g. screenshot-1.png).
 * @return string
 */
function smliser_get_asset_url( $type, $slug, $filename ) {
    $path   = "$type/$slug/assets";
    return smliser_repository_url()
    ->append_path( $path )
    ->append_path( $filename );
}

/**
 * Get the uploads url.
 * 
 * @param $path
 * @return URL
 */
function smliser_uploads_url( string $path  = '' ) : URL {
    $rel_path   = 'smliser-uploads';
    $path       = FileSystemHelper::join_path( $rel_path, $path );
    
    return url( $path );
}

/**
 * Get avatar URL.
 * 
 * @param $filename_hash    The MD5 hash name of the avatar.
 * @param $type             The avatar type.
 * @return URL
 */
function smliser_avatar_url( string $filename_hash, string $type ) : string|URL {
    $type       = smliser_pluralize( str_replace( '_', '-', $type ) );
    $path       = FileSystemHelper::join_path( 'avatars', $type );

    if ( is_smliser_error( $path ) ) {
        return '';
    }

    $path       = FileSystemHelper::join_path( $path, $filename_hash );
    $abs_path   = FileSystemHelper::join_path( SMLISER_UPLOADS_DIR, $path );

    if ( is_smliser_error( $path ) || ! FileSystemHelper::is_valid_file( $abs_path ) ) {
        return '';
    }

    $avatar_url = smliser_uploads_url( $path );
    return $avatar_url;
}

/**
 * Get current page URL.
 *
 * Returns an empty string if it cannot generate a URL.
 * @return \SmartLicenseServer\Core\URL
 */
function smliser_get_current_url() : URL {
    static $current_url    = null;
    
    if ( is_null( $current_url ) ) {
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $path   = parse_url( $uri, PHP_URL_PATH );
        $params = parse_url( $uri, PHP_URL_QUERY )?: [];
        
        parse_str( $params, $query );
        $current_url = url( $path, $query );
    }

    return $current_url;
}

/**
 * Sanitize the given URL
 * 
 * @param string $url
 */
function smliser_sanitize_url( $url ) : string {
    return ( new URL( $url ) )
    ->sanitize()
    ->url();
}