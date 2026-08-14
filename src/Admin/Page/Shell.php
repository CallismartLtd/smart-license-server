<?php
/**
 * The admin dashboard shell.
 *
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Admin\Page;

use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * The admin dashboard orchestrator.
 *
 * Resolves the active top-level menu and submenu from the request, dispatches
 * the matching handler, and assembles the left menu, top menu, and content
 * area into a single rendered string.
 *
 * NOTE: Submenu `visibility` (see AdminPageInterface::get_submenu()) is not
 * enforced here. AdminDashboardRegistry::add_submenu() currently discards
 * that field when a submenu is registered, so there is nothing for the Shell
 * to read at render time. Every registered submenu is treated as visible and
 * reachable until the registry itself is updated to retain it.
 */
class Shell {

	/**
	 * Query var used to resolve the active top-level menu.
	 *
	 * @var string
	 */
	protected string $page_query_var;

	/**
	 * Query var used to resolve the active sub-tab / submenu page.
	 *
	 * @var string
	 */
	protected string $tab_query_var;

    /*
    |--------------------
    | TEMPLATE SLUGS
    |--------------------
    */

    public const SHELL_TEMPLATE             = 'admin.index';
    public const HEADER_TEMPLATE            = 'admin.header';
    public const MENU_TEMPLATE              = 'admin.menu';
    public const CONTENT_TEMPLATE           = 'admin.content';
    public const AUTH_INDEX_TEMPLATE        = 'frontend.auth.index';
    public const AUTH_LOGIN_TEMPLATE        = 'frontend.auth.login';
    public const AUTH_SIGNUP_TEMPLATE       = 'frontend.auth.signup';
    public const AUTH_FORGOT_PWD_TEMPLATE   = 'frontend.auth.forgot-password';
    public const AUTH_RESET_PWD_TEMPLATE    = 'frontend.auth.reset-password';
    public const AUTH_2FA_TEMPLATE          = 'frontend.auth.2fa';
    public const FOOTER_TEMPLATE            = 'admin.footer';

    /**
     * @param AdminDashboardRegistry	$registry
     * @param TemplateLocator			$locator
     */
    public function __construct(
        protected AdminDashboardRegistry	$registry,
        protected TemplateLocator			$locator,
		protected Request					$request
    ) {}
    /*
    |---------
    | RENDER
    |---------
    */

    /**
     * Render the full dashboard shell.
     *
     * @return void
     */
    public function render() : void {
        $menu        = $this->registry->all();
        $this->locator->render( self::SHELL_TEMPLATE, [
            'menu'          => $menu,
			'request'		=> $this->request
        ] );
    }

    /**
     * Render only the header partial.
     *
     * @param string $rest_base
     * @param string $active_slug
     * @return void
     */
    public function render_header( string $rest_base, string $active_slug = '' ) : void {
        $menu        = $this->registry->all();
        $active_slug = $active_slug ?: array_key_first( $menu ) ?? '';

        $this->locator->render( self::HEADER_TEMPLATE, [
            'menu'        => $menu,
            'rest_base'   => rtrim( $rest_base, '/' ) . '/',
            'active_slug' => $active_slug,
        ] );
    }

    /**
     * Render only the menu partial.
     *
     * Useful when the menu needs to be refreshed independently
     * after a registry modification.
     *
     * @param string $active_slug
     * @return void
     */
    public function render_menu( string $active_slug = '' ) : void {
        $this->locator->render( self::MENU_TEMPLATE, [
            'menu'        => $this->registry->all(),
            'active_slug' => $active_slug,
        ] );
    }

    /**
     * Render only the content partial.
     *
     * @param string $rest_base
     * @param string $active_slug
     * @return void
     */
    public function render_content( string $rest_base, string $active_slug = '' ) : void {
        $this->locator->render( self::CONTENT_TEMPLATE, [
            'rest_base'   => rtrim( $rest_base, '/' ) . '/',
            'active_slug' => $active_slug,
        ] );
    }

    /**
     * Render only the footer partial.
     *
     * @return void
     */
    public function render_footer() : void {
        $this->locator->render( self::FOOTER_TEMPLATE );
    }

    /**
     * Render the full shell to a string.
     *
     * @param string $rest_base
     * @param string $active_slug
     * @return string
     */
    public function render_to_string( string $rest_base, string $active_slug = '' ) : string {
        ob_start();
        $this->render( $rest_base, $active_slug );
        return (string) ob_get_clean();
    }

    /**
     * Return as HTTP response object.
     * 
     * @param string $rest_base
     * @param string $active_slug
     * @return Response
     */
    public function asResponse( string $rest_base, string $active_slug = '' ) : Response {
        return ( new Response )
            ->set_body( $this->render_to_string( $rest_base, $active_slug ) )
            ->set_header( 'Content-Type', 'text/html; charset=utf-8' );
    }
}