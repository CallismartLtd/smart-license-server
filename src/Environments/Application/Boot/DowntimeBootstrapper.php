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
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Environments\Application\Installation\InstallerSession;
use SmartLicenseServer\Environments\Application\Installation\SetupToken;
use SmartLicenseServer\Environments\Application\Installation\WebInstaller;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Web\DowntimeExecutionHandler;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Class DowntimeBootstrapper
 *
 * Binds the execution handler for web requests while the application is
 * unavailable (see BootMode::blocks_web()). The CLI is never affected.
 *
 *  - Installation: WebInstaller. The installer owner gets the installer;
 *    everyone else gets the 503 installation notice.
 *  - Maintenance: DowntimeExecutionHandler. Everyone gets the 503
 *    maintenance notice.
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

		if ( BootMode::Installation === $resolver->resolve() ) {
			$container->singleton(
				ExecutionHandlerInterface::class,
				fn ( Container $c ) : WebInstaller => new WebInstaller(
					$c->get( AppInstaller::class ),
					$c->get( InstallerSession::class ),
					$c->get( SetupToken::class ),
					$resolver,
					$c->get( FileSystem::class )
				)
			);
		} else {
			$container->singleton(
				ExecutionHandlerInterface::class,
				fn () : DowntimeExecutionHandler => new DowntimeExecutionHandler(
					$resolver->message(),
					$resolver->retry_after()
				)
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ) : void {}
}