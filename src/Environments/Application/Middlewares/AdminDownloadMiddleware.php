<?php
/**
 * Admin file download middleware class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Ensures download requests are made by system dministrators
 */
class AdminDownloadMiddleware implements MiddlewareInterface {
    public function __construct(
        protected Guard $guard
    ) {}

    public function handle( Request $request, callable $next ): mixed {
        if ( ! $this->guard->has_principal() ) {
            return Response::error(
                new Exception(
                    'login_required',
                    'You must be logged in to access this resource.',
                    [ 'status' => 401]
                )
            );
        }

        if ( ! $this->guard->principal()->is( 'system_admin' ) ) {
            return Response::error(
                new Exception(
                    'unauthorized_download',
                    'You do not have the required permission to access this resource',
                    ['status' => 403]
                )
            );
        }

        return $next( $request );
    }
}