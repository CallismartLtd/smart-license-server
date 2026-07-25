<?php
/**
 * Terminal capabilities detection class file.
 *
 * Centralizes environment/terminal capability detection so both the
 * input side (ConsoleInput, HistoryAwareInput) and the output side
 * (ConsoleOutput) can share one detection strategy instead of each
 * re-implementing TTY, ANSI, and stty checks independently.
 *
 * ## Usage
 *
 *   $terminal = new TerminalCapabilities();
 *
 *   if ( $terminal->supports_ansi() ) {
 *       echo "\033[32mGreen text\033[0m";
 *   }
 *
 *   if ( $terminal->is_tty( STDIN ) && $terminal->stty_available() ) {
 *       // safe to enter stty raw mode
 *   }
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

/**
 * Detects terminal/environment capabilities: TTY status, ANSI/color
 * support (including NO_COLOR / FORCE_COLOR overrides and color depth),
 * platform, and availability of external tools (stty, readline).
 *
 * Detection results that are expensive or invariant for the life of the
 * process (ANSI support) are cached after first use. Detection results
 * that depend on the stream being checked (TTY status) are not cached,
 * since the same instance may be asked about STDIN and STDOUT.
 */
class TerminalCapabilities {

    /*
    |--------------------------------------------
    | STATE
    |--------------------------------------------
    */

    /**
     * Cached ANSI support result. Null means not yet detected.
     *
     * @var bool|null
     */
    private ?bool $ansi = null;

    /*
    |--------------------------------------------
    | TTY DETECTION
    |--------------------------------------------
    */

    /**
     * Whether the given stream is connected to an interactive terminal (TTY).
     *
     * Tries stream_isatty() first (all platforms, PHP 7.2+), then
     * posix_isatty() (Linux / macOS only, requires ext-posix), then
     * falls back to false when neither is available.
     *
     * @param resource $stream The stream to check. Defaults to STDIN.
     * @return bool
     */
    public function is_tty( $stream = STDIN ): bool {
        if ( function_exists( 'stream_isatty' ) ) {
            return (bool) @stream_isatty( $stream );
        }

        if ( ! $this->is_windows() && function_exists( 'posix_isatty' ) ) {
            return (bool) @posix_isatty( $stream );
        }

        return false;
    }

    /*
    |--------------------------------------------
    | ANSI SUPPORT
    |--------------------------------------------
    */

    /**
     * Whether the current terminal supports ANSI escape codes.
     *
     * Detected once per instance and cached. Resolution order:
     *
     *  1. NO_COLOR set (any value)       → false, unconditionally.
     *     Honors https://no-color.org/ — an explicit opt-out always wins.
     *  2. FORCE_COLOR / CLICOLOR_FORCE   → true, unconditionally.
     *     Lets users force color through pipes/redirects (e.g. `| less -R`)
     *     where the TTY check below would otherwise disable it.
     *  3. STDOUT is not a real TTY       → false.
     *     Plain piped/redirected output with no override gets no colors.
     *  4. Platform-specific check:
     *       - Windows:       one of ANSICON, ConEmu, Windows Terminal
     *                        (WT_SESSION), or VS Code's integrated
     *                        terminal (TERM_PROGRAM=vscode).
     *       - Linux / macOS: a non-empty, non-"dumb" TERM variable.
     *
     * @return bool
     */
    public function supports_ansi(): bool {
        if ( null !== $this->ansi ) {
            return $this->ansi;
        }

        // Explicit opt-out always wins, regardless of TTY or platform.
        if ( false !== getenv( 'NO_COLOR' ) ) {
            return $this->ansi = false;
        }

        // Explicit opt-in — lets colors survive a pipe/redirect on purpose.
        if ( $this->truthy_env( 'FORCE_COLOR' ) || $this->truthy_env( 'CLICOLOR_FORCE' ) ) {
            return $this->ansi = true;
        }

        // Not a real TTY and no override — piped / redirected output
        // gets no colors.
        if ( function_exists( 'stream_isatty' ) && ! stream_isatty( STDOUT ) ) {
            return $this->ansi = false;
        }

        if ( $this->is_windows() ) {
            // Windows Terminal sets WT_SESSION; ConEmu sets ConEmuANSI.
            // ANSICON is a popular wrapper that adds ANSI support.
            $this->ansi = (
                false !== getenv( 'ANSICON' )
                || 'ON' === getenv( 'ConEmuANSI' )
                || false !== getenv( 'WT_SESSION' )
                || 'vscode' === getenv( 'TERM_PROGRAM' )
            );
        } else {
            // Linux / macOS — require a non-empty, non-"dumb" TERM.
            $term       = (string) getenv( 'TERM' );
            $this->ansi = ( '' !== $term && 'dumb' !== $term );
        }

        return $this->ansi;
    }

