<?php
/**
 * URL manager class file.
 * 
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Contracts\URLManagerInterface;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Manages URLs for this application
 */
class URLManager implements URLManagerInterface {

    public function __construct(
        protected Settings $settings,
        protected Request $request,
        protected URL $app_url,
        protected URL $admin_base_url,
        protected URL $assets_url
        
    ) {}

    /**
     * Get the canonical app url
     * 
     * @param string $path  Optional path to append.
     * @param array $query  Optional query params
     */
    public function url( string $path = '', array $query = [] ) : URL {
        $basename       = \basename( $path );
        $trail_slash    = ! \str_contains( $basename,  '.' );

        return $this->app_url->append_path( $path, $trail_slash )->add_query_params( $query );
    }

    /**
     * Get the admin url prefix
     * 
     * @return string
     */
    public function admin_url_prefix() : string {
        return (string) $this->settings->get( static::ADMIN_URL_PREFIX_KEY, 'smliser-admin' );
    }

    /**
     * Get the admin url.
     * 
     * @param string $page  Optional page path to append.
     * @param string $tab   Optional submenu tab path to append.
     * @param array $query  Optional query params.
     */
    public function admin_url( string $page = '', string $tab = '', array $query = [] ) : URL {
        return $this->admin_base_url
            ->append_path( $this->admin_url_prefix() )
            ->append_path( $page )->append_path( $tab )
            ->add_query_params( $query );
    }

    /**
     * Get the base URL to public assets.
     * 
     * @param string $path  Optional path to append.
     * @param array $query  Optional query params.
     */
    public function assets_url( string $path = '', array $query = [] ) : URL {
        return $this->assets_url->append_path( $path )->add_query_params( $query );
    }

    /**
     * Get the login url prefix
     * 
     * @return string
     */
    public function login_url_prefix() : string {
        return (string) $this->settings->get( static::LOGIN_URL_PREFIX_KEY, 'auth' );
    }

    /**
     * Get the login URL.
     * 
     * @param string $path  Optional path to append.
     * @param array $query  Optional query params.
     * @return URL
     */
    public function login_url( string $path = '', array $query = [] ) : URL {
        return $this->url( $this->login_url_prefix(), $query )
            ->append_path( $path, true );
    }

    /**
     * Get the logout url prefix.
     * 
     * @return string
     */
    public function logout_url_prefix() : string {
        return (string) $this->settings->get( static::LOGOUT_URL_PREFIX_KEY, 'logout' );
    }

    /**
     * Get the logout url.
     * 
     * @param array $query
     * @return URL
     */
    public function logout_url( array $query = [] ) : URL {
        return $this->url( $this->logout_url_prefix(), $query );
    }

    /**
     * Get the client dashboard url prefix
     * 
     * @return string
     */
    public function client_dasboard_url_prefix() : string {
        return (string) $this->settings->get( static::CLIENT_DASHBOARD_URL_PREFIX_KEY, 'client-dashboard' );
    }

    /**
     * Get the the client dashboard url.
     * 
     * @param string $path  Optional path to append.
     * @param array $query  Optional query params.
     */
    public function client_dashboard_url( string $path = '', array $query = [] ) : URL {
        return $this->url( $this->client_dasboard_url_prefix(), $query )
            ->append_path( $path );
    }

    /**
     * Get the prefix for the downloads URL.
     * 
     * @return string
     */
    public function downloads_url_prefix() : string {
        return (string) $this->settings->get( static::DOWNLOADS_URL_PREFIX_KEY, 'downloads' );
    }

    /**
     * Get the downloads url.
     * 
     * @param string $path  Optional path to append.
     * @param array $query  Optional query params.
     */
    public function downloads_url( string $path = '', array $query = [] ) : URL {
        return $this->url( $this->downloads_url_prefix(), $query )
            ->append_path( $path );
    }

    /**
     * Get the repository url prefix
     * 
     * @return string 
     */
    public function repository_url_prefix() : string {
        return (string) $this->settings->get( static::REPOSITORY_URL_PREFIX_KEY, 'repository' );
    }

    /**
     * Get the repository url.
     * 
     * @param string $path  Optional path to append.
     * @param array $query  Optional query params.
     * 
     * @return URL
     */
    public function repository_url( string $path = '', array $query = [] ) : URL {
        return $this->url( $this->repository_url_prefix(), $query )
            ->append_path( $path );
    }

