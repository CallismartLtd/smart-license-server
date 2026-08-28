<?php
/**
 * Admin access middleware class file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Guards against unauthenticated and unauthorized access to the admin dashboard.
 */
class AdminAccessMiddleware implements MiddlewareInterface {

    /**
     * Class constructor
     */
    public function __construct( protected Guard $guard, protected URLManager $urlmanager ) {}

    /**
     * {@inheritdoc}
     * 
     * Perform authentication and authorization checks.
     */
    public function handle( Request $request, callable $next ) : mixed {
        
        if ( ! $this->guard->has_principal() ) {
            $return_url = \smliser_get_current_url();

            // Form submission after logged out?
            if ( in_array( $request->method(), [ Request::POST, Request::PATCH, Request::PUT ], true ) && $request->referer() ) {
                $return_url = URL::from( $request->referer() );
            }

            // Prevent open redirect attack.
            $app_origin = \url( '/' )->get_origin();
            if ( ! $return_url->is_valid() || $return_url->get_origin() !== $app_origin ) {
                $return_url = \smliser_get_current_url();
            }

            $location = $this->urlmanager->login_url()->add_query_param( 'redirect_url', $return_url->url() );
            
            return Response::make( '', 302 )
                ->set_header( 'Location', $location->url() );
        }

        // Handle authenticated users without admin privileges
        if ( ! $this->guard->get_principal()->is( 'system_admin' ) ) {
            return Response::make( '', 302 )
                ->set_header( 'Location', $this->urlmanager->client_dashboard_url()->url() );
        }

        return $next( $request );
    }
}