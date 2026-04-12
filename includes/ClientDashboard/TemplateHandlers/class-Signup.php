<?php
/**
 * Additional Auth Form Handlers
 *
 * Signup, ForgotPassword, and 2FA form handlers for the auth SPA.
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
 * Signup Form Handler
 *
 * Renders the signup/registration form.
 */
class Signup implements DashboardHandlerInterface {

    public static function slug() : string {
        return 'signup';
    }

    public static function guard( Request $request ) : bool|RequestException {
        // Signup accessible to everyone (no authentication required)
        return true;
    }

    public static function handle( Request $request ) : Response {
        $html = smliser_render_template_to_string( ClientDashboardRenderer::AUTH_SIGNUP_TEMPLATE, [] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }
}