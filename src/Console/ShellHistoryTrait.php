<?php
/**
 * Shell history trait file.
 *
 * Provides cross-platform history-aware line reading for the
 * InteractiveShell. Abstracts over three tiers of terminal capability:
 *
 *  Tier 1 — readline extension available (Linux/macOS typical, rare on Windows)
 *           Full readline: ↑/↓ history, Ctrl-R search, in-line editing.
 *           History is persisted to disk and reloaded across sessions.
 *
 *  Tier 2 — no readline, but stty is available (Linux/macOS without readline)
 *           Raw-mode key handler: ↑/↓ navigates in-memory + persisted history,
 *           printable characters are echoed, Backspace/Delete work, Enter submits.
 *           History is persisted to disk and reloaded across sessions.
 *
 *  Tier 3 — Windows without readline, or no stty (piped / minimal environments)
 *           Plain fgets() fallback. History is kept in memory only for the
 *           current session (no raw-mode key capture possible without extensions).
 *
 * ## History file location
 *
 *   {SMLISER_ABSPATH}.smliser.shell_history   (hidden dot-file, project root)
 *
 *   The file stores one entry per line, newest last, capped at
 *   ShellHistoryTrait::HISTORY_LIMIT entries. It is created on first use
 *   and silently skipped when the filesystem is not writable.
 *
 * ## Usage
 *
 *   class InteractiveShell extends SmliserCommand implements RunnerInterface {
 *       use ShellHistoryTrait;
 *
 *       private function read_line(): ?string {
 *           return $this->history_read_line( $this->prompt_string() );
 *       }
 *   }
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Cross-platform, history-aware line reading for the interactive shell.
 */
trait ShellHistoryTrait {

    /*
    |--------------------------------------------
    | CONSTANTS
    |--------------------------------------------
    */

    /**
     * Maximum number of history entries kept in memory and on disk.
     */
    private const HISTORY_LIMIT = 500;

    /*
    |--------------------------------------------
    | STATE
    |--------------------------------------------
    */

    /**
     * In-memory history entries for the current session.
     * Oldest entry at index 0, newest at the end.
     *
     * @var string[]
     */
    private array $shell_history = [];

    /**
     * Whether history has been loaded from disk this session.
     *
     * @var bool
     */
    private bool $shell_history_loaded = false;

    /*
    |--------------------------------------------
    | PUBLIC API
    |--------------------------------------------
    */

    /**
     * Print a prompt and read one line from STDIN with history support.
     *
     * Selects the best available strategy for the current platform and
     * terminal. Returns null on EOF (Ctrl-D / Ctrl-Z).
     *
     * @param  string      $prompt The prompt string to display.
     * @return string|null Trimmed input line, or null on EOF.
     */
    protected function history_read_line( string $prompt ): ?string {
        $this->history_load();

        // Tier 1 — readline extension.
        // readline() does not interpret ANSI escape codes — it prints them
        // literally. Strip them so the prompt renders cleanly.
        if ( $this->readline_available() ) {
            return $this->read_line_readline( $this->strip_ansi( $prompt ) );
        }

        // Tier 2 — stty raw mode (POSIX without readline).
        // We echo the prompt ourselves, so the colored version is fine.
        if ( ! $this->is_windows() && $this->is_tty() && $this->stty_available() ) {
            return $this->read_line_raw( $prompt );
        }

        // Tier 3 — plain fgets() (Windows fallback / piped input).
        // Same as Tier 2 — we echo the prompt ourselves.
        return $this->read_line_plain( $prompt );
    }

    /*
    |--------------------------------------------
    | TIER 1 — readline
    |--------------------------------------------
    */

    /**
     * Read a line using the readline extension.
     *
     * Loads persisted history into readline's own ring so ↑/↓ works
     * across sessions. Newly submitted entries are appended to both
     * readline's ring and the in-memory array, then flushed to disk.
     *
     * @param  string      $prompt
     * @return string|null
     */
    private function read_line_readline( string $prompt ): ?string {
        // Sync persisted history into readline's ring on first call.
        static $readline_synced = false;

        if ( ! $readline_synced ) {
            foreach ( $this->shell_history as $entry ) {
                readline_add_history( $entry );
            }

            $readline_synced = true;
        }

        $line = readline( $prompt );

        if ( $line === false ) {
            return null; // EOF
        }

        $line = trim( $line );

        if ( $line !== '' ) {
            readline_add_history( $line );
            $this->history_push( $line );
            $this->history_save();
        }

        return $line;
    }

