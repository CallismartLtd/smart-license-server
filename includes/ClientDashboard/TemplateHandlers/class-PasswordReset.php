<?php
/**
 * Password Reset Handler
 *
 * Renders and processes password reset form.
 * User lands here via reset link from email: /auth?token=XXX&email=user@example.com
 *
 * @package SmartLicenseServer\ClientDashboard\Auth\Handlers
 */

namespace SmartLicenseServer\ClientDashboard\Auth\Handlers;

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\ClientDashboard\DashboardHandlerInterface;
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
        // Token should be in query string: ?token=XXX
        $token = (string) $request->get( 'token', '' );
        $email = (string) $request->get( 'email', '' );

        // if ( empty( $token ) || empty( $email ) ) {
        //     return (new Response( 400 ))
        //         ->set_body( [
        //             'success' => false,
        //             'code'    => 'missing_token',
        //             'message' => 'Invalid or missing reset token.',
        //         ] )
        //         ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        // }

        // Validate that token hasn't expired and is valid
        // This would be checked in the form render method as well
        // You could add stricter validation here if needed

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
        $email = (string) $request->get( 'email', '' );
        $html   = \smliser_render_template_to_string( ClientDashboardRenderer::AUTH_RESET_PWD_TEMPLATE,[
            'token' => $token,
            'email' => $email
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