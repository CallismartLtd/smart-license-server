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
use SmartLicenseServer\Console\Commands\AppCommand;
use SmartLicenseServer\Console\Commands\CacheCommand;
use SmartLicenseServer\Console\Commands\InstallRolesCommand;
use SmartLicenseServer\Console\Commands\MigrateCommand;
use SmartLicenseServer\Console\Commands\ScheduleCommand;
use SmartLicenseServer\Console\Commands\WorkCommand;
use SmartLicenseServer\Console\Commands\WorkScheduleCommand;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Central console command registry.
 */
class CommandRegistry {

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

    /*
    |----------------------
    | REGISTRY STATE
    |----------------------
    */

    /**
     * Core command class strings keyed by command name.
     *
     * Core commands are registered at bootstrap and are never
     * removable — they form the guaranteed public API of the system.
     *
     * @var array<string, class-string<CommandInterface>>
     */
    private array $core = [];

    /**
     * Custom command class strings keyed by command name.
     *
     * Custom commands are registered by third-party integrators and
     * can be removed at any time before the runner dispatches.
     *
     * @var array<string, class-string<CommandInterface>>
     */
    private array $custom = [];

    /**
     * Flags whether core commands has been registered.
     * 
     * @var bool $core_booted
     */
    private bool $core_booted   = false;

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * Private constructor — use instance().
     */
    private function __construct() {
        $this->boot_core();
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
     * @return static Fluent.
     */
    private function boot_core(): static {

        if ( $this->core_booted ) {
            return $this;
        }

        $this->core_booted = true;

        $core_commands  = [
            WorkCommand::class,
            ScheduleCommand::class,
            WorkScheduleCommand::class,
            MigrateCommand::class,
            InstallRolesCommand::class,
            CacheCommand::class,
            AppCommand::class
        ];

        foreach ( $core_commands as $class ) {
            $this->register_core( $class );
        }

        return $this;
    }

    /*
    |--------------------------------------------
    | CUSTOM REGISTRATION
    |--------------------------------------------
    */

    /**
     * Register a custom command.
     *
     * Custom commands are registered by third-party integrators.
     * They can be removed at any time via unregister().
     *
     * If a core command with the same name exists, registration is
     * silently skipped — core commands always take precedence.
     *
     * If a custom command with the same name already exists it is
     * replaced without error.
     *
     * @param class-string<CommandInterface> $class Fully-qualified command class name.
     * @return static Fluent.
     * @throws InvalidArgumentException If the class does not implement CommandInterface.
     */
    public function register( string $class ): static {
        $this->assert_implements_interface( $class );

        $name = $class::name();

        // Core commands always win — skip silently.
        if ( isset( $this->core[ $name ] ) ) {
            return $this;
        }

        $this->custom[ $name ] = $class;

        return $this;
    }

    /**
     * Register multiple custom commands at once.
     *
     * @param array<class-string<CommandInterface>> $classes
     * @return static Fluent.
     */
    public function register_many( array $classes ): static {
        foreach ( $classes as $class ) {
            $this->register( $class );
        }

        return $this;
    }

    /*
    |--------------------------------------------
    | REMOVAL
    |--------------------------------------------
    */

    /**
     * Unregister a custom command by name.
     *
     * Core commands cannot be removed — this method silently ignores
     * attempts to unregister a core command so third-party code cannot
     * accidentally break the system contract.
     *
     * @param string $name The command name e.g. 'work:schedule'.
     * @return bool True if a custom command was removed, false otherwise.
     */
    public function unregister( string $name ): bool {
        if ( isset( $this->core[ $name ] ) ) {
            return false; // Core commands are protected.
        }

        if ( isset( $this->custom[ $name ] ) ) {
            unset( $this->custom[ $name ] );
            return true;
        }

        return false;
    }

    /*
    |--------------------------------------------
    | RETRIEVAL
    |--------------------------------------------
    */

    /**
     * Return all registered commands — core and custom — keyed by name.
     *
     * @return array<string, class-string<CommandInterface>>
     */
    public function all(): array {
        return array_merge( $this->core, $this->custom );
    }

    /**
     * Return only core commands keyed by name.
     *
     * @return array<string, class-string<CommandInterface>>
     */
    public function core(): array {
        return $this->core;
    }

    /**
     * Return only custom commands keyed by name.
     *
     * @return array<string, class-string<CommandInterface>>
     */
    public function custom(): array {
        return $this->custom;
    }

    /**
     * Return a single command class string by name.
     *
     * Looks in core first, then custom.
     *
     * @param string $name The command name.
     * @return class-string<CommandInterface>|null The command class, or null if not found.
     */
    public function get( string $name ): ?string {
        return $this->core[ $name ] ?? $this->custom[ $name ] ?? null;
    }

    /**
     * Whether a command with the given name is registered.
     *
     * @param string $name The command name.
     * @return bool
     */
    public function has( string $name ): bool {
        return isset( $this->core[ $name ] ) || isset( $this->custom[ $name ] );
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
    private function assert_implements_interface( string $class ): void {
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