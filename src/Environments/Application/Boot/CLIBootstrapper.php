<?php
/**
 * CLI environment bootstrapper class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\ConsoleInput;
use SmartLicenseServer\Console\ConsoleOutput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Console\Terminal;
use SmartLicenseServer\Environments\Application\Boot\BootstrapperInterface;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Environments\Application\Auth\ConsoleIdentityProvider;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\CLI\ConsoleDispatcher;
use SmartLicenseServer\Environments\Application\CLI\ConsoleExecutionHandler;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Class CLIBootstrapper
 *
 * Handles container bindings and boot initialization for command-line interface execution.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
class CLIBootstrapper implements BootstrapperInterface {

    /**
     * {@inheritdoc}
     */
    public function isEligible() : bool {
        return \is_cli();
    }

    /**
     * {@inheritdoc}
     */
    public function register( Container $container ) : void {
        $container->singleton(
            IdentityService::class,
            fn ( Container $c ) : IdentityService => new IdentityService(
                $c->get( Guard::class ),
                $c->get( ConsoleIdentityProvider::class )
            )
        );

        $container->singleton(
            CommandRegistry::class,
            fn ( Container $c ) : CommandRegistry => CommandRegistry::instance( $c )
        );

        $container->set(
            ConsoleInput::class,
            fn ( Container $c ) : ConsoleInput => new ConsoleInput(
                $c->get( Terminal::class )
            )
        );

        $container->set(
            ConsoleOutput::class,
            fn ( Container $c ) : ConsoleOutput => new ConsoleOutput(
                $c->get( Terminal::class )
            )
        );

        $container->singleton(
            ScriptName::class,
            fn () : ScriptName => ScriptName::fromGlobals()
        );
        
        $container->singleton(
            ExecutionHandlerInterface::class,
            fn ( Container $c ) : ConsoleExecutionHandler => new ConsoleExecutionHandler(
                $c->get( ConsoleDispatcher::class )
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot( Container $container ) : void {
        $container->alias( InputInterface::class, ConsoleInput::class );
        $container->alias( OutputInterface::class, ConsoleOutput::class );
    }
}