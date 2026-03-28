<?php
/**
 * The core smliser command.
 * 
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\Runners\CLIRunner;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The core smliser command handles the base commands and flags in `smliser`
 */
abstract class SmliserCommand implements CommandInterface {
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
    protected CommandRegistry $registry;

    /**
     * The raw argument vector from the CLI.
     *
     * @var array<int, string>
     */
    protected array $argv;

    public static function name() : string {
        return 'smliser';
    }

    public static function synopsis() : string {
        return 'smliser';
    }

    public static function description(): string {
        return 'Smart License Server CLI.';
    }

    public static function help(): string {
        return '';
    }

    public function execute( array $args = [] ) : void {

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
    protected function print_global_help(): void {
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
    protected function print_command_help( string $class ): void {
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
    protected function print_info() : void {
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
    protected function args_request_help( array $args ): bool {
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
    protected function print_error( string $message ): void {
        echo 'Error: ' . $message . PHP_EOL;
    }
}