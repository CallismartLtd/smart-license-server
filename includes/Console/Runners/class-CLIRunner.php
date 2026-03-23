<?php
/**
 * CLI runner class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Runners;

use SmartLicenseServer\Console\CommandRegistry;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Plain PHP CLI runner.
 *
 * Reads the command name from $argv, resolves it from the registry,
 * and calls execute(). Falls back to the help output when no command
 * is given or the command is not found.
 */
class CLIRunner implements RunnerInterface {

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

    /**
     * The raw argument vector from the CLI.
     *
     * @var array<int, string>
     */
    private array $argv;

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * @param CommandRegistry    $registry The command registry.
     * @param array<int, string> $argv     The raw $argv array from the CLI entry point.
     */
    public function __construct( CommandRegistry $registry, array $argv ) {
        $this->registry = $registry;
        $this->argv     = $argv;
    }

    /*
    |----------------------
    | RunnerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Resolves the command name from $argv[1], instantiates the command
     * class, and calls execute() with the remaining arguments.
     * Prints help if no command is given or the command is not found.
     */
    public function register(): void {
        $name = $this->argv[1] ?? 'help';
        $args = array_slice( $this->argv, 2 );

        if ( $name === 'help' || $name === '--help' || $name === '-h' ) {
            $this->print_help();
            return;
        }

        $class = $this->registry->get( $name );

        if ( $class === null ) {
            echo sprintf( 'Error: unknown command "%s".' . PHP_EOL, $name );
            echo PHP_EOL;
            $this->print_help();
            return;
        }

        ( new $class() )->execute( $args );
    }

    /*
    |----------------------
    | HELPERS
    |----------------------
    */

    /**
     * Print the help listing — all registered commands with descriptions.
     *
     * @return void
     */
    private function print_help(): void {
        echo 'Smart License Server CLI' . PHP_EOL;
        echo PHP_EOL;
        echo 'Usage:' . PHP_EOL;
        echo '  smliser [command]' . PHP_EOL;
        echo PHP_EOL;
        echo 'Commands:' . PHP_EOL;

        $commands = $this->registry->all();

        // Calculate padding from the longest command name.
        $max = max( array_map( 'strlen', array_keys( $commands ) ) );

        foreach ( $commands as $name => $class ) {
            $is_core = $this->registry->is_core( $name ) ? '' : ' [custom]';
            echo sprintf(
                '  %s  %s%s' . PHP_EOL,
                str_pad( $name, $max ),
                $class::description(),
                $is_core
            );
        }

        echo PHP_EOL;
    }
}