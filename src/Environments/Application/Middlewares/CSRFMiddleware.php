<?php
/**
 * CSRF middleware class file.
 *
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\CSRF\CSRF;

/**
 * Rejects cross-site requests that change state.
 *
 * GET, HEAD and OPTIONS pass untouched; they must never change state. Any
 * other method must come from the application itself (Sec-Fetch-Site,
 * Origin or Referer) and carry a valid CSRF token in the X-CSRF-Token header
 * or the _token field of a form or JSON body.
 *
 * Use it on routes whose caller is identified by the session cookie, or on
 * guest forms (login, signup, password reset), which get a guest token.
 * Routes authenticated by an API key or bearer token sent by the client
 * itself are not exposed to CSRF and do not need it.
 */
class CSRFMiddleware implements MiddlewareInterface {

    /**
     * Methods that pass without a token.
     *
     * @var string[]
     */
    private const SAFE_METHODS = [ Request::GET, Request::HEAD, Request::OPTIONS ];

    /**
     * Class constructor.
     *
     * @param CSRF       $csrf       The CSRF token service.
     * @param URLManager $urlmanager The URL manager.
     */
    public function __construct( protected CSRF $csrf, protected URLManager $urlmanager ) {}

    /**
     * {@inheritdoc}
     */
    public function handle( Request $request, callable $next ) : mixed {
        if ( in_array( $request->method(), self::SAFE_METHODS, true ) ) {
            return $next( $request );
        }

        if ( ! $this->csrf->same_origin( $request, $this->urlmanager->url()->url() ) ) {
            return $this->reject( $request, 'CROSS_SITE_REQUEST', 'This request did not come from this site.' );
        }

        if ( ! $this->csrf->verify_request( $request ) ) {
            return $this->reject( $request, 'INVALID_CSRF_TOKEN', 'Your session security token is missing or has expired. Reload the page and try again.' );
        }

        return $next( $request );
    }

    /**
     * The 403 response for a rejected request.
     *
     * @param Request $request The request.
     * @param string  $code    Error code.
     * @param string  $message Message for the user.
     * @return Response
     */
    protected function reject( Request $request, string $code, string $message ) : Response {
        if ( $request->expectsJson() ) {
            return Response::json(
                [
                    'success' => false,
                    'message' => $message,
                    'code'    => $code,
                ],
                403
            );
        }

        return Response::make( htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ), 403 )
            ->set_header( 'Content-Type', 'text/html; charset=utf-8' );
    }
}