    /*
    |--------------------------------------------
    | TIER 2 — stty raw mode
    |--------------------------------------------
    */

    /**
     * Read a line in stty raw mode, implementing ↑/↓ history navigation,
     * printable character echo, and Backspace/Delete handling manually.
     *
     * Raw mode disables the terminal's own line-editing so we receive
     * each key press immediately as a byte or escape sequence. We handle
     * the input character by character and build the line ourselves.
     *
     * Key support:
     *   Enter           Submit the current line.
     *   Backspace       Delete the last character.
     *   Delete (^D)     EOF when buffer is empty; delete character otherwise.
     *   ↑ (ESC [ A)     Recall older history entry.
     *   ↓ (ESC [ B)     Recall newer history entry (or clear line).
     *   Ctrl-C          Abandon the current line (returns empty string).
     *   Printable chars Echoed to STDOUT and appended to the buffer.
     *
     * @param  string      $prompt
     * @return string|null The submitted line, or null on EOF.
     */
    private function read_line_raw( string $prompt ): ?string {
        echo $prompt;

        $this->stty_raw( true );

        $buffer   = '';    // Current line buffer.
        $hist_idx = null;  // null = editing a fresh line; int = browsing history.
        $saved    = '';    // Preserves the draft line while navigating history.
        $result   = null;
        $eof      = false;

        try {
            while ( true ) {
                $char = fgetc( STDIN );

                if ( $char === false ) {
                    $eof = true;
                    break;
                }

                $byte = ord( $char );

                // ── Enter (\r or \n) ─────────────────────────────────
                if ( $byte === 13 || $byte === 10 ) {
                    echo PHP_EOL;
                    $result = $buffer;
                    break;
                }

                // ── Ctrl-C ───────────────────────────────────────────
                if ( $byte === 3 ) {
                    echo PHP_EOL;
                    $result = '';
                    break;
                }

                // ── Ctrl-D (EOF when buffer empty, else ignore) ──────
                if ( $byte === 4 ) {
                    if ( $buffer === '' ) {
                        echo PHP_EOL;
                        $eof = true;
                        break;
                    }
                    continue;
                }

                // ── Backspace (0x7F or 0x08) ─────────────────────────
                if ( $byte === 127 || $byte === 8 ) {
                    if ( $buffer !== '' ) {
                        $buffer = substr( $buffer, 0, -1 );
                        echo "\x08 \x08"; // erase last printed character.
                    }
                    continue;
                }

                // ── ESC sequence (arrow keys, Delete key) ────────────
                if ( $byte === 27 ) {
                    $seq = $this->read_escape_sequence();

                    // ↑ arrow — older history.
                    if ( $seq === '[A' ) {
                        [ $buffer, $hist_idx, $saved ] = $this->history_older(
                            $buffer, $hist_idx, $saved, $prompt
                        );
                        continue;
                    }

                    // ↓ arrow — newer history / back to draft.
                    if ( $seq === '[B' ) {
                        [ $buffer, $hist_idx ] = $this->history_newer(
                            $hist_idx, $saved, $prompt
                        );
                        continue;
                    }

                    // Other escape sequences — silently ignore.
                    continue;
                }

                // ── Printable character ──────────────────────────────
                if ( $byte >= 32 ) {
                    $buffer .= $char;
                    echo $char;
                }
            }
        } finally {
            $this->stty_raw( false );
        }

        if ( $eof ) {
            return null;
        }

        $line = trim( $result ?? '' );

        if ( $line !== '' ) {
            $this->history_push( $line );
            $this->history_save();
        }

        return $line;
    }

    /**
     * Read the bytes following an ESC byte to complete an escape sequence.
     *
     * Most arrow keys send ESC [ A/B/C/D. We read up to two more bytes
     * with a short non-blocking peek to distinguish a lone ESC keypress
     * from a multi-byte sequence.
     *
     * @return string The sequence characters after the ESC byte (e.g. '[A').
     */
    private function read_escape_sequence(): string {
        // Peek at the next byte — must arrive quickly after ESC.
        $r = [ STDIN ];
        $w = $e = null;

        if ( ! stream_select( $r, $w, $e, 0, 50000 ) ) {
            return ''; // Lone ESC key.
        }

        $next = fgetc( STDIN );

        if ( $next === false || $next !== '[' ) {
            return (string) $next;
        }

        // CSI sequence — read the final byte.
        $r = [ STDIN ];
        $w = $e = null;

        if ( ! stream_select( $r, $w, $e, 0, 50000 ) ) {
            return '[';
        }

        $final = fgetc( STDIN );

        return $final !== false ? '[' . $final : '[';
    }

