<?php
/**
 * The URL manager interface.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
namespace SmartLicenseServer\Contracts;

use SmartLicenseServer\Core\URL;

interface URLManagerInterface {

    public const LOGIN_URL_PREFIX_KEY               = 'login_url_prefix';
    public const LOGOUT_URL_PREFIX_KEY              = 'logout_url_prefix';
    public const ADMIN_URL_PREFIX_KEY               = 'admin_url_prefix';
    public const CLIENT_DASHBOARD_URL_PREFIX_KEY    = 'client_dashboard_url_prefix';
    public const REPOSITORY_URL_PREFIX_KEY          = 'repository_url_prefix';
    public const DOWNLOADS_URL_PREFIX_KEY           = 'downloads_url_prefix';
    public const ADMIN_DOWNLOADS_URL_PREFIX_KEY     = 'admin_downloads_url_prefix';
    public const UPLOADS_URL_PREFIX_KEY             = 'uploads_url_prefix';


    /*
     * -------------------------------------------------
     * CORE / BASE URLS
     * -------------------------------------------------
     */

    /**
     * Get the canonical app URL.
     *
     * @param string $path  Optional path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function url( string $path = '', array $query = [] ) : URL;

    /**
     * Get the base URL to public assets.
     *
     * @param string $path  Optional path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function assets_url( string $path = '', array $query = [] ) : URL;


    /*
     * -------------------------------------------------
     * ADMIN
     * -------------------------------------------------
     */

    /**
     * Get the admin URL prefix.
     *
     * @return string
     */
    public function admin_url_prefix() : string;

    /**
     * Get the admin URL.
     *
     * @param string $page  Optional page path to append.
     * @param string $tab   Optional submenu tab path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function admin_url(
        string $page = '',
        string $tab = '',
        array $query = []
    ) : URL;


    /*
     * -------------------------------------------------
     * ADMIN PAGES
     * -------------------------------------------------
     */

    /**
     * Get the admin repository page URL.
     *
     * @param string $tab   Optional submenu tab to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function admin_repo_url( string $tab = '', array $query = [] ) : URL;

    /**
     * Get the admin settings page URL.
     *
     * @param string $tab   Optional submenu tab to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function admin_options_url( string $tab = '', array $query = [] ) : URL;

    /**
     * Get the admin license page URL.
     *
     * @param string $tab   Optional tab to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function admin_license_page_url( string $tab = '', array $query = [] ) : URL;

    /**
     * Get the admin broadcasts page URL.
     *
     * @param string $tab   Optional tab to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function admin_broadcasts_page_url( string $tab = '', array $query = [] ) : URL;

    /**
     * Get the admin accounts page URL.
     *
     * @param string $tab   Optional tab to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function admin_accounts_page_url( string $tab = '', array $query = [] ) : URL;


    /*
     * -------------------------------------------------
     * AUTH
     * -------------------------------------------------
     */

    /**
     * Get the login URL prefix.
     *
     * @return string
     */
    public function login_url_prefix() : string;

    /**
     * Get the login URL.
     *
     * @param string $path  Optional path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function login_url( string $path = '', array $query = [] ) : URL;

    /**
     * Get the logout URL prefix.
     *
     * @return string
     */
    public function logout_url_prefix() : string;

    /**
     * Get the logout URL.
     *
     * @param array $query Optional query params.
     *
     * @return URL
     */
    public function logout_url( array $query = [] ) : URL;


    /*
     * -------------------------------------------------
     * CLIENT DASHBOARD
     * -------------------------------------------------
     */

    /**
     * Get the client dashboard URL prefix.
     *
     * @return string
     */
    public function client_dashboard_url_prefix() : string;

    /**
     * Get the client dashboard URL.
     *
     * @param string $path  Optional path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function client_dashboard_url( string $path = '', array $query = [] ) : URL;


    /*
     * -------------------------------------------------
     * DOWNLOADS
     * -------------------------------------------------
     */

    /**
     * Get the prefix for the downloads URL.
     *
     * @return string
     */
    public function downloads_url_prefix() : string;

    /**
     * Get the prefix for the admin downloads URL.
     *
     * @return string
     */
    public function admin_downloads_url_prefix() : string;

    /**
     * Get the downloads URL.
     *
     * @param string $path  Optional path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function downloads_url( string $path = '', array $query = [] ) : URL;

    /**
     * Get the admin downloads URL.
     *
     * @param string $path  Optional path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function admin_downloads_url( string $path = '', array $query = [] ) : URL;

    /**
     * Get the document download URL.
     *
     * @param int   $id    The document ID.
     * @param array $query Optional query params.
     *
     * @return URL
     */
    public function document_download_url( int $id, array $query = [] ) : URL;

    /**
     * Get the admin document download URL.
     *
     * @param int   $id    The document ID.
     * @param array $query Optional query params.
     *
     * @return URL
     */
    public function admin_document_download_url( int $id, array $query = [] ) : URL;


    /*
     * -------------------------------------------------
     * REPOSITORY
     * -------------------------------------------------
     */

    /**
     * Get the repository URL prefix.
     *
     * @return string
     */
    public function repository_url_prefix() : string;

    /**
     * Get the repository URL.
     *
     * @param string $path  Optional path to append.
     * @param array  $query Optional query params.
     *
     * @return URL
     */
    public function repository_url( string $path = '', array $query = [] ) : URL;


    /*
     * -------------------------------------------------
     * UPLOADS
     * -------------------------------------------------
     */

    /**
     * Get the uploads URL prefix.
     *
     * @return string
     */
    public function uploads_url_prefix() : string;

    /**
     * Get the uploads URL.
     *
     * Constructs the URL to get a resource from the uploads directory.
     *
     * @param string $path Optional path to append.
     *
     * @return URL
     */
    public function uploads_url( string $path = '' ) : URL;


    /*
     * -------------------------------------------------
     * APPS
     * -------------------------------------------------
     */

    /**
     * Get an app asset URL.
     *
     * @param string $app_type The app type.
     * @param string $app_slug The app slug.
     * @param string $filename Optional asset filename.
     *
     * @return URL
     */
    public function app_asset_url(
        string $app_type,
        string $app_slug,
        string $filename = ''
    ) : URL;

    /**
     * Get an app download URL.
     *
     * @param string $app_type The app type.
     * @param string $app_slug The app slug.
     *
     * @return URL
     */
    public function app_downloads_url(
        string $app_type,
        string $app_slug
    ) : URL;

    /**
     * Get an app download URL for administrators.
     *
     * @param string     $app_type The app type.
     * @param int|string $app_id   The app ID.
     *
     * @return URL
     */
    public function admin_app_downloads_url(
        string $app_type,
        int|string $app_id
    ) : URL;

    /**
     * Get the URL to download an app artifact.
     *
     * @param string $app_type The app type.
     * @param string $app_slug The app slug.
     * @param string $filename The artifact file name.
     *
     * @return URL
     */
    public function app_artifact_download_url(
        string $app_type,
        string $app_slug,
        string $filename
    ) : URL;

    /**
     * Get the preview/home URL of a hosted application.
     *
     * @param string $app_type The app type.
     * @param string $app_slug The app slug.
     *
     * @return URL
     */
    public function app_repository_url(
        string $app_type,
        string $app_slug
    ) : URL;
}