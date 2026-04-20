<?php
/**
 * Command registry class file.
 *
 * Central registry for all console commands. Core commands are
 * registered at bootstrap and cannot be removed. Custom commands
 * can be registered and unregistered freely by third parties.
 *
 * ## Registering commands
 *
 *   // Core (protected — cannot be unregistered)
 *   CommandRegistry::instance()->register_core( WorkCommand::class );
 *
 *   // Custom (removable)
 *   CommandRegistry::instance()->register( MyCommand::class );
 *
 * ## Removing custom commands
 *
 *   CommandRegistry::instance()->unregister( 'my:command' );
 *
 * ## Retrieving commands
 *
 *   CommandRegistry::instance()->all();
 *   CommandRegistry::instance()->core();
 *   CommandRegistry::instance()->custom();
 *   CommandRegistry::instance()->get( 'work:schedule' );
 *   CommandRegistry::instance()->has( 'migrate' );
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use InvalidArgumentException;
use RuntimeException;
use SmartLicenseServer\Console\Commands\Apps\AppCommand;
use SmartLicenseServer\Console\Commands\Apps\PluginCommand;
use SmartLicenseServer\Console\Commands\Apps\SoftwareCommand;
use SmartLicenseServer\Console\Commands\Apps\ThemeCommand;
use SmartLicenseServer\Console\Commands\CacheCommand;
use SmartLicenseServer\Console\Commands\InstallRolesCommand;
use SmartLicenseServer\Console\Commands\MigrateCommand;
use SmartLicenseServer\Console\Commands\ScheduleCommand;
use SmartLicenseServer\Console\Commands\SettingsCommand;
use SmartLicenseServer\Console\Commands\WhoAmI;
use SmartLicenseServer\Console\Commands\WorkCommand;
use SmartLicenseServer\Console\Commands\WorkScheduleCommand;
use SmartLicenseServer\Contracts\AbstractRegistry;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Central console command registry.
 * 
 * @method array<string, class-string<CommandInterface>> all() Get all registered coomands.
 * @method class-string<CommandInterface>|null get( string $coomand ) Get a command from registry.
 * @method bool has( string $coomand ) Tells whether a command exists in the registry.
 */
class CommandRegistry extends AbstractRegistry {

    /*
    |----------------------
    | SINGLETON
    |----------------------
    */

    /**
     * Singleton instance.
     *
     * @var static|null
     */
    private static ?self $instance = null;

    /**
     * Core commands.
     * 
     * @var array<int, class-string<CommandInterface>>
     */
    private array $core_commands  = [
        WhoAmI::class,
        WorkCommand::class,
        ScheduleCommand::class,
        WorkScheduleCommand::class,
        MigrateCommand::class,
        InstallRolesCommand::class,
        CacheCommand::class,
        AppCommand::class,
        PluginCommand::class,
        ThemeCommand::class,
        SoftwareCommand::class,
        SettingsCommand::class
    ];

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * Private constructor — use instance().
     */
    private function __construct() {
        $this->load_core();
    }

    /*
    |----------------------
    | SINGLETON ACCESS
    |----------------------
    */

    /**
     * Return the singleton instance.
     *
     * @return static
     */
    public static function instance(): static {
        if ( static::$instance === null ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /*
    |--------------------------------------------
    | CORE REGISTRATION
    |--------------------------------------------
    */

    /**
     * Register a core command.
     *
     * Core commands are part of the system contract and cannot be
     * removed via unregister(). Attempting to register a core command
     * whose name conflicts with an existing core command throws.
     * Conflicts with custom commands silently promote the core command
     * and remove the custom one — core always wins.
     *
     * @param class-string<CommandInterface> $class Fully-qualified command class name.
     * @return static Fluent.
     * @throws InvalidArgumentException If the class does not implement CommandInterface.
     * @throws RuntimeException         If a core command with the same name already exists.
     */
    private function register_core( string $class ): static {
        $this->assert_implements_interface( $class );

        $name = $class::name();

        if ( isset( $this->core[ $name ] ) ) {
            throw new RuntimeException(
                sprintf( 'CommandRegistry: core command "%s" is already registered.', $name )
            );
        }

        // Core silently wins over any existing custom command with the same name.
        unset( $this->custom[ $name ] );

        $this->core[ $name ] = $class;

        return $this;
    }

    /**
     * Boot core commands at once.
     *
     * @return void.
     */
    protected function load_core() : void {

        if ( $this->core_loaded ) {
            return;
        }

        foreach ( $this->core_commands as $class ) {
            $this->register_core( $class );
        }

        unset( $this->core_commands );

        $this->core_loaded = true;

    }

    /*
    |--------------------------------------------
    | CUSTOM REGISTRATION
    |--------------------------------------------
    */

    /**
     * Register multiple custom commands at once.
     *
     * @param array<class-string<CommandInterface>> $classes
     * @return static Fluent.
     */
    public function register_many( array $classes ): static {
        foreach ( $classes as $class ) {
            $this->add( $class );
        }

        return $this;
    }

    /**
     * Whether a command is a core command.
     *
     * @param string $name The command name.
     * @return bool
     */
    public function is_core( string $name ): bool {
        return isset( $this->core[ $name ] );
    }

    /**
     * Whether a command is a custom command.
     *
     * @param string $name The command name.
     * @return bool
     */
    public function is_custom( string $name ): bool {
        return isset( $this->custom[ $name ] );
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Assert that a class implements CommandInterface.
     *
     * @param string $class
     * @throws InvalidArgumentException
     */
    protected function assert_implements_interface( string $class ): void {
        if ( ! class_exists( $class ) ) {
            throw new InvalidArgumentException(
                sprintf( 'CommandRegistry: class "%s" does not exist.', $class )
            );
        }

        if ( ! in_array( CommandInterface::class, class_implements( $class ) ?: [], true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'CommandRegistry: "%s" must implement %s.',
                    $class,
                    CommandInterface::class
                )
            );
        }
    }
}