    /**
     * Navigate to an older history entry and redraw the line.
     *
     * @param  string      $buffer   Current buffer contents.
     * @param  int|null    $hist_idx Current history cursor (null = fresh line).
     * @param  string      $saved    Draft line saved before browsing started.
     * @param  string      $prompt   The full prompt string (may contain ANSI codes).
     * @return array{string, int, string} [$new_buffer, $new_hist_idx, $saved]
     */
    private function history_older( string $buffer, ?int $hist_idx, string $saved, string $prompt ): array {
        $count = count( $this->shell_history );

        if ( $count === 0 ) {
            return [ $buffer, $hist_idx, $saved ];
        }

        if ( $hist_idx === null ) {
            // First ↑ press — save draft and start at the newest entry.
            $saved    = $buffer;
            $hist_idx = $count - 1;
        } elseif ( $hist_idx > 0 ) {
            $hist_idx--;
        }

        $entry = $this->shell_history[ $hist_idx ];
        $this->rewrite_line( $buffer, $entry, $prompt );

        return [ $entry, $hist_idx, $saved ];
    }

    /**
     * Navigate to a newer history entry, or restore the draft line.
     *
     * @param  int|null $hist_idx Current history cursor.
     * @param  string   $saved    The draft line saved before browsing.
     * @param  string   $prompt   The full prompt string (may contain ANSI codes).
     * @return array{string, int|null} [$new_buffer, $new_hist_idx]
     */
    private function history_newer( ?int $hist_idx, string $saved, string $prompt ): array {
        if ( $hist_idx === null ) {
            return [ '', null ]; // Already at the fresh line.
        }

        $count = count( $this->shell_history );

        if ( $hist_idx >= $count - 1 ) {
            // Reached the bottom — restore the draft.
            $this->rewrite_line( $this->shell_history[ $hist_idx ] ?? '', $saved, $prompt );
            return [ $saved, null ];
        }

        $hist_idx++;
        $entry = $this->shell_history[ $hist_idx ];
        $this->rewrite_line( $this->shell_history[ $hist_idx - 1 ], $entry, $prompt );

        return [ $entry, $hist_idx ];
    }

    /**
     * Overwrite the buffer area of the current line with new content.
     *
     * Strategy:
     *   1. \r              — move cursor to column 0.
     *   2. Reprint prompt  — the prompt is always safe to reprint; it
     *                        never changes and restores its own colors.
     *   3. Erase remainder — blank enough characters to cover the old
     *                        buffer (in case the new entry is shorter).
     *   4. \r + prompt     — return to column 0, reprint prompt again
     *                        so the cursor ends up right after it.
     *   5. Print new entry — the replacement buffer content.
     *
     * This avoids cursor-arithmetic entirely — we never try to jump to
     * a computed column, so ANSI codes in the prompt cannot cause
     * off-by-N corruption regardless of their byte length.
     *
     * @param  string $old    Buffer currently displayed after the prompt.
     * @param  string $new    Replacement buffer content.
     * @param  string $prompt The full prompt string (ANSI codes are fine).
     * @return void
     */
    private function rewrite_line( string $old, string $new, string $prompt ): void {
        $erase = strlen( $old ) > strlen( $new )
            ? str_repeat( ' ', strlen( $old ) - strlen( $new ) )
            : '';

        echo "\r"       // column 0
           . $prompt    // reprint prompt (restores its colors too)
           . $new        // new buffer content
           . $erase      // blank any leftover characters from the old buffer
           . "\r"        // back to column 0
           . $prompt     // reprint prompt once more so cursor lands after it
           . $new;       // reprint new content so cursor is at end of input
    }

    /*
    |--------------------------------------------
    | TIER 3 — plain fgets()
    |--------------------------------------------
    */

    /**
     * Read a line using plain fgets() with no key-level handling.
     *
     * History is still maintained in memory within the session and
     * persisted to disk, but navigation (↑/↓) is not available because
     * the terminal delivers a complete line at a time.
     *
     * @param  string      $prompt
     * @return string|null
     */
    private function read_line_plain( string $prompt ): ?string {
        echo $prompt;
        $line = fgets( STDIN );

        if ( $line === false ) {
            return null;
        }

        $line = trim( $line );

        if ( $line !== '' ) {
            $this->history_push( $line );
            $this->history_save();
        }

        return $line;
    }

