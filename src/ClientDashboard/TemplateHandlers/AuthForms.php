<?php
/**
 * Auth forms class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\ClientDashboard\TemplateHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Handles the rendering of authentication forms as json response.
 */
class AuthForms {
    public const INDEX_TEMPLATE                 = 'frontend.auth.index';
    public const INDEX_CONTENT_TEMPLATE         = 'frontend.auth.content';
    public const LOGIN_FORM_TEMPLATE            = 'frontend.auth.login';
    public const SIGNUP_FORM_TEMPLATE           = 'frontend.auth.signup';
    public const FORGOT_PASSWORD_FORM_TEMPLATE  = 'frontend.auth.forgot-password';

    /**
     * Class constructor
     */
    public function __construct( protected Guard $guard ) {}

    /**
     * Renders the full login form page.
     * 
     * @param Request $request
     * @return Response
     */
    public function render_login_form_shell( Request $request ) : Response {
        $registry       = \authTemplateRegistry();
        $locator        = smliser_template_locator();

        return Response::make(
            $locator->render_to_string(
                static::INDEX_TEMPLATE,
                [
                    'menu'      => $registry->all(),
                    'rest_base' => \url( \smliser_login_url_prefix() . '/form/' )->url(),
                    'guard'     => $this->guard,
                    'request'   => $request
                ]
            )
        );
    }

    /**
     * Render the main login form template as json response.
     * 
     * @param Request $request
     * @return Response
     */
    public function render_json_login_form( Request $request ) : Response {
        $html = smliser_render_template_to_string(
            static::LOGIN_FORM_TEMPLATE, [
                'guard'     => $this->guard,
                'request'   => $request
            ]
        );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }

    /**
     * Render the signup form template as json response.
     * 
     * @param Request $request
     * @return Response
     */
    public function render_json_signup_form( Request $request ) : Response {
        $html = smliser_render_template_to_string(
            static::SIGNUP_FORM_TEMPLATE, [
                'guard'     => $this->guard,
                'request'   => $request
            ]
        );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }

    /**
     * Render the forgot password form template as json response.
     * 
     * @param Request $request
     * @return Response
     */
    public function render_json_forgot_password_form( Request $request ) : Response {
        $html = smliser_render_template_to_string(
            static::FORGOT_PASSWORD_FORM_TEMPLATE, [
                'guard'     => $this->guard,
                'request'   => $request
            ]
        );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [
                'success' => true,
                'html'    => $html,
            ] );
    }

}