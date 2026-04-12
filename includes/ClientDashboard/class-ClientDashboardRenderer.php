<?php
/**
 * Client Dashboard Renderer
 *
 * Renders the dashboard shell — the outer layout containing the
 * left menu and the async content area.
 *
 * Content is NOT rendered here. Each section is fetched asynchronously
 * via the REST API and injected into the content area by the client JS.
 *
 * Template slugs (auto-discovered from templates/frontend/):
 *   frontend.shell    — orchestrator, calls the four partials in order
 *   frontend.header   — auth guard, <head>, <body>, open layout wrapper
 *   frontend.menu     — left sidebar navigation
 *   frontend.content  — main column, topbar, content area, JS
 *   frontend.footer   — close layout wrapper, </body>, </html>
 *
 * @package SmartLicenseServer\ClientDashboard
 */

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\Contracts\AbstractDashboardRegistry;
use SmartLicenseServer\Templates\TemplateLocator;

defined( 'SMLISER_ABSPATH' ) || exit;

class ClientDashboardRenderer {

    /*
    |--------------------
    | TEMPLATE SLUGS
    |--------------------
    */

    public const SHELL_TEMPLATE             = 'frontend.shell';
    public const HEADER_TEMPLATE            = 'frontend.header';
    public const MENU_TEMPLATE              = 'frontend.menu';
    public const CONTENT_TEMPLATE           = 'frontend.content';
    public const AUTH_INDEX_TEMPLATE        = 'frontend.auth.index';
    public const AUTH_LOGIN_TEMPLATE        = 'frontend.auth.login';
    public const AUTH_SIGNUP_TEMPLATE       = 'frontend.auth.signup';
    public const AUTH_FORGOT_PWD_TEMPLATE   = 'frontend.auth.forgot-password';
    public const AUTH_2FA_TEMPLATE          = 'frontend.auth.2fa';
    public const FOOTER_TEMPLATE            = 'frontend.footer';

    /**
     * @param ClientDashboardRegistry $registry
     * @param TemplateLocator         $locator
     */
    public function __construct(
        protected AbstractDashboardRegistry $registry,
        protected TemplateLocator $locator
    ) {}

    /*
    |---------
    | RENDER
    |---------
    */

    /**
     * Render the full dashboard shell.
     *
     * Delegates to frontend.shell which orchestrates header → menu →
     * content → footer in order.
     *
     * @param string $rest_base   Full REST base URL for content requests.
     *                            e.g. https://example.com/wp-json/smliser/v1/dashboard/
     * @param string $active_slug Slug of the initially active section.
     * @return void
     */
    public function render( string $rest_base, string $active_slug = '' ) : void {
        $menu        = $this->registry->all();
        $active_slug = $active_slug ?: array_key_first( $menu ) ?? '';

        $this->locator->render( self::SHELL_TEMPLATE, [
            'menu'        => $menu,
            'rest_base'   => rtrim( $rest_base, '/' ) . '/',
            'active_slug' => $active_slug,
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
}