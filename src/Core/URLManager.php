<?php
/**
 * URL manager class file.
 * 
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Contracts\URLManagerInterface;

/**
 * Manages URLs for this application.
 *
 * This is the canonical URL manager for this application, environment
 * providers are required either bind this class to the DIC or swap it with
 * their specific implementation.
 *
 * @method URL url( string $path = '', array $query = [] )
 * @method URL assets_url( string $path = '', array $query = [] )
 *
 * @method string admin_url_prefix()
 * @method URL admin_url( string $page = '', string $tab = '', array $query = [] )
 *
 * @method URL admin_repo_url( string $tab = '', array $query = [] )
 * @method URL admin_options_url( string $tab = '', array $query = [] )
 * @method URL admin_license_page_url( string $tab = '', array $query = [] )
 * @method URL admin_broadcasts_page_url( string $tab = '', array $query = [] )
 * @method URL admin_accounts_page_url( string $tab = '', array $query = [] )
 *
 * @method string login_url_prefix()
 * @method URL login_url( string $path = '', array $query = [] )
 * @method string logout_url_prefix()
 * @method URL logout_url( array $query = [] )
 *
 * @method string client_dashboard_url_prefix()
 * @method URL client_dashboard_url( string $path = '', array $query = [] )
 *
 * @method string downloads_url_prefix()
 * @method string admin_downloads_url_prefix()
 * @method URL downloads_url( string $path = '', array $query = [] )
 * @method URL admin_downloads_url( string $path = '', array $query = [] )
 * @method URL document_download_url( int $id, array $query = [] )
 * @method URL admin_document_download_url( int $id, array $query = [] )
 *
 * @method string repository_url_prefix()
 * @method URL repository_url( string $path = '', array $query = [] )
 *
 * @method string uploads_url_prefix()
 * @method URL uploads_url( string $path = '' )
 *
 * @method URL app_asset_url( string $app_type, string $app_slug, string $filename = '' )
 * @method URL app_downloads_url( string $app_type, string $app_slug )
 * @method URL admin_app_downloads_url( string $app_type, int|string $app_id )
 * @method URL app_artifact_download_url( string $app_type, string $app_slug, string $filename )
 * @method URL app_repository_url( string $app_type, string $app_slug )
 */
class URLManager {

    public function __construct(
        protected URLManagerInterface $adapter
        
    ) {}

    /**
     * Swap the current adapter.
     * 
     * This method is used by the dev server API to update the
     * url manager.
     * 
     * @access private
     * @param URLManagerInterface $adapter
     */
    public function set_adapter( URLManagerInterface $adapter ) : void {
        $this->adapter   = $adapter;
    }

    /**
     * Proxy calls to the adapter methods.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     *
     * @return mixed
     *
     * @throws \ErrorException If the method does not exist in the adapter.
     */
    public function __call( string $method, array $args ) {
        if ( method_exists( $this->adapter, $method ) ) {
            return call_user_func_array( [ $this->adapter, $method ], $args );
        }

        $backtrace  = \debug_backtrace( \DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
        $file       = $backtrace[0]['file'] ?? null;
        $line       = $backtrace[0]['line'] ?? null;
        $message    = sprintf(
            'Method %s::%s does not exist.', 
            get_class( $this ),
            $method
        );

        throw new \ErrorException( $message, 0, 1, $file, $line );
    }
}