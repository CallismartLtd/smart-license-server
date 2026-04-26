<?php
/**
 * CLI aware trait file.
 *
 * Provides a rich console output API for any class that needs to
 * interact with a terminal — commands, runners, maintenance scripts.
 *
 * ## Usage
 *
 *   class MyCommand implements CommandInterface {
 *       use CLIAwareTrait;
 *
 *       public function execute( array $args = [] ): void {
 *           $this->start_timer();
 *           $this->info( 'Starting...' );
 *           $this->progress_start( 100, 'Processing' );
 *
 *           for ( $i = 0; $i < 100; $i++ ) {
 *               // do work
 *               $this->progress_advance();
 *           }
 *
 *           $this->progress_finish();
 *           $this->done( 'All items processed.' );
 *       }
 *   }
 *
 * ## Verbosity
 *
 *   $this->set_verbosity( CLIAwareTrait::VERBOSITY_QUIET );
 *   $this->set_verbosity( CLIAwareTrait::VERBOSITY_NORMAL );   // default
 *   $this->set_verbosity( CLIAwareTrait::VERBOSITY_VERBOSE );
 *
 * ## ANSI colors
 *
 *   Auto-detected from the terminal. Falls back to plain text when
 *   output is piped or redirected, or when the terminal does not
 *   support ANSI escape codes (legacy Windows console).
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Rich console output, interaction, progress, and timing for CLI commands.
 */
trait CLIAwareTrait {

    /*
    |--------------------------------------------
    | VERBOSITY CONSTANTS
    |--------------------------------------------
    */

    /**
     * Suppress all output except errors and done().
     */
    const VERBOSITY_QUIET = 0;

    /**
     * Standard output — the default level.
     */
    const VERBOSITY_NORMAL = 1;

    /**
     * Extended output — additional diagnostic messages.
     */
    const VERBOSITY_VERBOSE = 2;

    /*
    |--------------------------------------------
    | ANSI COLOR CODES
    |--------------------------------------------
    */

    const ANSI_RESET   = "\033[0m";
    const ANSI_BOLD    = "\033[1m";
    const ANSI_CYAN    = "\033[36m";
    const ANSI_GREEN   = "\033[32m";
    const ANSI_YELLOW  = "\033[33m";
    const ANSI_RED     = "\033[31m";
    const ANSI_WHITE   = "\033[37m";
    const ANSI_DIM     = "\033[2m";

    /*
    |--------------------------------------------
    | STATE
    |--------------------------------------------
    */

    /**
     * Current verbosity level.
     *
     * @var int
     */
    private int $cli_verbosity = self::VERBOSITY_NORMAL;

    /**
     * Whether ANSI color codes are supported by the current terminal.
     * Null means not yet detected.
     *
     * @var bool|null
     */
    private ?bool $cli_ansi = null;

    /**
     * Timer start time set by start_timer().
     *
     * @var float|null
     */
    private ?float $cli_timer_start = null;

    /**
     * Progress bar state.
     *
     * @var array{total: int, current: int, label: string, width: int}|null
     */
    private ?array $cli_progress = null;

    /*
    |--------------------------------------------
    | VERBOSITY
    |--------------------------------------------
    */

    /**
     * Set the current verbosity level.
     *
     * @param int $level One of VERBOSITY_QUIET, VERBOSITY_NORMAL, VERBOSITY_VERBOSE.
     * @return void
     */
    public function set_verbosity( int $level ): void {
        $this->cli_verbosity = $level;
    }

    /**
     * Get the current verbosity level.
     *
     * @return int
     */
    public function get_verbosity(): int {
        return $this->cli_verbosity;
    }

    /*
    |--------------------------------------------
    | OUTPUT — STYLED LINES
    |--------------------------------------------
    */

    /**
     * Print a plain unstyled line.
     *
     * @param string $message
     * @param int    $verbosity Minimum verbosity required to print.
     * @return void
     */
    public function line( string $message, int $verbosity = self::VERBOSITY_NORMAL ): void {
        $this->write_line( $message, $verbosity );
    }

    /**
     * Print an informational line in cyan.
     *
     * @param string $message
     * @param int    $verbosity
     * @return void
     */
    public function info( string $message, int $verbosity = self::VERBOSITY_NORMAL ): void {
        $this->write_line(
            $this->colorize( self::ANSI_CYAN, $message ),
            $verbosity
        );
    }

