<?php
/**
 * Application kernel base class.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Kernel\Application;

use SmartLicenseServer\Environments\EnvironmentProviderInterface;

/**
 * Abstract application kernel.
 *
 * The kernel coordinates application execution for a specific interface.
 * Concrete implementations define how the application is bootstrapped
 * and executed for that interface.
 */
abstract class Kernel {
    /**
     * Create instance.
     * 
     * @param EnvironmentProviderInterface $environment
     */
    private function __construct( protected EnvironmentProviderInterface $environment ) {}

    /**
     * Create instance.
     */
    public static function create( EnvironmentProviderInterface $environment ) : static {
        return new static( $environment );
    }
    
    /**
     * Bootstrap the application.
     *
     * @return void
     */
    abstract public function boot(): void;

    /**
     * Execute the application lifecycle.
     *
     * @return int Process exit status.
     */
    abstract public function run(): int;
}