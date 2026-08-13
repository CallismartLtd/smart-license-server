<?php
/**
 * Http kernel class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Kernel
 */

namespace SmartLicenseServer\Environments\Application\Kernel;

use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Routing\RouteManager;

/**
 * Http kernel class coordinates http request and response lifecycle.
 */
class HTTPKernel extends Kernel {
    protected RouteManager $routeManager;
    protected ?Response $response = null;
    
    /**
     * {@inheritdoc}
     */
    public function boot() : static {
        $this->routeManager = new RouteManager();
        $this->routeManager->registerCoreRoutes();
        
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {
        $this->response = $this->routeManager->dispatch(
            $this->environment->request()->method(),
            $this->environment->request()->path(),
            $this->environment->request()
        );

        $this->response->send();

        return $this;
    }

    /**
     * Terminate the request.
     * 
     * @return never
     */
    public function terminate() : never {
        if ( isset( $this->response ) ) {
            $this->response->stop();
        }

        exit( 0 );
    }
}