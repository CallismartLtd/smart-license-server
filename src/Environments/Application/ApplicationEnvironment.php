<?php
/**
 * Core runtime class file.
 *
 * @author Callistus Nwachukwu
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application;

use Callismart\DBPrism\DBConfigDTO;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environment;
use SmartLicenseServer\Environments\Application\Auth\ConsoleIdentityProvider;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Environments\Application\Routing\RouteManager;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\RESTAPI\Versions\V1;
use SmartLicenseServer\Routing\Router;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Templates\TemplateDiscovery;

/**
 * Smart License Server running as a standalone PHP application.
 */
class ApplicationEnvironment extends Environment {

    /**
     * {@inheritdoc}
     */
    protected function registerDependencies() : void {
        $this->container->singleton(
            Request::class,
            fn () : Request => Request::createFromGlobals()
        );

        $this->container->singleton( Guard::class, new Guard );

        $this->container->set(
            SessionManager::class, new SessionManager( $this->runtime->secret )
        );
        
        $this->container->singleton(
            IdentityService::class,
            function( Container $container ) {
                $provider   = is_cli()
                    ? $container->get( ConsoleIdentityProvider::class )
                    : $container->get( WebIdentityProvider::class );
                return new IdentityService(
                    $container->get( Guard::class ),
                    $provider
                );
            }
        );

        $this->container->alias( PasswordIdentityProviderInterface::class, IdentityService::class );

        $this->container->singleton(
            URLManager::class,
            fn ( Container $c ) : URLManager => new URLManager(
                settings: $c->get( Settings::class ),
                app_url: $this->url(),
                admin_base_url: $this->url(),
                assets_url: $this->url()->append_path( '/assets/' )
            )
        );

        $this->container->singleton(
            RestAPIProvider::class,
            RestAPIProvider::init(
                $this->container->get( V1::class )
            )
        );

        $this->container->alias( RESTProviderInterface::class, RestAPIProvider::class );
        $this->container->singleton( DefaultPage::class, $this->container->get( DefaultPage::class ) );
    }

    /**
     * {@inheritdoc}
     */
    protected function boot() : void {
        $this->container->get( TemplateDiscovery::class )
            ->discover( 'core', SMLISER_RUNTIME_DIR . '/templates/', 0 );

        $defaut_page    = $this->container->get( DefaultPage::class );
        $route_manager = $this->container->get( RouteManager::class )
            ->homeHandler( [$defaut_page, 'home'] )
            ->notFound( [$defaut_page, 'not_found'] )
            ->methodNotAllowed( [$defaut_page, 'method_not_allowed'] );
        
        $route_manager->registerCoreRoutes();

        $this->container->singleton( RouteManager::class, $route_manager );
        

    }

    /**
     * {@inheritdoc}
     */
    protected function createDatabaseConfig() : DBConfigDTO {
        $dbConfig = new DBConfigDTO([
            'driver'    => $_ENV['SMLISER_DB_DRIVER'] ?? '',
            'host'      => $_ENV['SMLISER_DB_HOST'] ?? '',
            'port'      => $_ENV['SMLISER_DB_PORT'] ?? '',
            'dbname'    => $_ENV['SMLISER_DB_NAME'] ?? '',
            'username'  => $_ENV['SMLISER_DB_USER'] ?? '',
            'password'  => $_ENV['SMLISER_DB_PASSWORD'] ?? '',
            'charset'   => $_ENV['SMLISER_DB_CHARSET'] ?? '',
            'prefix'    => $_ENV['SMLISER_DB_PREFIX'] ?? '',
            'path'      => $_ENV['SMLISER_DB_PATH'] ?? '',
        ]);

        if (
            'sqlite' === $dbConfig->driver
            && ! empty( $_ENV['SMLISER_SQLITE_ENCRYPTION_KEY'] )
        ) {
            $dbConfig->encryption_key = $_ENV['SMLISER_SQLITE_ENCRYPTION_KEY'];
        }

        return $dbConfig;
    }

    /**
     * {@inheritdoc}
     */
    protected function url() : URL {
        $url = (string) ( $_ENV['SMLISER_APP_URL'] ?? '' );

        return URL::from( $url );
    }

    /**
     * {@inheritdoc}
     */
    public function restAPIUrl( string $path = '', array $q = [] ) : URL {
        return $this->url( '/api/', $q )
            ->append_path( $path );
    }
}