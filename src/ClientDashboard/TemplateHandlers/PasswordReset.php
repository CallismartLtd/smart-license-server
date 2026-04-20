<?php
/**
 * Password Reset Handler
 *
 * Renders and processes password reset form.
 * User lands here via reset link from email: /auth?token=XXX&email=user@example.com
 *
 * @package SmartLicenseServer\ClientDashboard\Auth\Handlers
 */

namespace SmartLicenseServer\ClientDashboard\TemplateHandlers;

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\ClientDashboard\DashboardHandlerInterface;
use SmartLicenseServer\ClientDashboard\Handlers\AuthController;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;

defined( 'SMLISER_ABSPATH' ) || exit;

class PasswordReset implements DashboardHandlerInterface {

    /**
     * Get handler slug.
     *
     * @return string
     */
    public static function slug() : string {
        return 'reset-password';
    }

    /**
     * Guard this handler.
     *
     * Validates that reset token is present in query string.
     *
     * @param Request $request
     * @return bool|RequestException
     */
    public static function guard( Request $request ) : bool|RequestException {
        $token = (string) $request->get( 'token', '' );

        if ( empty( $token ) ) {
            return new RequestException(
                'missing_token',
                'Invalid or missing reset token.',
                ['status' => 400 ]
            );
        }

        $check  = AuthController::verify_password_reset_token( $token );

        if ( ! ( $check['valid'] ?? false ) ) {
            return new RequestException(
                'token_error',
                $check['reason'] ?? 'Unknown error occured.',
            );
        }

        return true;
    }

    /**
     * Render password reset form.
     *
     * @param Request $request
     * @return Response JSON response containing form HTML
     */
    public static function handle( Request $request ) : Response {
        // Get token and email from query string
        $token = (string) $request->get( 'token', '' );

        $html   = \smliser_render_template_to_string( 
            ClientDashboardRenderer::AUTH_RESET_PWD_TEMPLATE,[
            'token' => $token,
        ]);

        // Return JSON response with form HTML and header
        return (new Response( 200 ))
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
    }
}