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
 * platform, and availability of external tools (stty, readline) — and
 * owns the actual stty mode-switching (raw mode, echo suppression) so
 * that logic exists in exactly one place instead of being duplicated
 * across ConsoleInput and HistoryAwareInput.
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

    public function supports_ansi(): bool {
        if ( null !== $this->ansi ) {
            return $this->ansi;
        }

        // 1. Explicit opt-out always wins (https://no-color.org/)
        if ( false !== getenv( 'NO_COLOR' ) ) {
            return $this->ansi = false;
        }

        // 2. Explicit opt-in
        if ( $this->truthy_env( 'FORCE_COLOR' ) || $this->truthy_env( 'CLICOLOR_FORCE' ) ) {
            return $this->ansi = true;
        }

        // 3. Not a TTY -> no colors
        if ( ! $this->is_tty( STDOUT ) ) {
            return $this->ansi = false;
        }

        // 4. Windows specific environments
        if ( $this->is_windows() ) {
            return $this->ansi = (
                false !== getenv( 'ANSICON' )
                || 'ON' === getenv( 'ConEmuANSI' )
                || false !== getenv( 'WT_SESSION' )
                || $this->is_known_ansi_term_program()
            );
        }

        // 5. POSIX (Linux/macOS): Whitelist OR Capability Probe
        return $this->ansi = (
            $this->is_known_ansi_term_program()
            || $this->has_ansi_capabilities()
        );
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

    /**
     * Check terminal capabilities without naming specific applications.
     */
    private function has_ansi_capabilities(): bool {
        // 1. Check COLORTERM — standard across modern Linux/macOS emulators
        $colorterm = strtolower( (string) getenv( 'COLORTERM' ) );
        if ( '' !== $colorterm ) {
            return true;
        }

        // 2. Check TERM against standard ANSI patterns
        $term = strtolower( (string) getenv( 'TERM' ) );

        if ( '' === $term || 'dumb' === $term ) {
            return false;
        }

        // Standard terminal patterns known to support ANSI
        return (bool) preg_match( '/(?:color|ansi|xterm|screen|tmux|vt100|rxvt|linux)/i', $term );
    }

    /**
     * Known TERM_PROGRAM identifiers that natively support ANSI.
     */
    private const ANSI_TERM_PROGRAMS = [
        'vscode',
        'hyper',
        'apple_terminal',
        'iterm.app',
        'terminus',
        'ghostty',
        'wezterm',
        'rio',
        'foot',
        'warp',
    ];

    /**
     * Check if TERM_PROGRAM matches a known terminal emulator.
     */
    private function is_known_ansi_term_program(): bool {
        $program = strtolower( (string) getenv( 'TERM_PROGRAM' ) );

        return '' !== $program && in_array( $program, self::ANSI_TERM_PROGRAMS, true );
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
     * @return bool
     */
    public function stty_available(): bool {
        if ( $this->is_windows() ) {
            return false;
        }

        return $this->command_available( 'stty' );
    }

    /**
     * Whether the `clear` or `cls` screen utility is available.
     *
     * @return bool
     */
    public function clear_available(): bool {
        $utility = $this->is_windows() ? 'cls' : 'clear';

        return $this->command_available( $utility );
    }

    /**
     * Whether an external shell command/binary is executable on the system.
     *
     * Uses `where` on Windows and `which` on POSIX systems (Linux/macOS).
     *
     * @param string $command The executable name (e.g., 'stty', 'clear', 'git', 'tput').
     * @return bool
     */
    public function command_available( string $command ): bool {
        if ( ! $this->function_available( 'exec' ) ) {
            return false;
        }

        // Sanitize the input to prevent command injection risks.
        $command = escapeshellarg( $command );

        // Determine OS-specific system lookup utility.
        $lookup  = $this->is_windows() ? 'where' : 'which';
        $null    = $this->is_windows() ? 'NUL' : '/dev/null';

        $output = [];
        $exit   = 1;

        @exec( sprintf( '%s %s 2>%s', $lookup, $command, $null ), $output, $exit );

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

    /*
    |--------------------------------------------
    | RAW MODE / ECHO CONTROL
    |--------------------------------------------
    */

    /**
     * Switch STDIN into raw mode: no line buffering (-icanon), no
     * local echo (-echo), and return each byte as soon as it arrives
     * rather than waiting for a full line (min 1 time 0).
     *
     * This is what lets a caller (HistoryAwareInput's raw-mode line
     * editor) read and react to individual keystrokes — arrows,
     * Backspace, Ctrl-C — instead of only receiving a complete line
     * from the kernel's own line-editing.
     *
     * Centralized here rather than duplicated per caller: this is the
     * single place that knows how to talk to the terminal via stty,
     * matching stty_available()/command_available() already living on
     * this class.
     *
     * @return bool True if raw mode was actually engaged; false if
     *              stty or system() are unavailable, in which case the
     *              caller should not enter a raw-mode read loop at all.
     */
    public function enable_raw_mode(): bool {
        if ( ! $this->stty_available() || ! $this->function_available( 'system' ) ) {
            return false;
        }

        @system( 'stty -icanon -echo min 1 time 0' );

        return true;
    }

    /**
     * Restore STDIN to normal (cooked) line-buffered, echoing mode.
     *
     * Safe to call even if enable_raw_mode() was never successfully
     * engaged — it just checks availability again and no-ops if stty
     * isn't usable.
     *
     * @return void
     */
    public function restore_cooked_mode(): void {
        if ( $this->stty_available() && $this->function_available( 'system' ) ) {
            @system( 'stty icanon echo' );
        }
    }

    /**
     * Suppress terminal echo only, leaving line buffering (canonical
     * mode) intact — used for hidden input (passwords/secrets), which
     * still wants the kernel's own line editing (Backspace, etc.), just
     * without the typed characters appearing on screen.
     *
     * @return bool True if echo was actually suppressed; false if stty
     *              or system() are unavailable.
     */
    public function disable_echo(): bool {
        if ( ! $this->stty_available() || ! $this->function_available( 'system' ) ) {
            return false;
        }

        @system( 'stty -echo' );

        return true;
    }

    /**
     * Restore terminal echo after disable_echo().
     *
     * @return void
     */
    public function enable_echo(): void {
        if ( $this->stty_available() && $this->function_available( 'system' ) ) {
            @system( 'stty echo' );
        }
    }
}