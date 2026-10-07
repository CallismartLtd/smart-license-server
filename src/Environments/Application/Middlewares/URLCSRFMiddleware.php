<?php
/**
 * URL CSRF middleware class file.
 *
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Security\CSRF\CSRF;

/**
 * Requires a valid URL token (CSRF::url()) on links that perform an action.
 *
 * CSRFMiddleware leaves GET requests alone; links that change something
 * (logout, a delete link, a one-click admin action) put an action-bound
 * token in their URL instead, and this middleware checks it on every method.
 *
 * By default the token is bound to the request path, which matches links
 * made with CSRF::url( $link ) and no named action. A route that needs a
 * named action, or reads the token from another parameter, uses a subclass
 * that overrides action() and param(): for example a download route binding
 * the token to "download:{app_type}/{app_slug}" from the route parameters.
 */
class URLCSRFMiddleware extends CSRFMiddleware {

    /**
     * {@inheritdoc}
     */
    public function handle( Request $request, callable $next ) : mixed {
        if ( ! $this->csrf->verify_url( $request, $this->action( $request ), $this->param( $request ) ) ) {
            return $this->reject( $request, 'INVALID_URL_TOKEN', 'This link has expired or is not valid for you. Go back, reload the page and try again.' );
        }

        return $next( $request );
    }

    /**
     * The action the token must have been made for.
     *
     * Returns "" by default: the token is bound to the request path.
     * Override to bind it to a named action, usually built from route
     * parameters ($request->route_param()).
     *
     * @param Request $request The request.
     * @return string
     */
    protected function action( Request $request ) : string {
        return '';
    }

    /**
     * The query parameter that carries the token.
     *
     * @param Request $request The request.
     * @return string
     */
    protected function param( Request $request ) : string {
        return CSRF::PARAM;
    }
}