    /**
     * Print a success line in green.
     *
     * @param string $message
     * @param int    $verbosity
     * @return void
     */
    public function success( string $message, int $verbosity = self::VERBOSITY_NORMAL ): void {
        $this->write_line(
            $this->colorize( self::ANSI_GREEN, '✔ ' . $message ),
            $verbosity
        );
    }

    /**
     * Print a warning line in yellow.
     *
     * @param string $message
     * @param int    $verbosity
     * @return void
     */
    public function warning( string $message, int $verbosity = self::VERBOSITY_NORMAL ): void {
        $this->write_line(
            $this->colorize( self::ANSI_YELLOW, '⚠ ' . $message ),
            $verbosity
        );
    }

    /**
     * Print an error line in red.
     *
     * Errors always print regardless of verbosity level.
     *
     * @param string $message
     * @return void
     */
    public function error( string $message ): void {
        $this->write_line(
            $this->colorize( self::ANSI_RED, '✖ ' . $message ),
            self::VERBOSITY_QUIET // always print
        );
    }

    /**
     * Print one or more blank lines.
     *
     * @param int $count     Number of blank lines.
     * @param int $verbosity
     * @return void
     */
    public function newline( int $count = 1, int $verbosity = self::VERBOSITY_NORMAL ): void {
        if ( $this->cli_verbosity < $verbosity ) {
            return;
        }

        echo str_repeat( PHP_EOL, max( 1, $count ) );
    }

    /**
     * Print an auto-aligned table.
     *
     * @param string[]   $headers Column headers.
     * @param array[]    $rows    Rows — each row is an indexed array of cell values.
     * @param int        $verbosity
     * @return void
     */
    public function table( array $headers, array $rows, int $verbosity = self::VERBOSITY_NORMAL ): void {
        if ( $this->cli_verbosity < $verbosity ) {
            return;
        }

        // Calculate column widths.
        $widths = array_map( 'strlen', $headers );

        foreach ( $rows as $row ) {
            foreach ( array_values( $row ) as $i => $cell ) {
                $widths[ $i ] = max( $widths[ $i ] ?? 0, strlen( (string) $cell ) );
            }
        }

        $separator = '+' . implode( '+', array_map( fn( $w ) => str_repeat( '-', $w + 2 ), $widths ) ) . '+';

        // Header.
        echo $separator . PHP_EOL;
        echo '|';
        foreach ( $headers as $i => $header ) {
            echo ' ' . $this->colorize( self::ANSI_BOLD, str_pad( $header, $widths[ $i ] ) ) . ' |';
        }
        echo PHP_EOL;
        echo $separator . PHP_EOL;

        // Rows.
        foreach ( $rows as $row ) {
            echo '|';
            foreach ( array_values( $row ) as $i => $cell ) {
                echo ' ' . str_pad( (string) $cell, $widths[ $i ] ) . ' |';
            }
            echo PHP_EOL;
        }

        echo $separator . PHP_EOL;
    }

    /*
    |--------------------------------------------
    | PROGRESS BAR
    |--------------------------------------------
    */

    /**
     * Start a progress bar.
     *
     * @param int    $total The total number of steps.
     * @param string $label Optional label shown before the bar.
     * @param int    $width Bar width in characters. Default 40.
     * @return void
     */
    public function progress_start( int $total, string $label = '', int $width = 60 ): void {
        $this->cli_progress = [
            'total'   => max( 1, $total ),
            'current' => 0,
            'label'   => $label,
            'width'   => $width,
        ];

        $this->draw_progress();
    }

    /**
     * Advance the progress bar by one or more steps.
     *
     * @param int $step Number of steps to advance. Default 1.
     * @return void
     */
    public function progress_advance( int $step = 1 ): void {
        if ( $this->cli_progress === null ) {
            return;
        }

        $this->cli_progress['current'] = min(
            $this->cli_progress['current'] + $step,
            $this->cli_progress['total']
        );

        $this->draw_progress();
    }

    /**
     * Update the progress bar's label.
     * 
     * @param string $label New label to show before the bar.
     * @return void
     */
    public function progress_update_label( string $label ): void {
        if ( $this->cli_progress === null ) {
            return;
        }

        $this->cli_progress['label'] = $label;
        $this->draw_progress();
    }