    /**
     * Get document download url.
     * 
     * @param int $id       The document ID.
     * @param array $query  Optional query params.
     * @return URL
     */
    public function document_download_url( int $id, array $query = [] ) : URL {
        return $this->downloads_url( 'document', $query )
            ->append_path( "license-document-{$id}.txt" );
    }

    /**
     * Admin repository page URL.
     * 
     * @param string $tab Optional submenu tab to append.
     * @param array $query
     */
    public function admin_repo_url( string $tab = '', array $query = [] ) : URL {
        return $this->admin_url( 'repository', $tab, $query );
    }

    /**
     * Admin settings page URL.
     * 
     * @param string $tab Optional submenu tab to append.
     * @param array $query
     */
    public function admin_options_url( string $tab = '', array $query = [] ) : URL {
        return $this->admin_url( 'settings', $tab, $query );
    }

    /**
     * Get the admin license URL.
     * 
     * @param string $tab   Optional tab to append.
     * @param array $query  Optional query params.
     */
    public function admin_license_page_url( string $tab = '', array $query = [] ) : URL {
        return $this->admin_url( 'licenses', $tab, $query );
    }

    /**
     * Get the admin broadcasts page URL.
     * 
     * @param string $tab   Optional tab to append.
     * @param array $query  Optional query params.
     */
    public function admin_broadcats_page_url( string $tab = '', array $query = [] ) : URL {
        return $this->admin_url( 'broadcasts', $tab, $query );
    }

    /**
     * Get the admin accounts page URL.
     * 
     * @param string $tab   Optional tab to append.
     * @param array $query  Optional query params.
     */
    public function admin_accounts_page_url( string $tab = '', array $query = [] ) : URL {
        return $this->admin_url( 'accounts', $tab, $query );
    }

    /**
     * Get app assets URLs
     *
     * @param string $app_type  The app type.
     * @param string $app_slug  The app slug.
     * @param string $filename  Optional asset filename.
     */
    public function app_asset_url( string $app_type, string $app_slug, string $filename = '' ) : URL {
        $path   = "$app_type/$app_slug/assets";
        return $this->repository_url( $path )
            ->append_path( $filename );
    }

    /**
     * Get the uploads URL prefix.
     * 
     * @return string
     */
    public function uploads_url_prefix() : string {
        return (string) $this->settings->get( static::UPLOADS_URL_PREFIX_KEY, 'smliser-uploads' );
    }
    
    /**
     * Get the uploads url.
     * 
     * Contructs the URL to get resource from the uploads directory.
     * 
     * @param $path
     * @return URL
     */
    function uploads_url( string $path  = '' ) : URL {        
        return url( $this->uploads_url_prefix() )
            ->append_path( $path );
    }

    /**
     * Get the avatar
     * 
     * @param string $filename_hash
     * @param string $avatar_type
     * @return URL
     */
    public function avatar_url( string $filename_hash, string $avatar_type ) : URL {
        $path       = FileSystemHelper::join_path( SMLISER_UPLOADS_DIR, 'avatars', $avatar_type, $filename_hash );

        if ( ! file_exists( $path ) ) {
            return $this->assets_url( smliser_get_placeholder_icon( 'avatar' ) );
        }
        
        $type       = smliser_pluralize( str_replace( '_', '-', $avatar_type ) );

        return $this->uploads_url( 'avatars' )
            ->append_path( $type )
            ->append_path( $filename_hash );
    }

    /**
     * Get app downloads url.
     * 
     * @param string $app_type
     * @param string $app_slug
     * @return URL
     */
    public function app_downloads_url( string $app_type, string $app_slug ) : URL {
        return $this->downloads_url( $app_type )
            ->append_path( "{$app_slug}.zip" );
    }

    /**
     * Get the URL to download an app artifact.
     * 
     * @param string $app_type The app type.
     * @param string $app_slug The app slug.
     * @param string $filename The artifact file name
     * 
     * @return URL
     */
    public function app_artifact_download_url( string $app_type, string $app_slug, string $filename ) : URL {
        return $this->downloads_url( $app_type )
            ->append_path( $app_slug )
            ->append_path( 'artifacts' )
            ->append_path( $filename );
    }

    /**
     * Get the preview/home url of a hosted application.
     * 
     * @param string $app_type
     * @param string $app_slug
     */
    public function app_repository_url( string $app_type, string $app_slug ) : URL {
        return $this->repository_url( $app_type )
            ->append_path( $app_slug );
    }
}