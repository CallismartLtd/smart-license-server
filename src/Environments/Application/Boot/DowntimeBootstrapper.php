<?php
/**
 * Downtime bootstrapper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Auth\WebIdentityProvider;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Web\DowntimeExecutionHandler;
use SmartLicenseServer\RuntimeConfig;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Class DowntimeBootstrapper
 *
 * Binds the downtime execution handler for web requests while the
 * application is in Maintenance or Installation mode (see
 * BootMode::blocks_web()). The CLI is never affected.
 *
 * Registered in place of WebBootstrapper, so no routes, sessions or
 * identity services are set up for downtime requests.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
final class DowntimeBootstrapper implements BootstrapperInterface {

	/**
	 * Class constructor.
	 *
	 * @param BootModeResolver $resolver The request's boot mode resolver.
	 */
	public function __construct( private readonly BootModeResolver $resolver ) {}

	/**
	 * {@inheritdoc}
	 */
	public function isEligible() : bool {
		return ! \is_cli() && $this->resolver->resolve()->blocks_web();
	}

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ) : void {
		$resolver = $this->resolver;

		$container->singleton(
			ExecutionHandlerInterface::class,
			fn () : DowntimeExecutionHandler => new DowntimeExecutionHandler(
				$resolver->message(),
				$resolver->retry_after()
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
            SessionManager::class,
            fn( Container $c ) : SessionManager => new SessionManager(
                $c->get( RuntimeConfig::class )->secret
            )
        );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ) : void {}
}