    /**
     * Return the terminal's color depth as a level string.
     *
     * This is a separate question from supports_ansi(): a terminal can
     * support basic ANSI codes but not 256-color or truecolor escape
     * sequences. Callers that only use the eight standard ANSI colors
     * (as ConsoleOutput does) don't need this — it exists for future
     * richer output (e.g. gradient progress bars, 256-color palettes)
     * without having to re-derive detection logic at that point.
     *
     * Levels, from richest to none:
     *   'truecolor' — COLORTERM is "truecolor" or "24bit" (most modern
     *                 terminal emulators: iTerm2, Windows Terminal,
     *                 GNOME Terminal, VS Code's integrated terminal).
     *   '256'       — TERM contains "256color" (e.g. xterm-256color).
     *   'basic'     — supports_ansi() is true but neither of the above
     *                 richer modes was detected — assume the 8/16
     *                 standard ANSI colors are safe.
     *   'none'      — supports_ansi() is false.
     *
     * @return string One of 'none', 'basic', '256', 'truecolor'.
     */
    public function color_support(): string {
        if ( ! $this->supports_ansi() ) {
            return 'none';
        }

        $colorterm = strtolower( (string) getenv( 'COLORTERM' ) );

        if ( 'truecolor' === $colorterm || '24bit' === $colorterm ) {
            return 'truecolor';
        }

        $term = strtolower( (string) getenv( 'TERM' ) );

        if ( str_contains( $term, '256color' ) ) {
            return '256';
        }

        return 'basic';
    }

    /**
     * Whether an environment variable is set to a truthy value.
     *
     * Treats unset, empty string, and "0" as false; anything else
     * (including "1", "true", "yes") as true. Matches the convention
     * used by FORCE_COLOR/CLICOLOR_FORCE across the broader CLI
     * tooling ecosystem (npm, chalk, supports-color, etc.).
     *
     * @param string $name Environment variable name.
     * @return bool
     */
    private function truthy_env( string $name ): bool {
        $value = getenv( $name );

        return false !== $value && '' !== $value && '0' !== $value;
    }

    /*
    |--------------------------------------------
    | PLATFORM
    |--------------------------------------------
    */

    /**
     * Whether the current runtime is Windows.
     *
     * Uses PHP_OS_FAMILY (PHP 7.2+) with a DIRECTORY_SEPARATOR fallback
     * for older runtimes.
     *
     * @return bool
     */
    public function is_windows(): bool {
        return ( defined( 'PHP_OS_FAMILY' ) ? PHP_OS_FAMILY : PHP_OS ) === 'Windows'
            || DIRECTORY_SEPARATOR === '\\';
    }

    /*
    |--------------------------------------------
    | EXTERNAL TOOL AVAILABILITY
    |--------------------------------------------
    */

    /**
     * Whether the `stty` command is available on this system.
     *
     * Always returns false on Windows — stty is a POSIX utility not
     * present on that platform.
     *
     * @return bool
     */
    public function stty_available(): bool {
        if ( $this->is_windows() || ! $this->function_available( 'exec' ) ) {
            return false;
        }

        $output = [];
        $exit   = 1;

        @exec( 'stty -a 2>&1', $output, $exit );

        return 0 === $exit;
    }

    /**
     * Whether the `readline` extension is available on this system.
     *
     * @return bool
     */
    public function readline_available(): bool {
        return function_exists( 'readline_callback_handler_install' );
    }

    /**
     * Whether the named function exists and has not been disabled via
     * the `disable_functions` php.ini directive.
     *
     * @param string $function
     * @return bool
     */
    public function function_available( string $function ): bool {
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
}