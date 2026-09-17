<?php
/**
 * Web environment bootstrapper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Assets\CSS;
use SmartLicenseServer\Assets\JS;
use SmartLicenseServer\Environments\Application\Boot\BootstrapperInterface;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Environments\Application\DefaultPage;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Web\HttpExecutionHandler;
use SmartLicenseServer\Environments\Application\RestAPIProvider;
use SmartLicenseServer\Environments\Application\Web\HttpDispatcher;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\RESTAPI\Versions\V1;
use SmartLicenseServer\Routing\Router;
use SmartLicenseServer\RuntimeConfig;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;
use SmartLicenseServer\Templates\TemplateDiscovery;

/**
 * Class WebBootstrapper
 *
 * Handles container bindings and boot initialization for HTTP/Web request execution.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
class WebBootstrapper implements BootstrapperInterface {

    /**
     * {@inheritdoc}
     */
    public function isEligible() : bool {
        return ! \is_cli();
    }

    /**
     * {@inheritdoc}
     */
    public function register( Container $container ) : void {
        $container->singleton(
            Request::class,
            fn () : Request => Request::createFromGlobals()
        );

        $container->singleton(
            HttpDispatcher::class,
            fn ( Container $c ) : HttpDispatcher => new HttpDispatcher(
                $c->get( Router::class ),
                $c
            )
        );

        $container->singleton(
            ExecutionHandlerInterface::class,
            fn ( Container $c ) : HttpExecutionHandler => new HttpExecutionHandler(
                $c->get( HttpDispatcher::class ),
                $c->get( Request::class )
            )
        );

        $container->singleton(
            IdentityService::class,
            fn ( Container $c ) : IdentityService => new IdentityService(
                $c->get( Guard::class ),
                $c->get( WebIdentityProvider::class )
            )
        );

        $container->singleton(
            RestAPIProvider::class,
            fn ( Container $c ) : RestAPIProvider => RestAPIProvider::init(
                $c->get( V1::class )
            )
        );

        $container->singleton(
            AssetsManager::class,
            fn ( Container $c ) : AssetsManager => new AssetsManager(
                $c->get( Guard::class ),
                $c->get( URLManager::class ),
                $c->get( CSS::class ),
                $c->get( JS::class )
            )
        );

        $container->singleton(
            SessionManager::class,
            fn( Container $c ) : SessionManager => new SessionManager(
                $c->get( RuntimeConfig::class )->secret
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot( Container $container ) : void {
        // Force evaluation of FileSystem API initialization.
        $container->get( FileSystem::class );

        $guard = $container->get( Guard::class );
        if ( $guard->has_principal() ) {
            $container->singleton(
                UserSettings::class,
                UserSettings::for( $guard->principal()->get_actor() )
            );
        }

        $defaultPage  = $container->get( DefaultPage::class );
        $routeManager = $container->get( HttpDispatcher::class )
            ->homeHandler( [ $defaultPage, 'home' ] )
            ->notFound( [ $defaultPage, 'not_found' ] )
            ->methodNotAllowed( [ $defaultPage, 'method_not_allowed' ] );

        $routeManager->registerCoreRoutes();
        
        $container->get( TemplateDiscovery::class )
            ->discover( 'core', \SMLISER_RUNTIME_DIR . '/templates/', 0 );
    }
}