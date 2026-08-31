<?php
/**
 * Http kernel class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Kernel
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Kernel;

use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\ApplicationEnvironment;
use SmartLicenseServer\Environments\Application\Routing\RouteManager;

/**
 * HTTP kernel coordinates the HTTP request lifecycle.
 */
class HTTPKernel extends Kernel {

    /**
     * Response generated during request dispatch.
     */
    protected Response $response;

    /**
     * The current request object.
     */
    protected Request $request;

    /**
     * The route management object.
     */
    protected RouteManager $routeManager;

    protected Container $container;


    /**
     * Constructor.
     */
    public function __construct(
        protected ApplicationEnvironment $app,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function boot() : static {
        $this->app->boot();

        $this->container    = $this->app->container();
        $this->request      = $this->container->get( Request::class );
        $this->routeManager = $this->container->get( RouteManager::class );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {

        $this->response = $this->routeManager->dispatch( $this->request );
        
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