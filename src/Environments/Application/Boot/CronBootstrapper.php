<?php
/**
 * Cron bootstrapper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Boot
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Web\CronExecutionHandler;

/**
 * Binds the execution handler for requests to the public cron URL.
 *
 * Registered in place of WebBootstrapper and DowntimeBootstrapper (see
 * ApplicationEnvironment), so a cron request sets up no routes, sessions,
 * identity or assets: it answers 204 and runs the due background work.
 */
final class CronBootstrapper implements BootstrapperInterface {

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
		return ! \is_cli();
	}

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ) : void {
		$resolver = $this->resolver;

		$container->singleton(
			ExecutionHandlerInterface::class,
			fn ( Container $c ) : CronExecutionHandler => new CronExecutionHandler( $c, $resolver )
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ) : void {}
}
