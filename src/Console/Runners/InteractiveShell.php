<?php
/**
 * Interactive shell runner class file.
 *
 * Provides a REPL loop so operators can run multiple commands in a
 * single session without re-invoking the smliser binary each time.
 *
 * ## Usage
 *
 * The smliser entry point activates the shell automatically when no
 * command argument is supplied:
 *
 *   smliser           → interactive shell
 *   smliser <command> → one-shot dispatch (CLIRunner, unchanged)
 *
 * Inside the shell every registered command works exactly as on the
 * command line, minus the leading "smliser" token:
 *
 *   smliser > license list
 *   smliser > app list --type=plugin
 *   smliser > help
 *   smliser > help cache
 *   smliser > exit
 *
 * Quoted arguments are handled so multi-word values stay intact:
 *
 *   smliser > app search "my plugin"
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Runners;

use SmartLicenseServer\Console\AbstractCommandRouter;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\ConsoleOutput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\OptionParser;
use SmartLicenseServer\Console\TerminalCapabilities;
use SmartLicenseServer\Console\Traits\CLIWelcomeTrait;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Interactive REPL shell for the SmartLicenseServer CLI.
 *
 * Extends AbstractCommandRouter for print_global_help(),
 * print_command_help(), print_info(), route_command(), and
 * split_invocation() — none of which touch STDIN/STDOUT directly,
 * since AbstractCommandRouter writes only through the injected
 * OutputInterface.
 *
 * Reads go through the injected InputInterface's read_line() — in
 * practice a HistoryAwareInput wrapping a ConsoleInput, wired up by
 * CLIEnvironment — rather than this class touching STDIN or the old
 * ShellHistoryTrait itself. That keeps the shell's own code ignorant
 * of *how* history/raw-mode reading works, matching how CLIRunner
 * doesn't know or care that ConsoleInput exists underneath its $io.
 */
class InteractiveShell extends AbstractCommandRouter implements RunnerInterface {

    use CLIWelcomeTrait;

    /*
    |------------
    | CONSTANTS
    |------------
    */

    /**
     * Input tokens that end the session.
     */
    private const EXIT_TOKENS = [ 'exit', 'quit', 'q' ];

    /*
    |--------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------
    */

    /**
     * @param CommandRegistry      $registry
     * @param InputInterface       $io
     * @param OutputInterface      $output
     * @param TerminalCapabilities $terminal Needed here only to decide
     *                                       whether to colorize the
     *                                       prompt/banner — everything
     *                                       else goes through $output.
     */
    public function __construct(
        CommandRegistry $registry,
        InputInterface $io,
        OutputInterface $output,
        private TerminalCapabilities $terminal
    ) {
        if ( ! defined( 'SMLISER_INTERACTIVE_SHELL' ) ) {
            define( 'SMLISER_INTERACTIVE_SHELL', true );
        }

        parent::__construct( $registry, $io, $output );
    }

    /*
    |------------------
    | RunnerInterface
    |------------------
    */

    /**
     * {@inheritdoc}
     *
     * Prints the welcome banner, then repeatedly reads a line of input,
     * parses it into tokens, and dispatches it. Continues until the
     * operator types an exit token or sends EOF (Ctrl-D on POSIX /
     * Ctrl-Z on Windows).
     */
    public function init(): int {
        $this->print_banner();

        while ( true ) {
            $raw = $this->io->read_line( $this->prompt_string() );

            // EOF — Ctrl-D / Ctrl-Z / closed stream.
            if ( null === $raw ) {
                $this->output->newline();
                $this->print_goodbye();
                break;
            }

            $raw = trim( $raw, " \t\n\r\0\x0B\v;" );

            if ( '' === $raw ) {
                continue;
            }

            if ( in_array( $raw, static::EXIT_TOKENS, true ) ) {
                $this->print_goodbye();
                break;
            }

            $this->dispatch( $raw );
        }

        return 0;
    }

    /*
    |-------
    | INPUT
    |-------
    */

    /**
     * Build the colored prompt string for the current session.
     *
     * @return string
     */
    private function prompt_string(): string {
        $principal      = Guard::get_principal();
        $prompt_symbol  = $principal?->is( 'system_admin' ) ? '#' : '>';
        $version_string = smliser_debug_enabled() ? '-' . \SMLISER_VER : '';
        $prompt_slug    = str_replace( [ '_', ' ' ], '-', strtolower( \SMLISER_APP_NAME ) );
        $prompt         = sprintf( '[%s%s] %s ', $prompt_slug, $version_string, $prompt_symbol );

        return $this->colorize( ConsoleOutput::ANSI_GREEN, $prompt );
    }

    /*
    |-----------
    | DISPATCH
    |-----------
    */

