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

use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Console\CommandRegistry;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Plain PHP CLI runner.
 *
 * Reads the command name from $argv, resolves it from the registry,
 * and calls execute(). Handles global and per-command help output.
 *
 * Supported help patterns:
 *   smliser help              → global command listing
 *   smliser help <command>    → per-command synopsis + help
 *   smliser <command> --help  → same as above
 *   smliser <command> -h      → same as above
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
     * Resolution order:
     *   1. No command            → global help
     *   2. `help`                → global help
     *   3. `help <command>`      → per-command help
     *   4. `--help` / `-h`       → global help
     *   5. `<command> --help`    → per-command help
     *   6. `<command> -h`        → per-command help
     *   7. `<command> [args...]` → execute command
     *   8. Unknown command       → error + global help
     */
    public function register(): void {
        $name = $this->argv[1] ?? null;
        $args = array_slice( $this->argv, 2 );

        // No command given.
        if ( $name === null ) {
            $this->print_global_help();
            return;
        }

        if ( in_array( $name, ['version', '--version', '-v'] ) ) {
            $this->print_info();
            return;
        }

        // Global help flags.
        if ( in_array( $name, [ 'help', '--help', '-h' ], true ) ) {
            // `smliser help <command>` — per-command help.
            $target = $args[0] ?? null;

            if ( $target && $this->registry->has( $target ) ) {
                $this->print_command_help( $this->registry->get( $target ) );
                return;
            }

            $this->print_global_help();
            return;
        }

        // Resolve the command class.
        $class = $this->registry->get( $name );

        if ( $class === null ) {
            $this->print_error( sprintf( 'Unknown command "%s".', $name ) );
            echo PHP_EOL;
            $this->print_global_help();
            return;
        }

        // Per-command help flags anywhere in args.
        if ( $this->args_request_help( $args ) ) {
            $this->print_command_help( $class );
            return;
        }

        ( new $class() )->execute( $args );
    }

    /*
    |----------------------
    | HELP PRINTERS
    |----------------------
    */

    /**
     * Print the global command listing.
     *
     * @return void
     */
    private function print_global_help(): void {
        echo 'Smart License Server CLI' . PHP_EOL;
        echo PHP_EOL;
        echo 'Usage:' . PHP_EOL;
        echo '  smliser [command]' . PHP_EOL;
        echo '  smliser help <command>' . PHP_EOL;
        echo PHP_EOL;
        echo 'Commands:' . PHP_EOL;

        $commands = $this->registry->all();
        $max      = max( array_map( 'strlen', array_keys( $commands ) ) );

        ksort( $commands, \SORT_ASC );

        foreach ( $commands as $cmd_name => $class ) {
            $custom_marker = $this->registry->is_custom( $cmd_name ) ? ' [custom]' : '';
            echo sprintf(
                '  %s  %s%s' . PHP_EOL,
                str_pad( $cmd_name, $max ),
                $class::description(),
                $custom_marker
            );
        }

        echo PHP_EOL;
        echo 'Run `smliser help <command>` for detailed usage of any command.' . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Print per-command help — synopsis, description, and detailed help body.
     *
     * @param class-string<CommandInterface> $class
     * @return void
     */
    private function print_command_help( string $class ): void {
        echo PHP_EOL;
        echo 'Command: ' . $class::name() . PHP_EOL;
        echo PHP_EOL;

        echo 'Description:' . PHP_EOL;
        echo '  ' . $class::description() . PHP_EOL;
        echo PHP_EOL;

        $synopsis = $class::synopsis();
        if ( $synopsis !== '' ) {
            echo 'Usage:' . PHP_EOL;
            echo '  ' . $synopsis . PHP_EOL;
            echo PHP_EOL;
        }

        $help = $class::help();
        if ( $help !== '' ) {
            echo $help . PHP_EOL;
            echo PHP_EOL;
        }
    }

    /**
     * Print application info
     */
    private function print_info() : void {
        $app_name = \SMLISER_APP_NAME;
        $version  = SMLISER_VER;
        $author   = 'Callistus Nwachukwu';

        echo $app_name . ' v' . $version . PHP_EOL;
        echo 'Author: ' . $author . PHP_EOL;
        echo PHP_EOL;
    }

    /*
    |----------------------
    | PRIVATE HELPERS
    |----------------------
    */

    /**
     * Whether the argument list contains a help flag.
     *
     * @param array<int|string, mixed> $args
     * @return bool
     */
    private function args_request_help( array $args ): bool {
        foreach ( $args as $arg ) {
            if ( in_array( $arg, [ '--help', '-h' ], true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Print a formatted error line to stdout.
     *
     * @param string $message
     * @return void
     */
    private function print_error( string $message ): void {
        echo 'Error: ' . $message . PHP_EOL;
    }
}