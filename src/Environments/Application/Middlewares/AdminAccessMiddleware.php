<?php
/**
 * Admin access middleware class file.
 * 
 * @author Callistus NWachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Guards against unauthenticated and unauthorized access to the admin dashboard.
 */
class AdminAccessMiddleware implements MiddlewareInterface {
    /**
     * {@inheritdoc}
     * 
     * Perform authentication checks.
     */
    public function handle( Request $request, callable $next ) : mixed {
        
        if ( ! Guard::has_principal() ) {
            return Response::make( '', 302 )
                ->set_header( 'Location', \url( 'login/' )
                    ->add_query_param( 'redirect_url', \smliser_get_current_url()->url() )
                    ->url() 
                );
        }

        if ( ! Guard::get_principal()->is( 'system_admin' ) ) {
            return Response::make( '', 302  )
                ->set_header( 'Location', \url( 'login/' )->url() );
        }

        return $next();
    }
}