    /*
    |--------------------------------------------
    | HISTORY MANAGEMENT
    |--------------------------------------------
    */

    /**
     * Append an entry to the in-memory history, enforcing the cap.
     *
     * Duplicate consecutive entries are de-duplicated — typing the same
     * command twice in a row only adds one history entry.
     *
     * @param  string $line
     * @return void
     */
    private function history_push( string $line ): void {
        // Skip consecutive duplicates.
        if ( ! empty( $this->shell_history ) && end( $this->shell_history ) === $line ) {
            return;
        }

        $this->shell_history[] = $line;

        if ( count( $this->shell_history ) > self::HISTORY_LIMIT ) {
            array_shift( $this->shell_history );
        }
    }

    /**
     * Load history from the persisted file into $this->shell_history.
     *
     * Called once per session. Silently does nothing if the file does
     * not exist or is not readable.
     *
     * @return void
     */
    private function history_load(): void {
        if ( $this->shell_history_loaded ) {
            return;
        }

        $this->shell_history_loaded = true;

        $path = $this->history_file_path();

        if ( ! is_readable( $path ) ) {
            return;
        }

        $lines = @file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

        if ( ! is_array( $lines ) ) {
            return;
        }

        // Keep only the most recent HISTORY_LIMIT entries.
        $lines               = array_slice( $lines, -self::HISTORY_LIMIT );
        $this->shell_history = array_values( $lines );
    }

    /**
     * Persist the current in-memory history to disk.
     *
     * Silently skips if the history file path cannot be determined or
     * if the directory is not writable.
     *
     * @return void
     */
    private function history_save(): void {
        $path   = $this->history_file_path();
        $dir    = dirname( $path );

        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
            return;
        }

        $content = implode( PHP_EOL, $this->shell_history ) . PHP_EOL;

        file_put_contents( $path, $content, LOCK_EX );
    }

    /**
     * Return the absolute path to the history file.
     *
     * Resolves to {SMLISER_ABSPATH}.smliser_history.
     *
     * @return string|null
     */
    private function history_file_path(): string {
        return rtrim( SMLISER_ABSPATH, '/\\' ) . DIRECTORY_SEPARATOR . '.smliser.shell_history';
    }

    /*
    |--------------------------------------------
    | TERMINAL HELPERS
    |--------------------------------------------
    */

    /**
     * Strip all ANSI escape sequences from a string, returning plain text.
     *
     * Used to produce a readline-safe prompt — readline() does not
     * interpret ANSI codes and would print them literally to the terminal.
     *
     * @param  string $str
     * @return string
     */
    private function strip_ansi( string $str ): string {
        // CSI sequences: ESC [ <params> <final-byte>
        $str = preg_replace( '/\033\[[0-9;]*[A-Za-z]/', '', $str ) ?? $str;
        // OSC sequences: ESC ] ... BEL  or  ESC ] ... ESC \
        $str = preg_replace( '/\033\][^\007\033]*(?:\007|\033\\\\)/', '', $str ) ?? $str;

        return $str;
    }

    /**
     * Return the visible column width of a string, stripping ANSI escape codes.
     *
     * ANSI escape sequences (colors, bold, reset, cursor movement) are
     * zero-width — they produce no visible characters and must not be
     * counted when computing cursor positions. strlen() counts their
     * bytes, which causes the cursor to overshoot and corrupt the prompt.
     *
     * Covers all standard CSI sequences (ESC [ ... m / ESC [ ... J etc.)
     * and the OSC sequences used by some terminal emulators.
     *
     * @param  string $str
     * @return int Visible character count.
     */
    private function visible_length( string $str ): int {
        return mb_strlen( $this->strip_ansi( $str ), 'UTF-8' );
    }

    /**
     * Switch the terminal between raw and cooked (canonical) mode via stty.
     *
     * Raw mode:   input is delivered character-by-character with no
     *             buffering, echo, or special-character processing.
     * Cooked mode: normal terminal behaviour restored.
     *
     * No-op if stty is unavailable or system() is disabled.
     *
     * @param  bool $enable True to enter raw mode; false to restore cooked mode.
     * @return void
     */
    private function stty_raw( bool $enable ): void {
        if ( ! $this->stty_available() || ! $this->function_available( 'system' ) ) {
            return;
        }

        if ( $enable ) {
            @system( 'stty -icanon -echo min 1 time 0' );
        } else {
            @system( 'stty icanon echo' );
        }
    }
}