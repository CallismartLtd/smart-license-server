<?php
/**
 * Http kernel class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Kernel
 */
declare( strict_types=1 );
namespace SmartLicenseServer\Environments\Application\Kernel;

use SmartLicenseServer\Admin\Page\Dispatcher;
use SmartLicenseServer\ClientDashboard\Handlers\AuthController;
use SmartLicenseServer\ClientDashboard\TemplateHandlers\AuthForms;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Environments\Application\Routing\RouteManager;
use SmartLicenseServer\Routing\Router;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Http kernel class coordinates http request and response lifecycle.
 */
class HTTPKernel extends Kernel {
    protected RouteManager $routeManager;
    protected Response $response;
    protected SessionManager $sessionManager;
    
    /**
     * {@inheritdoc}
     */
    public function boot() : static {
        $this->setAuth();
        $this->identity_service->authenticate();
        $this->setUpRouter();
        
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {
        $this->response = $this->routeManager->dispatch(
            $this->environment->request()->method(),
            $this->environment->request()->path(),
            $this->environment->request(),
            $this->guard
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

    /*
    |-------------------------
    | BOOT HELPERS
    |-------------------------
    */

    /**
     * Setup the auth provider
     */
    protected function setAuth() : void {
        $this->guard            = new Guard;
        $this->sessionManager   = new SessionManager( 
            $this->environment->get_runtime_config()->secret 
        );

        $this->identity_service = new IdentityService(
            $this->guard,
            new WebIdentityProvider( $this->sessionManager, $this->guard )
        );
    }

    /**
     * Set up web routing
     */
    protected function setUpRouter() {
        $this->routeManager = new RouteManager( new Router );
                
        $this->routeManager->registerDefaultPages();
        $this->routeManager->registerCoreRoutes( 
            $this->guard,
            new AuthController( $this->guard, $this->identity_service ),
            new AuthForms( $this->guard ),
            new Dispatcher( $this->guard ),
        );
    }

}