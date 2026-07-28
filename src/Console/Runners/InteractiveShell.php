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
use SmartLicenseServer\Console\AsciiLogo;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\ConsoleOutput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\HistoryAwareInput;
use SmartLicenseServer\Console\OptionParser;
use SmartLicenseServer\Console\SignalManager;
use SmartLicenseServer\Console\Terminal;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Utils\Format;

/**
 * Interactive REPL shell for the SmartLicenseServer CLI.
 */
class InteractiveShell extends AbstractCommandRouter implements RunnerInterface {

    /*
    |------------
    | CONSTANTS
    |------------
    */

    /**
     * Input tokens that end the session.
     */
    private const EXIT_TOKENS = [ 'exit', 'quit', 'q' ];

    /**
     * Shell session start time (Unix timestamp).
     *
     * @var int
     */
    private int $started_at;

    /*
    |--------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------
    */

    /**
     * @param CommandRegistry      $registry
     * @param InputInterface       $io
     * @param OutputInterface      $output
     * @param Terminal $terminal Needed here only to decide
     *                                       whether to colorize the
     *                                       prompt/banner — everything
     *                                       else goes through $output.
     */
    public function __construct(
        CommandRegistry $registry,
        InputInterface $io,
        OutputInterface $output,
        Terminal $terminal
    ) {
        if ( ! defined( 'SMLISER_INTERACTIVE_SHELL' ) ) {
            define( 'SMLISER_INTERACTIVE_SHELL', true );
        }

        parent::__construct( $registry, $io, $output, $terminal );
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
        $this->started_at = time();

        $this->register_signal_handlers();
        $this->print_banner();

        while ( true ) {
            $raw = $this->io->read_line( $this->prompt_string() );

            // EOF — Ctrl-D / Ctrl-Z / closed stream.
            if ( null === $raw ) {
                $this->output->newline();
                $this->print_goodbye( 'closed' );
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

    /**
     * Catch SIGINT/SIGTERM/SIGHUP so exit() runs — and with it, every
     * register_shutdown_function() callback registered so far,
     * including HistoryAwareInput::flush_history() — instead of the
     * process dying without any PHP-level cleanup at all.
     *
     * Does NOT cover SIGKILL: POSIX guarantees SIGKILL (and SIGSTOP)
     * are delivered without giving the target process any chance to
     * intercept them, specifically so a process can never make itself
     * unkillable. No signal handler, in any language, changes that —
     * it's a kernel guarantee, not a PHP limitation.
     *
     * Explicitly restores cooked terminal mode before calling exit()
     * rather than relying on read_via_best_available_mode()'s
     * finally block to do it — exit() does not unwind pending finally
     * blocks the way a normal return or thrown exception would, it
     * jumps straight to the shutdown sequence. Without this explicit
     * call, Ctrl-C during raw-mode reading would flush history
     * correctly but leave the real terminal stuck in -icanon -echo
     * after the process exits.
     *
     * SIGTSTP (Ctrl-Z, suspend) is deliberately NOT caught here — that
     * would break the normal suspend/resume (fg) workflow operators
     * expect from any shell, catchable or not.
     *
     * No-op on Windows (pcntl doesn't exist there) or if the pcntl
     * extension isn't compiled in/enabled — a common case even on
     * POSIX systems, since pcntl is not part of most default PHP
     * builds.
     *
     * @return void
     */
    private function register_signal_handlers(): void {        
        if ( ! $this->signal->register() ) {
            return;
        }

        if ( $this->io instanceof HistoryAwareInput ) {
            $this->signal->on( SIGWINCH, [$this->io, 'reset_terminal_width'] );
        }

        $this->signal->on( SIGINT, [$this, 'exit_handler'] )
                ->on( SIGTERM, [$this, 'exit_handler'] )
                ->on( SIGHUP, [$this, 'exit_handler'] );
    }

    /**
     * Graceful exit logging and cooked-mode restoration on process termination signals.
     *
     * @param int $signal
     * @return never
     */
    public function exit_handler( int $signal ): never {
        $reason = match ( $signal ) {
            SIGINT  => 'interrupted',
            SIGTERM => 'terminated',
            SIGHUP  => 'disconnected',
            default => 'ended',
        };

        $this->terminal->restore_cooked_mode();
        $this->output->newline();
        $this->print_goodbye( $reason );

        // 128 + signal number is the conventional shell exit-code
        // convention for "terminated by signal N" (e.g. 130 for SIGINT)
        exit( 128 + $signal );
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
        $logo   = match( $this->output->get_verbosity() ) {
            OutputInterface::VERBOSITY_NORMAL   => AsciiLogo::MONOSPACED,
            OutputInterface::VERBOSITY_VERBOSE  => AsciiLogo::LARGE,
            OutputInterface::VERBOSITY_QUIET    => '',
            default                             => ''
            
        };

        $quit_tokens = implode( '", "', self::EXIT_TOKENS );

        $this->output->newline();
        $this->output->writeln( $logo );
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
     * @param string $reason
     * @return void
     */
    private function print_goodbye( string $reason = 'ended' ): void {
        $duration = time() - $this->started_at;

        $this->output->writeln(
            sprintf(
                'Session %s (%s).',
                $reason,
                Format::short_duration( $duration )
            )
        );
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
}