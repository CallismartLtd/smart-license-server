<?php
/**
 * Hosted Application download middleware class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Middlewares;

use SmartLicenseServer\Core\Request;

/**
 * Handles normalization of requests to download a hosted application.
 */
class AppDownloadMiddleware implements MiddlewareInterface {
    /**
     * {@inheritdoc}
     */
    public function handle( Request $request, callable $next ): mixed {
        
        return $next( $request );
    }
}