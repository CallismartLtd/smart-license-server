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
use SmartLicenseServer\Console\Commands\SmliserCommand;

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
class CLIRunner extends SmliserCommand implements RunnerInterface {

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

        if ( str_starts_with( $name, '-' ) || in_array( $name, ['help', 'version'], true ) ) {
            $print_info = in_array( $name, [ 'version', '-v', '--version' ] );

            if ( $print_info ) {
                $this->print_info();
                return;
            }

            $print_help = in_array( $name, [ 'help', '-h', '--help' ], true );
            
            // Global help flags.
            if ( $print_help ) {
                // `smliser help <command>` — per-command help.
                $target = $args[0] ?? null;

                if ( $target && $this->registry->has( $target ) ) {
                    $this->print_command_help( $this->registry->get( $target ) );
                    return;
                }

                $this->print_global_help();
                return;
            }
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

}