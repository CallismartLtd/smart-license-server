<?php
/**
 * Login Form Handler
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

/**
 * Two-Factor Authentication Handler
 *
 * Renders the 2FA verification code form.
 *
 * Guard: Requires user to have partial authentication
 * (completed username/password but not yet verified 2FA code).
 */
class TwoFactorAuth implements DashboardHandlerInterface {

    public static function slug() : string {
        return '2fa';
    }

    /**
     * 2FA form only accessible after initial login attempt.
     *
     * Could check for a temporary session token or flag
     * indicating user has passed first auth step.
     */
    public static function guard( Request $request ) : bool|RequestException {
        // TODO: Implement check for partial auth session
        // For now, allow access (implement proper guard when 2FA flow is finalized)
        return true;
    }

    public static function handle( Request $request ) : Response {
        $html = smliser_render_template_to_string( ClientDashboardRenderer::AUTH_2FA_TEMPLATE, [] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }
}