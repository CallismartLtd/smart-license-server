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
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
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
     * Constructor.
     *
     * @param Container    $container
     * @param Request      $request
     * @param RouteManager $routeManager
     * @param IdentityService $identityService
     */
    public function __construct(
        protected Container $container,
        protected Request $request,
        protected RouteManager $routeManager,
        protected IdentityService $identityService
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function boot() : static {
        // $this->identityService->authenticate();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {
        $this->response = $this->routeManager->dispatch(
            $this->request->method(),
            $this->request->path(),
            $this->request
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