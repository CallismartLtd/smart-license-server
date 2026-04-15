<?php
/**
 * Logout Form Handler
 *
 * Renders the login form HTML for the auth SPA.
 * Fetched via GET /dashboard/auth/login
 *
 * @package SmartLicenseServer\ClientDashboard\Auth
 */

namespace SmartLicenseServer\ClientDashboard\TemplateHandlers;

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\ClientDashboard\DashboardHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;

defined( 'SMLISER_ABSPATH' ) || exit;

class Logout implements DashboardHandlerInterface {

    public static function slug() : string {
        return 'logout';
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
        $html = smliser_render_template_to_string( ClientDashboardRenderer::AUTH_LOGOUT_TEMPLATE, [] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }
}