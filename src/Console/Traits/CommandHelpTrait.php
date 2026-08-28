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
use SmartLicenseServer\Console\ConsoleOutput;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Global and per-command help printing, shared by runners (NonInteractiveRunner,
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
    protected Guard $guard;

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
     * Print application info (name, version, author)
     */
    protected function print_info(): void {
        $app_name = \SMLISER_APP_NAME;
        $version  = SMLISER_VER;
        $author   = 'Callistus Nwachukwu';

        $this->output->writeln( $this->colorize( ConsoleOutput::ANSI_BOLD, $app_name ) . ' v' . $version );
        $this->output->writeln( 'Author: ' . $this->colorize( ConsoleOutput::ANSI_CYAN, $author ) );
        $this->output->newline();
        $this->print_system_info();
    }

    /**
     * Print application version information.
     *
     * @return void
     */
    protected function print_version(): void {
        $this->output->writeln( 
            $this->colorize( ConsoleOutput::ANSI_BOLD, SMLISER_APP_NAME )
        );

        $this->output->writeln( sprintf( 'Version : %s', SMLISER_VER ) );
        $this->output->writeln(
            sprintf(
                'Runtime : PHP %s (%s)',
                PHP_VERSION,
                PHP_ZTS ? 'ZTS' : 'NTS'
            )
        );
        $this->output->writeln( sprintf( 'SAPI    : %s', PHP_SAPI ) );
        $this->output->writeln(
            sprintf(
                'OS      : %s (%s)',
                PHP_OS_FAMILY,
                php_uname( 'm' )
            )
        );
    }

    /**
     * Print stylized system info (hostname, OS, IP)
     */
    protected function print_system_info(): void {
        $hostname = function_exists( 'gethostname' ) ? ( gethostname() ?: 'Unknown' ) : 'Unknown';

        $os = 'Unknown OS';
        if ( function_exists( 'php_uname' ) ) {
            $os = trim( @php_uname( 's' ) . ' ' . @php_uname( 'r' ) ) ?: 'Unknown OS';
        }

        // gethostbyname() returns the input string UNCHANGED on failure
        // rather than false/null — that's the actual signal to check for,
        // not truthiness.
        $ip = 'Not resolvable';
        if ( function_exists( 'gethostbyname' ) && 'Unknown' !== $hostname ) {
            $resolved = @gethostbyname( $hostname );
            if ( $resolved && $resolved !== $hostname ) {
                $ip = $resolved;
            }
        }

        $current_user = 'Unknown';
        if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_geteuid' ) ) {
            $pw           = @posix_getpwuid( posix_geteuid() );
            $current_user = $pw['name'] ?? 'Unknown';
        } elseif ( function_exists( 'get_current_user' ) ) {
            $current_user = get_current_user() ?: 'Unknown';
        }

        $principal = $this->guard->get_principal();
        $auth_status = null === $principal
            ? 'Not authenticated'
            : sprintf( 'Authenticated (%s)', $principal->get_role()->get_label() );

        $info = [
            [ 'Application',    sprintf( '%s v%s', \SMLISER_APP_NAME, \SMLISER_VER ) ],
            [ 'Session',        $auth_status ],
            [ 'Server Time',    date( 'Y-m-d H:i:s T' ) ],
            [ 'PHP Version',    PHP_VERSION . ' (' . ( php_sapi_name() ?: 'Unknown' ) . ')' ],
            [ 'OS',             $os ],
            [ 'Hostname',       $hostname ],
            [ 'IP Address',     $ip ],
            [ 'Current User',   $current_user ],
            [ 'Working Dir',    getcwd() ?: 'Unknown' ],
        ];

        $width       = 80;
        $label_width = max( array_map( fn( $row ) => strlen( $row[0] ), $info ) );

        $this->output->writeln( str_repeat( '=', $width ) );
        $this->output->writeln( $this->colorize( ConsoleOutput::ANSI_BOLD, '  SYSTEM INFO' ) );
        $this->output->writeln( str_repeat( '-', $width ) );

        foreach ( $info as [ $label, $value ] ) {
            $this->output->writeln( sprintf(
                '  %s : %s',
                $this->colorize( ConsoleOutput::ANSI_YELLOW, str_pad( $label, $label_width ) ),
                $this->colorize( ConsoleOutput::ANSI_GREEN, $value )
            ) );
        }

        $this->output->writeln( str_repeat( '=', $width ) );
        $this->output->newline();
    }

    /**
     * Whether the argument list contains a help flag.
     *
     * @param array<int|string, mixed> $args
     * @return bool
     */
    protected function args_request_help( array $args ): bool {
        $help_tokens    = [ '--help', '-h', 'help' ];
        foreach ( $args as $key => $arg ) {

            if ( in_array( $key, $help_tokens, true ) && (bool) $key ) {
                return true;
            }

            if ( in_array( $arg, $help_tokens, true ) ) {
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

    abstract protected function colorize( string $code, string $message ): string;
}