<?php
/**
 * Forgot Password Form Handler
 *
 * Renders the forgot password form HTML for the auth SPA.
 * Fetched via GET /dashboard/auth/forgot-password
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

/**
 * Forgot Password Form Handler
 *
 * Renders the password reset request form.
 */
class ForgotPassword implements DashboardHandlerInterface {

    public static function slug() : string {
        return 'forgot-password';
    }

    public static function guard( Request $request ) : bool|RequestException {
        // Forgot password accessible to everyone
        return true;
    }

    public static function handle( Request $request ) : Response {
        $html = smliser_render_template_to_string( ClientDashboardRenderer::AUTH_FORGOT_PWD_TEMPLATE, [] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }
}