<?php
/**
 * Login form class file.
 *
 * @package SmartLicenseServer\ClientDashboard\Auth
 */

namespace SmartLicenseServer\ClientDashboard\TemplateHandlers;

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\ClientDashboard\DashboardHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;

/**
 * Handles the login form rendering.
 */
class Login implements DashboardHandlerInterface {
    public const INDEX_TEMPLATE             = 'frontend.index';
    public const AUTH_INDEX_TEMPLATE        = 'frontend.auth.index';
    public const AUTH_LOGIN_TEMPLATE        = 'frontend.auth.login';
    public const AUTH_SIGNUP_TEMPLATE       = 'frontend.auth.signup';
    public const AUTH_FORGOT_PWD_TEMPLATE   = 'frontend.auth.forgot-password';
    public const AUTH_RESET_PWD_TEMPLATE    = 'frontend.auth.reset-password';
    public const AUTH_2FA_TEMPLATE          = 'frontend.auth.2fa';
    public const FOOTER_TEMPLATE            = 'frontend.footer';

    public static function slug() : string {
        return 'login';
    }

    /**
     * No guard restrictions — login form is always accessible.
     */
    public static function guard( Request $request ) : bool|RequestException {
        return true;
    }

    /**
     * Render and return the login form HTML.
     *
     * Template renders only the form (no wrapper, no alerts).
     * The SPA container provides the wrapper and alert system.
     */
    public static function handle( Request $request ) : Response {
        $html = smliser_render_template_to_string( ClientDashboardRenderer::AUTH_LOGIN_TEMPLATE, [] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }

    /**
     * Renders the full login form page.
     * 
     * @param Request $request
     * @return Response
     */
    public static function render_login_form( Request $request ) : Response {
        $registry       = \authTemplateRegistry();
        $locator        = smliser_template_locator();

        return Response::make(
            $locator->render_to_string(
                static::INDEX_TEMPLATE,
                [
                    'menu'      => $registry->all(),
                    'rest_base' => \url( \smliser_login_url_prefix() . '/form/' )
                ]
            )
        );
    }
}