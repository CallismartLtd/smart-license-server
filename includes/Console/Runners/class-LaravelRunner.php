<?php
/**
 * Laravel runner class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Runners;

use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\CommandInterface;
use Illuminate\Console\Command as ArtisanCommand;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Laravel Artisan runner.
 *
 * Wraps each registered command as an anonymous Artisan command class
 * and registers it with the Laravel application:
 *
 *   php artisan smliser:work
 *   php artisan smliser:work:schedule
 *   php artisan smliser:migrate
 *
 * Usage — call from your AppServiceProvider::boot():
 *
 *   ( new LaravelRunner( CommandRegistry::instance() ) )->register();
 *
 * Only active when the Laravel application container is available
 * (the 'app' function exists and resolves a console kernel).
 */
class LaravelRunner implements RunnerInterface {

    /*
    |----------------------
    | CONSTANTS
    |----------------------
    */

    /**
     * Artisan command name prefix.
     *
     * All commands are registered as `smliser:<command-name>` to
     * keep them grouped and avoid collisions with application commands.
     *
     * @var string
     */
    const ARTISAN_PREFIX = 'smliser:';

    /*
    |----------------------
    | DEPENDENCIES
    |----------------------
    */

    /**
     * The command registry.
     *
     * @var CommandRegistry
     */
    private CommandRegistry $registry;

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * @param CommandRegistry $registry The command registry.
     */
    public function __construct( CommandRegistry $registry ) {
        $this->registry = $registry;
    }

    /*
    |----------------------
    | RunnerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Iterates the registry, wraps each command in an anonymous
     * Artisan command class, and loads it into the Laravel application.
     *
     * Does nothing if the Laravel application is not available.
     */
    public function register(): void {
        if ( ! $this->is_laravel() ) {
            return;
        }

        $artisan_commands = [];

        foreach ( $this->registry->all() as $name => $class ) {
            $artisan_commands[] = $this->make_artisan_command( $name, $class );
        }

        // Register all commands with the Laravel application.
        app( \Illuminate\Contracts\Console\Kernel::class )
            ->resolveCommands( $artisan_commands );
    }

    /*
    |----------------------
    | HELPERS
    |----------------------
    */

    /**
     * Build an anonymous Artisan command class wrapping a CommandInterface.
     *
     * @param string                         $name  The command name.
     * @param class-string<CommandInterface> $class The command class.
     * @return class-string<ArtisanCommand> Anonymous class string.
     */
    private function make_artisan_command( string $name, string $class ): ArtisanCommand {
        $artisan_name = static::ARTISAN_PREFIX . $name;
        $description  = $class::description();

        return new class( $artisan_name, $description, $class ) extends ArtisanCommand {

            /**
             * @var string
             */
            protected $signature;

            /**
             * @var string
             */
            protected $description;

            /**
             * @var class-string<CommandInterface>
             */
            private string $command_class;

            public function __construct( string $signature, string $description, string $command_class ) {
                $this->signature     = $signature;
                $this->description   = $description;
                $this->command_class = $command_class;

                parent::__construct();
            }

            public function handle(): void {
                ( new $this->command_class() )->execute( $this->arguments() );
            }
        };
    }

    /**
     * Whether the Laravel application container is available.
     *
     * @return bool
     */
    private function is_laravel(): bool {
        return function_exists( 'app' ) && app() instanceof \Illuminate\Contracts\Foundation\Application;
    }
}