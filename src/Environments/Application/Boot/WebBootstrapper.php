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

use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\ClientDashboard\ClientDashboardRegistry;
use SmartLicenseServer\Assets\CSS;
use SmartLicenseServer\Assets\JS;
use SmartLicenseServer\Environments\Application\Boot\BootstrapperInterface;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Theming\ThemeManager;
use SmartLicenseServer\Environments\Application\Theming\ThemeRenderer;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Environments\Application\Web\HttpExecutionHandler;
use SmartLicenseServer\Environments\Application\Web\RestAPIProvider;
use SmartLicenseServer\Environments\Application\Web\HttpDispatcher;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\RESTAPI\Versions\V1;
use SmartLicenseServer\Routing\Router;
use SmartLicenseServer\RuntimeConfig;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Authentication\Session\UserSessionCutoff;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\CSRF\CSRF;
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
			CSRF::class,
			function( Container $c ) : CSRF {
				$is_secure         = $c->get( Request::class )->isSecure();
				$guest_cookie_name = sprintf( '%ssmliser-csrf', $is_secure ? '__Host-' : '' );
				return new CSRF(
					secret: $c->get( RuntimeConfig::class )->secret,
					salt: $c->get( RuntimeConfig::class )->salt,
					sessions: $c->get( SessionManager::class ),
					secure: $is_secure,
					guest_cookie: $guest_cookie_name
				);
			}
		);

		$container->singleton(
			RestAPIProvider::class,
			fn ( Container $c ) : RestAPIProvider => RestAPIProvider::init(
				identity: $c->get( IdentityService::class ),
				guard: $c->get( Guard::class ),
				csrf: $c->get( CSRF::class ),
				urlmanager: $c->get( URLManager::class ),
				versions: $c->get( V1::class )
			)
		);
		
		$container->singleton(
			AssetsManager::class,
			fn ( Container $c ) : AssetsManager => new AssetsManager(
				$c->get( Guard::class ),
				$c->get( URLManager::class ),
				$c->get( CSS::class ),
				$c->get( JS::class ),
				$c->get( CSRF::class )
			)
		);

		$container->singleton(
			ThemeManager::class,
			fn ( Container $c ) : ThemeManager => ThemeManager::from_runtime( $c->get( FileSystem::class ) )
		);

		$container->singleton(
			ThemeRenderer::class,
			fn ( Container $c ) : ThemeRenderer => new ThemeRenderer(
				$c->get( ThemeManager::class ),
				$c->get( URLManager::class ),
				$c->get( Guard::class ),
				$c->get( AssetsManager::class ),
				$c->get( FileSystem::class )
			)
		);

		/*
		 * One registry per dashboard for the whole request, so menus added
		 * by extensions and themes are the menus the dashboard is built from.
		 */
		$container->singleton(
			AdminDashboardRegistry::class,
			fn ( Container $c ) : AdminDashboardRegistry => new AdminDashboardRegistry(
				$c->get( URLManager::class ),
				$c
			)
		);

		$container->singleton(
			ClientDashboardRegistry::class,
			fn ( Container $c ) : ClientDashboardRegistry => new ClientDashboardRegistry(
				$c->get( URLManager::class ),
				$c,
				$c->get( Guard::class )
			)
		);

		$container->singleton( UserSessionCutoff::class,
			fn() : UserSessionCutoff => new UserSessionCutoff()
		);

		$container->singleton(
			SessionManager::class,
			function( Container $c ) : SessionManager {
				$is_secure   = $c->get( Request::class )->isSecure();
				$cookie_name = sprintf( '%ssmliser-sid', $is_secure ? '__Host-' : '' );
				return new SessionManager(
					secret: $c->get( RuntimeConfig::class )->secret,
					salt: $c->get( RuntimeConfig::class )->salt,
					revocation_check: $c->get( UserSessionCutoff::class ),
					secure: $is_secure,
					cookie_name: $cookie_name
				);
			}
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

		// Views put their assets on the request's AssetsManager.
		View::use_assets( fn () : AssetsManager => $container->get( AssetsManager::class ) );

		$container->get( HttpDispatcher::class )
			->registerCoreRoutes()
			->registerProvider( $container->get( RESTProviderInterface::class ) );
		
		$container->get( TemplateDiscovery::class )
			->discover( 'core', \SMLISER_RUNTIME_DIR . '/templates/', 0 );
	}
}