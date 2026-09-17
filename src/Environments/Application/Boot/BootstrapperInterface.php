<?php
/**
 * Bootstrapper interface definition file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\Core\Container\Container;

/**
 * Interface BootstrapperInterface
 *
 * Contract for context-specific service registration and application initialization tasks.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
interface BootstrapperInterface {

    /**
     * Register context-specific service bindings into the container.
     *
     * @param Container $container The dependency injection container.
     * @return void
     */
    public function register( Container $container ) : void;

    /**
     * Execute post-registration runtime initialization tasks.
     *
     * @param Container $container The dependency injection container.
     * @return void
     */
    public function boot( Container $container ) : void;

    /**
     * Determine whether the bootstrapper is eligible to run in the current execution context.
     *
     * @return bool True if the bootstrapper should execute; false otherwise.
     */
    public function isEligible() : bool;
}