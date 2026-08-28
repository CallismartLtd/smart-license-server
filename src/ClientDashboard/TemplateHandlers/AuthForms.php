<?php
/**
 * Auth forms class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\ClientDashboard\TemplateHandlers;

use SmartLicenseServer\ClientDashboard\AuthTemplateRegistry;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateLocator;

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
    public function __construct(
        protected Guard $guard,
        protected AuthTemplateRegistry $registry,
        protected TemplateLocator $locator,
        protected URLManager $urlmanager,
        protected Settings $settings
        
    ) {}

    /**
     * Renders the full login form page.
     * 
     * @param Request $request
     * @return Response
     */
    public function render_login_form_shell( Request $request ) : Response {

        return Response::make(
            $this->locator->render_to_string(
                static::INDEX_TEMPLATE,
                [
                    'menu'      => $this->registry->all(),
                    'rest_base' => $this->urlmanager->login_url()->url(),
                    'guard'     => $this->guard,
                    'request'   => $request,
                    'settings'  => $this->settings,
                    'slugs'     => $this->registry->slugs()
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
        $html = $this->locator->render_to_string(
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
        $html = $this->locator->render_to_string(
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
        $html = $this->locator->render_to_string(
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