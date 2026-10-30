<?php
/**
 * Extension bootstrapper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Environments\Application\Extensions\Extension;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionAutoloader;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionException;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionInterface;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionManager;

/**
 * Class ExtensionBootstrapper
 *
 * Registers and boots the enabled extensions through the same two phases as
 * every other bootstrapper.
 *
 *  - register(): resolves the enabled extensions (ExtensionManager::resolve()),
 *    makes their classes loadable, builds each entry class through the
 *    container and calls its register(), in dependency order.
 *  - boot(): calls each registered extension's boot(), in the same order.
 *
 * Added to BootManager after the context bootstrapper (CLI, Web, Cron), so
 * extensions boot after the core routes, registries and theme services
 * exist.
 *
 * Eligible only in BootMode::Normal: not while the app is being installed,
 * and not during maintenance or an upgrade (the CLI included), when an
 * extension could run against a schema that is mid-upgrade.
 *
 * An extension that throws, or whose dependency failed, is left out for the
 * request and logged; the rest still load. Nothing is written back to the
 * enabled list.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
final class ExtensionBootstrapper implements BootstrapperInterface {

	/**
	 * Extensions whose register() ran, by slug, in load order.
	 *
	 * @var array<string, Extension>
	 */
	private array $extensions = [];

	/**
	 * Entry instances of the extensions whose register() ran, by slug.
	 *
	 * @var array<string, ExtensionInterface>
	 */
	private array $instances = [];

	/**
	 * Slugs of the extensions that failed or were left out, by slug.
	 *
	 * @var array<string, true>
	 */
	private array $failed = [];

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
		return BootMode::Normal === $this->resolver->resolve();
	}

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ) : void {
		$resolved = $container->get( ExtensionManager::class )->resolve();
		$loader   = $container->get( ExtensionAutoloader::class );

		foreach ( $resolved['skipped'] as $slug => $reason ) {
			$this->failed[ $slug ] = true;

			\smliser_log_error( sprintf( '[Extension] Left out "%s": %s', $slug, $reason ) );
		}

		foreach ( $resolved['load'] as $extension ) {
			$blocker = $this->failed_dependency( $extension );

			if ( null !== $blocker ) {
				$this->failed[ $extension->slug ] = true;

				\smliser_log_error( sprintf( '[Extension] Left out "%s": its dependency "%s" failed.', $extension->slug, $blocker ) );
				continue;
			}

			try {
				$loader->add( $extension );

				$instance = $container->get( $extension->entry );

				if ( ! $instance instanceof ExtensionInterface ) {
					throw new ExtensionException( sprintf( 'The entry class "%s" of the extension "%s" does not implement %s.', $extension->entry, $extension->slug, ExtensionInterface::class ) );
				}

				$instance->register( $container );

				$this->extensions[ $extension->slug ] = $extension;
				$this->instances[ $extension->slug ]  = $instance;
			} catch ( \Throwable $e ) {
				$this->failed[ $extension->slug ] = true;

				\smliser_log_error( sprintf( '[Extension] "%s" failed to register: %s', $extension->slug, $e->getMessage() ) );
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot( Container $container ) : void {
		foreach ( $this->instances as $slug => $instance ) {
			$blocker = $this->failed_dependency( $this->extensions[ $slug ] );

			if ( null !== $blocker ) {
				$this->failed[ $slug ] = true;

				\smliser_log_error( sprintf( '[Extension] Not booting "%s": its dependency "%s" failed.', $slug, $blocker ) );
				continue;
			}

			try {
				$instance->boot( $container );
			} catch ( \Throwable $e ) {
				$this->failed[ $slug ] = true;

				\smliser_log_error( sprintf( '[Extension] "%s" failed to boot: %s', $slug, $e->getMessage() ) );
			}
		}
	}

	/**
	 * The first dependency of an extension that failed or was left out.
	 *
	 * @param Extension $extension
	 * @return string|null Its slug, or null when none failed.
	 */
	private function failed_dependency( Extension $extension ) : ?string {
		foreach ( $extension->depends as $dependency ) {
			if ( isset( $this->failed[ $dependency ] ) ) {
				return $dependency;
			}
		}

		return null;
	}
}