    /**
     * Parse and execute one line of input.
     *
     * @param string $line A non-empty, trimmed input line.
     * @return void
     */
    private function dispatch( string $line ): void {
        $tokens = $this->tokenize( $line );

        if ( in_array( $tokens[0] ?? null, [ 'clear', 'cls' ], true ) ) {
            $this->clear_screen();
            return;
        }

        [ $command, $subcommand, $args ] = $this->split_invocation( $tokens );

        $option_parser = new OptionParser();
        $parsed        = $option_parser->parse( $args );

        $command_input = new CommandInput(
            (array) ( $parsed['arguments'] ?? [] ),
            (array) ( $parsed['options'] ?? [] )
        );

        // Execute — catch every Throwable so one bad command cannot
        // kill the session. The exit code isn't surfaced anywhere —
        // an interactive session doesn't have a process exit code to
        // report it to — but a failed command has already reported
        // its own error via print_error()/$this->output->error()
        // before returning it.
        try {
            $this->route_command( $command_input, $command, $subcommand );
        } catch ( \Throwable $e ) {
            $this->print_error( sprintf(
                '%s thrown in %s (%s)',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ) );
        } finally {
            $db = smliser_db();

            if ( $db->is_connected() ) {
                $db->close();
            }
        }
    }

    /*
    |-------------
    | TOKENIZER
    |-------------
    */

    /**
     * Split a raw input line into an array of argument tokens.
     *
     * Examples:
     *   'app list --type=plugin'           → ['app', 'list', '--type=plugin']
     *   'app search "my plugin" --limit=5' → ['app', 'search', 'my plugin', '--limit=5']
     *   "cache get 'some key'"             → ['cache', 'get', 'some key']
     *
     * @param string $line
     * @return string[]
     */
    private function tokenize( string $line ): array {
        $tokens   = [];
        $current  = '';
        $in_quote = null;
        $length   = strlen( $line );

        for ( $i = 0; $i < $length; $i++ ) {
            $char = $line[ $i ];

            if ( null !== $in_quote ) {
                if ( $char === $in_quote ) {
                    $in_quote = null;
                } else {
                    $current .= $char;
                }
                continue;
            }

            if ( '"' === $char || "'" === $char ) {
                $in_quote = $char;
                continue;
            }

            if ( ' ' === $char || "\t" === $char ) {
                if ( '' !== $current ) {
                    $tokens[] = $current;
                    $current  = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ( '' !== $current ) {
            $tokens[] = $current;
        }

        if ( null !== $in_quote ) {
            $this->print_error( 'Warning: Unclosed string quote detected.' );
        }

        return $tokens;
    }

    /*
    |--------------------------------------------
    | SHELL BUILT-INS
    |--------------------------------------------
    */

    /**
     * Print the welcome banner shown at the start of each session.
     *
     * @return void
     */
    private function print_banner(): void {
        $quit_tokens = implode( '", "', self::EXIT_TOKENS );

        $this->output->writeln( static::ASCII_LOGO );
        $this->output->writeln(
            $this->colorize( 
                ConsoleOutput::ANSI_BOLD, 
                sprintf( '  %s  v%s', SMLISER_APP_NAME, SMLISER_VER )
            )
        );
        
        $this->output->info( sprintf( '  Type "help" to list commands. Type "%s" to quit.', $quit_tokens ) );
        $this->output->newline();
    }

    /**
     * Print the goodbye line shown when the session ends.
     *
     * @return void
     */
    private function print_goodbye(): void {
        $this->output->writeln( 'Goodbye.' );
    }

    /**
     * {@inheritdoc}
     *
     * Augments the global help listing with shell-specific built-in
     * commands (clear, exit) that are not in the registry.
     */
    protected function print_contextual_help(): void {
        $this->print_global_help();

        $clear_token = $this->terminal->is_windows() ? '"cls"' : '"clear"';
        $exit_tokens = implode( ', ', array_map( fn( $t ) => "\"$t\"", self::EXIT_TOKENS ) );

        $this->output->writeln( 'Shell built-ins:' );
        $this->output->writeln( sprintf( '  %-30s  Clear the terminal screen.', $clear_token ) );
        $this->output->writeln( sprintf( '  %-30s  End the interactive session.', $exit_tokens ) );
    }

    /**
     * Clears the visible terminal screen.
     *
     * @return void
     */
    private function clear_screen(): void {
        // Fast path: ANSI-capable terminals.
        if ( $this->terminal->supports_ansi() ) {
            $this->output->write( "\033[3J\033[2J\033[H" );
            return;
        }

        // Windows fallback.
        if (
            $this->terminal->is_windows()
            && $this->terminal->function_available( 'system' )
        ) {
            system( 'cls' );
            return;
        }

        // POSIX fallback.
        if (
            $this->terminal->function_available( 'system' )
            && $this->terminal->command_available( 'tput' )
        ) {
            system( 'tput clear' );
        }
    }

    /**
     * Wrap a message in ANSI color codes if the terminal supports them.
     *
     * A small local helper rather than a call into ConsoleOutput — its
     * colorize() is private by design (an internal detail of how it
     * renders its own styled lines), so the shell's prompt/banner
     * coloring goes through the same TerminalCapabilities check
     * independently instead of reaching into ConsoleOutput's internals.
     *
     * @param string $code    ANSI escape code constant (see ConsoleOutput).
     * @param string $message
     * @return string
     */
    private function colorize( string $code, string $message ): string {
        if ( ! $this->terminal->supports_ansi() ) {
            return $message;
        }

        return $code . $message . ConsoleOutput::ANSI_RESET;
    }
}