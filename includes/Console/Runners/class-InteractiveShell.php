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
 *   smliser > cache stats
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

use SmartLicenseServer\Console\ShellHistoryTrait;
use SmartLicenseServer\Console\CLIWelcomeTrait;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\Commands\SmliserCommand;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Interactive REPL shell for the SmartLicenseServer CLI.
 *
 * Extends SmliserCommand so it inherits print_global_help(),
 * print_command_help(), print_info(), and print_error() without
 * duplication. Implements RunnerInterface so it is a proper runner
 * and can replace CLIRunner at the entry point transparently.
 */
class InteractiveShell extends SmliserCommand implements RunnerInterface {

    use CLIWelcomeTrait;
    use ShellHistoryTrait;

    /*
    |--------------------------------------------
    | CONSTANTS
    |--------------------------------------------
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
     * @param CommandRegistry $registry The command registry singleton.
     */
    public function __construct( CommandRegistry $registry ) {
        $this->registry = $registry;
        // $argv is not used by the shell — initialise to an empty vector
        // so the inherited SmliserCommand property is always defined.
        $this->argv = [];
    }

    /*
    |--------------------------------------------
    | RunnerInterface
    |--------------------------------------------
    */

    /**
     * Start the interactive REPL loop.
     *
     * Prints the welcome banner, then repeatedly reads a line of input,
     * parses it into tokens, and dispatches the first token as a command
     * name. Continues until the user types an exit token or sends EOF
     * (Ctrl-D on POSIX / Ctrl-Z on Windows).
     *
     * @return void
     */
    public function register(): void {
        $this->print_banner();

        while ( true ) {
            $raw = $this->read_line();

            // EOF — Ctrl-D / Ctrl-Z.
            if ( $raw === null ) {
                echo PHP_EOL;
                $this->print_goodbye();
                break;
            }

            $raw = trim( $raw, " \t\n\r\0\x0B\v;" );

            if ( $raw === '' ) {
                continue;
            }

            if ( in_array( $raw, self::EXIT_TOKENS, true ) ) {
                $this->print_goodbye();
                break;
            }

            $this->dispatch( $raw );
        }
    }

    /*
    |--------------------------------------------
    | INPUT
    |--------------------------------------------
    */

    /**
     * Read one line of input from STDIN, with history support.
     *
     * Delegates entirely to CLIHistoryTrait::history_read_line() which
     * selects the best available strategy for the current platform:
     *   - readline extension  → native history + editing (all platforms)
     *   - pure-PHP raw input  → in-memory history with ↑/↓ navigation
     *   - plain fgets()       → non-TTY / piped input fallback
     *
     * @return string|null The trimmed input line, or null on EOF.
     */
    private function read_line(): ?string {
        $prompt = sprintf( '[smart-license-server-%s] > ', SMLISER_VER );

        return $this->history_read_line(
            $this->colorize( static::ANSI_GREEN, $prompt )
        );
    }

    /*
    |--------------------------------------------
    | DISPATCH
    |--------------------------------------------
    */

