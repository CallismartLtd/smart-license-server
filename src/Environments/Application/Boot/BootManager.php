<?php
/**
 * Boot manager class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\Core\Container\Container;

/**
 * Class BootManager
 *
 * Manages and orchestrates the lifecycle of environment bootstrappers,
 * executing registration and boot sequences for eligible context handlers.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
final class BootManager {

    /**
     * Registered bootstrappers stack.
     *
     * @var BootstrapperInterface[]
     */
    private array $bootstrappers = [];

    /**
     * Class constructor.
     *
     * @param Container $container The dependency injection container.
     */
    public function __construct( private Container $container ) {}

    /**
     * Add a bootstrapper instance to the management stack.
     *
     * @param BootstrapperInterface $bootstrapper The bootstrapper instance.
     * @return self
     */
    public function add( BootstrapperInterface $bootstrapper ) : self {
        $this->bootstrappers[] = $bootstrapper;
        return $this;
    }

    /**
     * Execute container registrations for all eligible bootstrappers.
     *
     * @return void
     */
    public function registerAll() : void {
        foreach ( $this->bootstrappers as $bootstrapper ) {
            if ( $bootstrapper->isEligible() ) {
                $bootstrapper->register( $this->container );
            }
        }
    }

    /**
     * Execute post-registration boot logic for all eligible bootstrappers.
     *
     * @return void
     */
    public function bootAll() : void {
        foreach ( $this->bootstrappers as $bootstrapper ) {
            if ( $bootstrapper->isEligible() ) {
                $bootstrapper->boot( $this->container );
            }
        }
    }
}