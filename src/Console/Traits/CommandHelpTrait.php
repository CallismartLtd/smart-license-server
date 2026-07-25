<?php
/**
 * Command help trait file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Traits
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Traits;

use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\Contracts\OutputInterface;

/**
 * Global and per-command help printing, shared by runners (CLIRunner,
 * InteractiveShell) rather than by leaf commands — nothing in
 * CommandInterface requires a command to print help about itself or
 * about its siblings, only a runner dispatching between them does.
 *
 * Consumers of this trait must have:
 *   - protected \SartLicenseServer\Console\CommandRegistry $registry
 *   - protected \SmartLicenseServer\Console\OutputInterface $output
 */
trait CommandHelpTrait {
    protected OutputInterface $output;
    protected CommandRegistry $registry;

    /**
     * Print the global command listing.
     *
     * @return void
     */
    protected function print_global_help(): void {
        $this->output->writeln( \sprintf( '%s CLI', SMLISER_APP_NAME ) );
        $this->output->newline();
        $this->output->writeln( 'Usage:' );
        $this->output->writeln( '  smliser [command]' );
        $this->output->writeln( '  smliser help <command>' );
        $this->output->newline();
        $this->output->writeln( 'Commands:' );

        $commands = $this->registry->all();
        $max      = max( array_map( 'strlen', array_keys( $commands ) ) );

        ksort( $commands, \SORT_ASC );

        foreach ( $commands as $cmd_name => $class ) {
            $custom_marker = $this->registry->is_custom( $cmd_name ) ? ' [custom]' : '';
            $this->output->writeln( sprintf(
                '  %s  %s%s',
                str_pad( $cmd_name, $max ),
                $class::description(),
                $custom_marker
            ) );
        }

        $this->output->newline();
        $this->output->writeln( 'Run `smliser help <command>` for detailed usage of any command.' );
    }

    /**
     * Print per-command help — synopsis, description, and detailed
     * help body.
     *
     * @param class-string $class
     * @return void
     */
    protected function print_command_help( string $class ): void {
        $this->output->newline();
        $this->output->writeln( 'Command: ' . $class::name() );
        $this->output->newline();

        $this->output->writeln( 'Description:' );
        $this->output->writeln( '  ' . $class::description() );
        $this->output->newline();

        $synopsis = $class::synopsis();

        if ( '' !== $synopsis ) {
            $this->output->writeln( 'Usage:' );
            $this->output->writeln( '  ' . $synopsis );
            $this->output->newline();
        }

        $help = $class::help();

        if ( '' !== $help ) {
            $this->output->writeln( $help );
            $this->output->newline();
        }
    }

    /**
     * Print application info (name, version, author).
     *
     * @return void
     */
    protected function print_info(): void {
        $this->output->writeln( sprintf( '%s v%s', \SMLISER_APP_NAME, \SMLISER_VER ) );
        $this->output->writeln( 'Author: Callistus Nwachukwu' );
        $this->output->newline();
    }

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
     * Print a formatted error, with a leading blank line for visual
     * separation from whatever was printed before it.
     *
     * @param string $message
     * @return void
     */
    protected function print_error( string $message ): void {
        $this->output->error( $message );
    }
}