    /**
     * Parse and execute one line of input.
     *
     * The first token is treated as the command name; the remaining
     * tokens are passed as $args to the command's execute() method,
     * matching exactly what CLIRunner does for one-shot invocations.
     *
     * Built-in shell commands (help, version, clear) are handled before
     * the registry lookup so they are always available even if a custom
     * command happens to shadow one of their names.
     *
     * @param string $line A non-empty, trimmed input line.
     * @return void
     */
    private function dispatch( string $line ): void {
        $tokens  = $this->tokenize( $line );
        $command = array_shift( $tokens ); // first token = command name
        $args    = $tokens;

        // ── Built-in: version ────────────────────────────────────────
        if ( in_array( $command, [ 'version', '-v', '--version' ], true ) ) {
            $this->print_info();
            return;
        }

        // ── Built-in: help ───────────────────────────────────────────
        if ( in_array( $command, [ 'help', '-h', '--help' ], true ) ) {
            $target = $args[0] ?? null;

            if ( $target !== null && $this->registry->has( $target ) ) {
                $this->print_command_help( $this->registry->get( $target ) );
            } else {
                $this->print_shell_help();
            }

            return;
        }

        // ── Built-in: clear ──────────────────────────────────────────
        if ( in_array( $command, [ 'clear', 'cls' ], true ) ) {
            $this->clear_screen();
            return;
        }

        // ── Registry lookup ──────────────────────────────────────────
        $class = $this->registry->get( $command );

        if ( $class === null ) {
            $this->print_error( sprintf( 'Unknown command "%s". Type "help" for a list.', $command ) );
            return;
        }

        // Per-command help flags anywhere in the args list.
        if ( $this->args_request_help( $args ) ) {
            $this->print_command_help( $class );
            return;
        }

        // Execute — catch every Throwable so one bad command cannot
        // kill the session.
        try {
            ( new $class() )->execute( $args );
        } catch ( \Throwable $e ) {
            $this->print_error( $e->getMessage() );
        }
    }

    /*
    |--------------------------------------------
    | TOKENIZER
    |--------------------------------------------
    */

    /**
     * Split a raw input line into an array of argument tokens.
     *
     * Respects single and double quoted strings so a value like
     * "my plugin" is preserved as one token. Escape sequences are
     * not processed — this matches the behaviour of most CLI tools
     * and is sufficient for the argument patterns used by existing
     * commands.
     *
     * Examples:
     *   'app list --type=plugin'           → ['app', 'list', '--type=plugin']
     *   'app search "my plugin" --limit=5' → ['app', 'search', 'my plugin', '--limit=5']
     *   "cache get 'some key'"             → ['cache', 'get', 'some key']
     *
     * @param  string   $line
     * @return string[]
     */
    private function tokenize( string $line ): array {
        $tokens   = [];
        $current  = '';
        $in_quote = null; // null | '"' | "'"
        $length   = strlen( $line );

        for ( $i = 0; $i < $length; $i++ ) {
            $char = $line[ $i ];

            if ( $in_quote !== null ) {
                if ( $char === $in_quote ) {
                    $in_quote = null;
                } else {
                    $current .= $char;
                }
                continue;
            }

            if ( $char === '"' || $char === "'" ) {
                $in_quote = $char;
                continue;
            }

            if ( $char === ' ' || $char === "\t" ) {
                if ( $current !== '' ) {
                    $tokens[]  = $current;
                    $current   = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ( $current !== '' ) {
            $tokens[] = $current;
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

        $this->line( static::ASCII_LOGO );
        $this->line( $this->colorize( static::ANSI_BOLD, '  Smart License Server  v' . SMLISER_VER ) );
        $this->info( sprintf( '  Type "help" to list commands. Type "%s" to quit.', $quit_tokens ) );
        $this->newline();
    }

    /**
     * Print the goodbye line shown when the session ends.
     *
     * @return void
     */
    private function print_goodbye(): void {
        $this->line( 'Goodbye.' );
    }

    /**
     * Print the global help listing augmented with shell-specific
     * built-in commands (clear, exit) that are not in the registry.
     *
     * @return void
     */
    private function print_shell_help(): void {
        $this->print_global_help();

        $clear_tokens = '"clear", "cls"';
        $exit_tokens  = implode( ', ', array_map( fn( $t ) => "\"$t\"", self::EXIT_TOKENS ) );

        echo 'Shell built-ins:' . PHP_EOL;
        echo sprintf( '  %-30s  Clear the terminal screen.', $clear_tokens ) . PHP_EOL;
        echo sprintf( '  %-30s  End the interactive session.', $exit_tokens ) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Clear the visible terminal screen.
     *
     * @return void
     */
    private function clear_screen(): void {
        if ( DIRECTORY_SEPARATOR === '\\' ) {
            @system( 'cls' );
        } else {
            echo "\033[2J\033[H";
        }
    }
}