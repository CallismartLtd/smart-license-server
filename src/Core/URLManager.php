<?php
/**
 * URL manager class file.
 * 
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core;

use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Manages URLs for this application
 */
class URLManager {
    /**
     * The login URL prefix.
     */
    public const LOGIN_URL_PREFIX_KEY               = 'login_url_prefix';
    public const LOGOUT_URL_PREFIX_KEY              = 'logout_url_prefix';
    public const ADMIN_URL_PREFIX_KEY               = 'admin_url_prefix';
    public const CLIENT_DASHBOARD_URL_PREFIX_KEY    = 'client_dashboard_url_prefix';
    public const REPOSITORY_URL_PREFIX_KEY          = 'repository_url_prefix';
    public const DOWNLOADS_URL_PREFIX_KEY           = 'downloads_url_prefix';
    // public const LOGIN_URL_PREFIX_KEY   = '';

    public function __construct(
        protected Settings $settings,
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
     * @param string $path  Optional path to append.
     * @param array $query  Optional query params.
     */
    public function admin_url( string $path = '', array $query = [] ) : URL {
        return $this->admin_base_url
            ->append_path( $this->admin_url_prefix() )
            ->append_path( $path )
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
}