    /**
     * Complete the progress bar and move to the next line.
     *
     * @param string $final_label Optional label to show on completion.
     * @return void
     */
    public function progress_finish( string $final_label = '' ): void {
        if ( $this->cli_progress === null ) {
            return;
        }

        $this->cli_progress['current'] = $this->cli_progress['total'];
        if ( $final_label !== '' ) {
            $this->cli_progress['label'] = $final_label;
        }

        $this->draw_progress();

        echo PHP_EOL;

        $this->cli_progress = null;
    }

    /**
     * Draw the current progress bar to STDOUT.
     *
     * Uses a carriage return to overwrite the previous bar in place.
     *
     * @return void
     */
    private function draw_progress(): void {
        if ( $this->cli_progress === null || $this->cli_verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        $total      = $this->cli_progress['total'];
        $current    = $this->cli_progress['current'];
        $width      = $this->cli_progress['width'];
        $label      = $this->cli_progress['label'];
        $label      = str_pad( substr( $label, 0, 40 ), 40 );

        $percent  = (int) floor( ( $current / $total ) * 100 );
        $filled   = (int) ( ( $current / $total ) * $width );
        $empty    = $width - $filled;

        $bar = $this->colorize( self::ANSI_GREEN, str_repeat( '█', $filled ) )
             . $this->colorize( self::ANSI_DIM,   str_repeat( '░', $empty ) );

        $prefix = $label !== '' ? $label . ' ' : '';

        printf(
            "\r%s[%s] %3d%% (%d/%d)  ",
            $prefix,
            $bar,
            $percent,
            $current,
            $total
        );

        flush();
    }

    /*
    |--------------------------------------------
    | INTERACTION
    |--------------------------------------------
    */

    /**
     * Prompt the user for freeform input.
     *
     * @param string $question The question to display.
     * @param string $default  Default value if the user presses enter.
     * @return string The user's input or the default.
     */
    public function prompt( string $question, string $default = '' ): string {
        $prompt = $default !== ''
            ? sprintf( '%s [%s] ', $question, $default )
            : $question . ' ';

        echo $this->colorize( self::ANSI_CYAN, $prompt );

        $input = trim( (string) fgets( STDIN ) );

        return $input !== '' ? $input : $default;
    }

    /**
     * Alias for prompt() — prompts the user for freeform input.
     *
     * @param string $question The question to display.
     * @param string $default  Default value if the user presses enter.
     * @return string The user's input or the default.
     */
    public function ask( string $question, string $default = '' ): string {
        return $this->prompt( $question, $default );
    }

    /**
     * Prompt the user for a yes/no confirmation.
     *
     * @param string $question The question to display.
     * @param bool   $default  Default answer if the user presses enter.
     * @return bool True for yes, false for no.
     */
    public function confirm( string $question, bool $default = true ): bool {
        $hint   = $default ? '[Y/n]' : '[y/N]';
        $prompt = sprintf( '%s %s: ', $question, $hint );

        echo $this->colorize( self::ANSI_CYAN, $prompt );

        $input = strtolower( trim( (string) fgets( STDIN ) ) );

        if ( $input === '' ) {
            return $default;
        }

        return in_array( $input, [ 'y', 'yes' ], true );
    }

    /**
     * Prompt the user for hidden input (passwords, secrets).
     *
     * Input is not echoed to the terminal where possible.
     *
     * Platform strategy:
     *  - Linux / macOS: prefers readline hidden input, falls back to stty -echo.
     *  - Windows:       uses `powershell Read-Host -AsSecureString` piped through
     *                   a small ps1 snippet; falls back to plain fgets() when
     *                   PowerShell is unavailable.
     *  - All platforms: plain fgets() fallback when nothing else is available.
     *
     * @param string $question The question to display.
     * @return string The user's input.
     */
    public function secret( string $question ): string {
        echo $this->colorize( self::ANSI_CYAN, $question . ': ' );

        if ( ! $this->is_tty() ) {
            return trim( (string) fgets( STDIN ) );
        }

        if ( $this->is_windows() ) {
            return $this->windows_secret();
        }

        // Linux / macOS — prefer readline, fall back to stty.
        if ( $this->readline_available() ) {
            $input = $this->readline_hidden();
            echo PHP_EOL;
            return trim( $input );
        }

        if ( $this->stty_available() ) {
            $this->stty_echo( false );
            $input = fgets( STDIN );
            $this->stty_echo( true );
            echo PHP_EOL;
            return trim( (string) $input );
        }

        // Plain fallback (input visible).
        return trim( (string) fgets( STDIN ) );
    }

    /**
     * Read hidden input on Windows via PowerShell's SecureString prompt.
     *
     * The ps1 one-liner reads a SecureString and converts it back to plain
     * text so PHP receives it on stdout. Falls back to plain fgets() when
     * PowerShell is not available.
     *
     * @return string
     */
    private function windows_secret(): string {
        if ( ! $this->function_available( 'proc_open' ) ) {
            return trim( (string) fgets( STDIN ) );
        }

        // PowerShell one-liner: read a masked line, echo the plain text.
        $ps1 = '$s=Read-Host -AsSecureString;'
             . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
             . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))';

