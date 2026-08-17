<?php
/**
 * The admin menu class file.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Core\Request;

use function add_submenu_page, add_menu_page, sprintf;

/**
 * The admin menu class handles all admin menu registry and routing.
 */
class AdminMenu {
    const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+CiAgPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTAsIDEwKSBzY2FsZSgxLjQpIHRyYW5zbGF0ZSgtMTAsIC0xMCkiPgogICAgPHJlY3QgeD0iNC40IiB5PSI1LjYiIHdpZHRoPSIxMS4yIiBoZWlnaHQ9IjIiIHJ4PSIwLjYiIGZpbGw9IiNhN2FhYWQiLz4KICAgIDxyZWN0IHg9IjQuNCIgeT0iOC40IiB3aWR0aD0iMTEuMiIgaGVpZ2h0PSIyIiByeD0iMC42IiBmaWxsPSIjYTdhYWFkIi8+CiAgICA8cmVjdCB4PSI0LjQiIHk9IjExLjIiIHdpZHRoPSIxMS4yIiBoZWlnaHQ9IjIiIHJ4PSIwLjYiIGZpbGw9IiNhN2FhYWQiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjYuNiIgcj0iMC41IiBmaWxsPSIjMzMzIiBvcGFjaXR5PSIwLjQiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjkuNCIgcj0iMC41IiBmaWxsPSIjMzMzIiBvcGFjaXR5PSIwLjQiLz4KICAgIDxjaXJjbGUgY3g9IjUuNiIgY3k9IjEyLjIiIHI9IjAuNSIgZmlsbD0iIzMzMyIgb3BhY2l0eT0iMC40Ii8+CiAgICA8cGF0aCBkPSJNMTAgNCBMMTMuNiA1LjYgVjkuMiBDMTMuNiAxMiAxMS42IDE0IDEwIDE0LjggQzguNCAxNCA2LjQgMTIgNi40IDkuMiBWNS42IFoiIGZpbGw9IiNhN2FhYWQiLz4KICAgIDxjaXJjbGUgY3g9IjEwIiBjeT0iOC44IiByPSIxIiBmaWxsPSIjMzMzIiBvcGFjaXR5PSIwLjQiLz4KICAgIDxyZWN0IHg9IjkuNCIgeT0iOC44IiB3aWR0aD0iMS4yIiBoZWlnaHQ9IjIuNCIgcng9IjAuMyIgZmlsbD0iIzMzMyIgb3BhY2l0eT0iMC40Ii8+CiAgPC9nPgo8L3N2Zz4=';

    private string $prefix  = 'smliser';

    /**
     * Direct reference to the global WordPress admin submenu variable.
     */
    private array $wp_submenu;


    /**
     * Constructor
     * 
     * @param AdminDashboardRegistry $registry
     * @param Request $request The  current request object.
     */
    public function __construct(
        private AdminDashboardRegistry $registry,
        private Request $request,
    ) {}

    /**
     * Register admin menus.
     */
    public function register_menus() {
        $slug   = sprintf( '%s-overview', $this->prefix );
        
        add_menu_page( SMLISER_APP_NAME, SMLISER_APP_NAME, 'manage_options', $slug, array( $this, 'dispatch_request' ), self::MENU_ICON, 3.1 );

        foreach ( $this->registry->all() as $key => $menu ) {
            if ( $this->registry->is_root_menu( $key ) ) continue; // Already registered.

            $base_slug   = "{$this->prefix}-{$menu['slug']}";
            add_submenu_page( $slug, $menu['title'], $menu['title'], 'manage_options', $base_slug, [$this, 'dispatch_request'] );
        }
    }

    /**
     * Rename First menu item to Dashboard.
     */
    public function submenu_index_name() {
        $this->ensure_wpsubmenu();

        $slug   = "{$this->prefix}-overview";

        if ( ! isset( $this->wp_submenu[$slug] ) ) {
            return;
        }

        $this->wp_submenu[$slug][0][0] = 'Overview';
    }

    /**
     * Dispatch the WordPress menu call to the handler with the request object.
     */
    public function dispatch_request() : void {
        if ( ! $this->request->hasValue( 'page' ) ) {
            return;
        }

        $page   = (string) $this->request->get( 'page' );
        $prefix = "{$this->prefix}-";

        if ( strpos( $page, $prefix ) === 0 ) {
            $key            = substr( $page, strlen( $prefix ) );
            $target_menu    = $this->registry->get( $key );
        } else {
            $target_menu    = $this->registry->get( 'overview' );
        }

        $routing_var    = 'tab';

        if ( $routing_var && $this->request->hasValue( $routing_var ) ) {
            $submenu    = $target_menu['handler']::get_submenu();

            foreach( $submenu as $subm ) {
                if ( $this->request->get( $routing_var ) === $subm['slug'] ) {
                    $handler    = $subm['callback'];
                }
            }
            
        }

        if ( ! isset( $handler ) ) {
            $handler    = $target_menu['handler']::index_page_handler();
        }

        $handler( $this->request );
    }

    protected function ensure_wpsubmenu() : void {
        if ( isset( $this->wp_submenu ) ) {
            return;
        }

        $this->wp_submenu   = &$GLOBALS['submenu'] ?? [];
    }
}