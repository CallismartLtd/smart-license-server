<?php
/**
 * Command router class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use SmartLicenseServer\Console\Contracts\CommandInterface;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\Traits\CommandHelpTrait;

/**
 * Routes a resolved command/subcommand pair to the appropriate handler.
 */
abstract class AbstractCommandRouter {

    use CommandHelpTrait;

    /**
     * @param CommandRegistry $registry
     * @param InputInterface  $io
     * @param OutputInterface $output
     * @param Terminal $terminal Needed here only to decide
     *                                       whether to colorize the
     *                                       prompt/banner — everything
     *                                       else goes through $output.
     */
    public function __construct(
        protected CommandRegistry $registry,
        protected InputInterface $io,
        protected OutputInterface $output,
        protected Terminal $terminal,
        protected ?SignalManager $signal = null

    ) {
        if ( ! isset( $this->signal ) ) {
            $this->signal = new SignalManager( $this->terminal );
        }
    }

    /*
    |--------------------------------------------
    | INVOCATION SPLITTING
    |--------------------------------------------
    */

    /**
     * Split a token list into [command, subcommand, remaining args].
     *
     * Shared by NonInteractiveRunner (tokens = $argv with the script name already
     * stripped) and InteractiveShell (tokens = one tokenized input line)
     * so the "is the second token a subcommand or the start of the
     * option/argument list" heuristic exists in exactly one place.
     *
     * A second token is treated as a subcommand unless it starts with
     * "-", in which case it's assumed to be an option and left in the
     * remaining args instead (e.g. `license --force` should not treat
     * "--force" as a subcommand named "--force").
     *
     * @param string[] $tokens
     * @return array{0: string|null, 1: string|null, 2: string[]}
     */
    protected function split_invocation( array $tokens ): array {
        $command    = $tokens[0] ?? null;
        $subcommand = $tokens[1] ?? null;

        if ( null !== $subcommand && str_starts_with( $subcommand, '-' ) ) {
            $subcommand = null;
        }

        $slice = null === $subcommand ? 1 : 2;
        $args  = array_slice( $tokens, $slice );

        return [ $command, $subcommand, $args ];
    }

    /*
    |--------------------------------------------
    | ROUTING
    |--------------------------------------------
    */

    /**
     * Route a resolved command/subcommand pair to its handler.
     *
     * @param CommandInput $command_input The parsed arguments/options.
     * @param string|null  $command       The resolved command name, or
     *                                    null when none was given at all
     *                                    (e.g. bare `smliser`).
     * @param string|null  $subcommand    Optional subcommand, or — for
     *                                    the help/`-h`/`--help` pseudo
     *                                    -command — the target command
     *                                    name to show help for.
     * @return int Process exit code — 0 for success, 1 for a routing
     *             failure (unknown command/subcommand).
     */
    protected function route_command( CommandInput $command_input, ?string $command, ?string $subcommand ): int {
        if ( null === $command ) {
            $this->print_global_help();
            return 0;
        }

        if ( in_array( $command, [ 'version', '-v', '--version' ], true ) ) {
            $this->print_version();
            return 0;
        }

        if ( in_array( $command, [ 'help', '-h', '--help' ], true ) ) {
            if ( null !== $subcommand && $this->registry->has( $subcommand ) ) {
                $this->print_command_help( $this->registry->get( $subcommand ) );
            } else {
                $this->print_global_help();
            }

            return 0;
        }

        $class = $this->registry->get( $command );

        if ( null === $class ) {
            $this->print_error( sprintf( 'Unknown command "%s".', $command ) );
            return 1;
        }

        $has_command_help   = $this->args_request_help(
            array_merge(
                $command_input->get_arguments(),
                $command_input->get_options()
            )
        );

        if ( $has_command_help ) {
            $this->print_command_help( $class );

            return 0;
        }

        /** @var CommandInterface $command_instance */
        $command_instance = new $class( $this->io, $this->output );

        if ( null !== $subcommand ) {
            $subcommands = $command_instance->get_subcommands();

            if ( ! array_key_exists( $subcommand, $subcommands ) ) {
                $this->print_error( sprintf( 'Unknown subcommand "%s %s".', $command, $subcommand ) );
                return 1;
            }

            return $subcommands[ $subcommand ]( $command_input );
        }

        return $command_instance->run( $command_input );
    }

    /**
     * Wrap a message in ANSI color codes if the terminal supports them.
     *
     * A small local helper rather than a call into ConsoleOutput — its
     * colorize() is private by design (an internal detail of how it
     * renders its own styled lines), so the shell's prompt/banner
     * coloring goes through the same Terminal check
     * independently instead of reaching into ConsoleOutput's internals.
     *
     * @param string $code    ANSI escape code constant (see ConsoleOutput).
     * @param string $message
     * @return string
     */
    protected function colorize( string $code, string $message ): string {
        if ( ! isset( $this->terminal ) || ! $this->terminal->supports_ansi() ) {
            return $message;
        }

        return $code . $message . ConsoleOutput::ANSI_RESET;
    }

    /**
     * Write additional context-specific help (e.g. shell built-ins) to
     * the output stream, on top of print_global_help().
     *
     * @return void
     */
    abstract protected function print_contextual_help(): void;
}