        $cmd         = 'powershell -NoProfile -NonInteractive -Command "' . $ps1 . '"';
        $descriptors = [
            0 => [ 'file', 'php://stdin',  'r' ],
            1 => [ 'pipe', 'w' ],  // stdout — we read from this.
            2 => [ 'file', 'php://stderr', 'w' ],
        ];

        $process = @proc_open( $cmd, $descriptors, $pipes );

        if ( ! is_resource( $process ) ) {
            // PowerShell unavailable — plain fallback.
            return trim( (string) fgets( STDIN ) );
        }

        $input = stream_get_contents( $pipes[1] );
        fclose( $pipes[1] );
        proc_close( $process );

        echo PHP_EOL;
        return trim( (string) $input );
    }

    /**
     * Read a line of input without echoing to the terminal using the readline extension.
     *
     * @return string
     */
    private function readline_hidden(): string {
        $input = '';

        readline_callback_handler_install( '', function( $line ) use ( &$input ) {
            $input = $line;
        });

        while ( true ) {
            $r = [ STDIN ];
            $w = null;
            $e = null;

            if ( stream_select( $r, $w, $e, null ) ) {
                readline_callback_read_char();
                break;
            }
        }

        readline_callback_handler_remove();

        return $input;
    }

    /**
     * Enable or disable terminal echo using the stty command.
     *
     * No-op on Windows — stty is not available there.
     *
     * @param bool $enable True to restore echo; false to suppress it.
     * @return void
     */
    private function stty_echo( bool $enable ): void {
        if ( $this->is_windows() || ! $this->function_available( 'system' ) ) {
            return;
        }

        if ( $enable ) {
            @system( 'stty echo' );
        } else {
            @system( 'stty -echo' );
        }
    }

    /*
    |--------------------------------------------
    | TIMING
    |--------------------------------------------
    */

    /**
     * Mark the start of a timed operation.
     *
     * @return void
     */
    public function start_timer(): void {
        $this->cli_timer_start = microtime( true );
    }

    /**
     * Return the elapsed time in seconds since start_timer() was called.
     *
     * Returns 0.0 if start_timer() has not been called.
     *
     * @return float Elapsed seconds.
     */
    public function elapsed(): float {
        if ( $this->cli_timer_start === null ) {
            return 0.0;
        }

        return round( microtime( true ) - $this->cli_timer_start, 3 );
    }

    /*
    |--------------------------------------------
    | COMPLETION
    |--------------------------------------------
    */

    /**
     * Print a completion message and optionally show elapsed time.
     *
     * Always prints regardless of verbosity level — completion feedback
     * is always relevant.
     *
     * @param string $message   Optional message. Defaults to 'Done.'.
     * @param bool   $show_time Whether to append elapsed time. Default true
     *                          when start_timer() has been called.
     * @return void
     */
    public function done( string $message = 'Done.', ?bool $show_time = null ): void {
        // Auto-show time if start_timer() was called and $show_time is not explicitly false.
        $show_time = $show_time ?? ( $this->cli_timer_start !== null );

        if ( $show_time && $this->cli_timer_start !== null ) {
            $elapsed    = $this->elapsed();
            $message    = rtrim( $message, '.' ) . sprintf( '. Completed in %ss.', $elapsed );
        }

        $this->write_line(
            $this->colorize( self::ANSI_GREEN, '✔ ' . $message ),
            self::VERBOSITY_QUIET // always print
        );
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Write a line to STDOUT if the current verbosity allows it.
     *
     * @param string $message
     * @param int    $verbosity
     * @return void
     */
    private function write_line( string $message, int $verbosity ): void {
        if ( $this->cli_verbosity < $verbosity ) {
            return;
        }

        echo $message . PHP_EOL;
    }

    /**
     * Wrap a message in ANSI color codes if the terminal supports them.
     *
     * Returns the plain message when ANSI is not supported so output
     * is always readable regardless of environment.
     *
     * @param string $code    ANSI escape code constant.
     * @param string $message The message to colorize.
     * @return string
     */
    private function colorize( string $code, string $message ): string {
        if ( ! $this->supports_ansi() ) {
            return $message;
        }

        return $code . $message . self::ANSI_RESET;
    }

    /**
     * Whether the current terminal supports ANSI escape codes.
     *
     * Detected once and cached. Detection strategy per platform:
     *
     *  - All platforms: stream_isatty( STDOUT ) must be true (i.e. not piped).
     *  - Windows:       also requires either the ANSICON env var, ConEmu/CMDER,
     *                   Windows Terminal (WT_SESSION), or a PHP build that
     *                   natively enables VT processing (PHP >= 8.x on Win10+).
     *  - Linux / macOS: TERM env var must be set and non-empty.
     *
     * @return bool
     */
    private function supports_ansi(): bool {
        if ( $this->cli_ansi !== null ) {
            return $this->cli_ansi;
        }

        // Not a real TTY — piped / redirected output never gets colors.
        if ( function_exists( 'stream_isatty' ) && ! stream_isatty( STDOUT ) ) {
            return $this->cli_ansi = false;
        }

        if ( $this->is_windows() ) {
            // Windows Terminal sets WT_SESSION; ConEmu sets ConEmuANSI.
            // ANSICON is a popular wrapper that adds ANSI support.
            $this->cli_ansi = (
                getenv( 'ANSICON' ) !== false
                || getenv( 'ConEmuANSI' ) === 'ON'
                || getenv( 'WT_SESSION' ) !== false
                || getenv( 'TERM_PROGRAM' ) === 'vscode'
            );
        } else {
            // Linux / macOS — require a non-empty TERM.
            $term           = (string) getenv( 'TERM' );
            $this->cli_ansi = $term !== '' && $term !== 'dumb';
        }

        return $this->cli_ansi;
    }

    /**
     * Whether the `stty` command is available on this system.
     *
     * Always returns false on Windows — stty is a POSIX utility.
     *
     * @return bool
     */
    private function stty_available(): bool {
        if ( $this->is_windows() || ! $this->function_available( 'exec' ) ) {
            return false;
        }

        $output = [];
        $exit   = 1;

        @exec( 'stty -a 2>&1', $output, $exit );

        return $exit === 0;
    }

    /**
     * Whether STDIN is connected to an interactive terminal (TTY).
     *
     * Tries stream_isatty() first (all platforms, PHP 7.2+), then
     * posix_isatty() (Linux / macOS only), then returns false.
     *
     * @return bool
     */
    private function is_tty(): bool {
        if ( function_exists( 'stream_isatty' ) ) {
            return (bool) @stream_isatty( STDIN );
        }

        if ( ! $this->is_windows() && function_exists( 'posix_isatty' ) ) {
            return (bool) @posix_isatty( STDIN );
        }

        return false;
    }

    /**
     * Whether the `readline` extension is available on this system.
     *
     * @return bool
     */
    private function readline_available(): bool {
        return function_exists( 'readline_callback_handler_install' );
    }

    /**
     * Whether the named function exists and has not been disabled via php.ini.
     *
     * @param string $function
     * @return bool
     */
    private function function_available( string $function ): bool {
        if ( ! function_exists( $function ) ) {
            return false;
        }

        $disabled = ini_get( 'disable_functions' );
        if ( ! empty( $disabled ) ) {
            $disabled = array_map( 'trim', explode( ',', $disabled ) );
            if ( in_array( $function, $disabled, true ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the current runtime is Windows.
     *
     * Uses PHP_OS_FAMILY (PHP 7.2+) with a DIRECTORY_SEPARATOR fallback.
     *
     * @return bool
     */
    private function is_windows(): bool {
        return ( defined( 'PHP_OS_FAMILY' ) ? PHP_OS_FAMILY : PHP_OS ) === 'Windows'
            || DIRECTORY_SEPARATOR === '\\';
    }

}