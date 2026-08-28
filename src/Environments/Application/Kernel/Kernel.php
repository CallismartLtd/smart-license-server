<?php
/**
 * Application kernel base class.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Kernel;

use SmartLicenseServer\Environment;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Abstract application kernel.
 *
 * The kernel coordinates application execution for a specific interface.
 * Concrete implementations define how the application is bootstrapped
 * and executed for that interface.
 */
abstract class Kernel {    
    /**
     * Bootstrap the application.
     *
     * @return static The kernel instance.
     */
    abstract public function boot(): static;

    /**
     * Execute the application lifecycle.
     *
     * @return static The kernel instance.
     */
    abstract public function run(): static;

    /**
     * Terminate the current request.
     * 
     * @return never
     */
    abstract public function terminate() : never;
}