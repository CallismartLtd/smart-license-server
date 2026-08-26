<?php
/**
 * Logged in check middleware class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Checks request to the auth route and prevents a logged in actor
 * from seeing the form again.
 */
class LoggedInCheckerMiddleware implements MiddlewareInterface {
    public function __construct( protected Guard $guard ) {}
    
    /**
     * {@inheritdoc}
     */
    public function handle( Request $request, callable $next ): mixed {
        if ( $this->guard->has_principal() ) {

            \dd( $request );
            $principal  = $this->guard->principal();
            
            return Response::make( '', 302 )
                ->set_header( 'Location', '' );
        }

        return $next( $request );
    }
}