<?php
/**
 * History-aware input class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use SmartLicenseServer\Console\Contracts\InputInterface;

/**
 * Decorates an InputInterface with cross-platform, history-aware line
 * reading (↑/↓ navigation) for the interactive shell.
 *
 * Only read_line() gets history behavior — confirm(), choice(), and
 * secret() delegate straight to the wrapped instance, since browsing
 * command history mid-confirmation or mid-secret-entry isn't a real
 * use case (and history should never retain what was typed into a
 * secret() prompt).
 *
 * ## Persistence model
 *
 * History lives in memory for the life of the process. It is written
 * to disk exactly once, via a shutdown function registered in the
 * constructor, as a single block prefixed with one timestamp marker:
 *
 *   # smliser-session: 2026-07-26 14:32:07
 *   license list
 *   app list --type=plugin
 *   cache clear
 *
 * The marker is filtered back out on load — see load_history() — so
 * it never shows up in ↑/↓ recall as if it were a command. The file
 * itself is append-only and never rewritten, the same shape as
 * .bash_history.
 *
 * Trade-off worth knowing: because the write happens at shutdown
 * rather than after each command, a hard kill (SIGKILL, a crash PHP's
 * own shutdown sequence never runs for) loses that session's history.
 * register_shutdown_function() DOES run on normal termination and on
 * fatal errors, but not on signals PHP doesn't intercept — if that
 * matters to you, it needs an explicit pcntl signal handler that
 * calls exit() so the normal shutdown sequence (and this flush) runs.
 *
 * Tiers, selected per call based on platform/terminal capability:
 *
 *  Tier 2 — POSIX with stty available: raw-mode key handler. ↑/↓
 *           navigate the in-memory recall buffer; Backspace/Delete
 *           work; Enter submits.
 *
 *  Tier 3 — Windows, or POSIX without stty (piped / minimal
 *           environments): falls through to the wrapped input's plain
 *           read_line(). History is still recorded in memory and
 *           flushed at shutdown the same way, but ↑/↓ navigation is
 *           unavailable since input arrives a full line at a time.
 *
 * A readline-extension tier (native history ring) is intentionally
 * not wired in here — see read_line_readline() below, kept for
 * reference but unused, matching the prior decision in this codebase
 * to skip it because readline() prints ANSI escape codes in the
 * prompt literally instead of interpreting them.
 */
class HistoryAwareInput implements InputInterface {

    /**
     * Marker line prefix written once per session, immediately before
     * that session's entries, so a later load can tell "this is a
     * timestamp header" apart from "this is a command" by prefix
     * alone — filtered out in load_history(), never treated as a
     * recall entry.
     */
    private const SESSION_MARKER_PREFIX = '# smliser-session: ';

    /**
     * In-memory recall buffer for the current session — entries
     * loaded from disk plus everything submitted so far this session.
     * Oldest entry at index 0, newest at the end. No size cap —
     * matching .bash_history, where the shell keeps everything a
     * session has seen without trimming it for memory reasons.
     *
     * @var string[]
     */
    private array $history = [];

    /**
     * Entries submitted THIS session only, in submission order —
     * distinct from $history, which also includes entries loaded from
     * prior sessions' file content. Only these get written at
     * shutdown; already-persisted content is never read back in and
     * rewritten, so nothing on disk is ever touched twice.
     *
     * @var string[]
     */
    private array $pending_entries = [];

    /**
     * Whether history has been loaded from disk this session.
     *
     * @var bool
     */
    private bool $history_loaded = false;

    /**
     * @param InputInterface        $inner        Wrapped input — read_line()
     *                                             falls through to this in
     *                                             Tier 3; confirm()/choice()/
     *                                             secret() always delegate here.
     * @param TerminalCapabilities  $terminal     Shared capability detector,
     *                                             also owns the actual stty
     *                                             raw-mode/echo toggling.
     * @param string                $history_path Absolute path to the single,
     *                                             ever-growing history file —
     *                                             the same shape as
     *                                             .bash_history, not split by
     *                                             day or otherwise rotated.
     * @param resource              $stdin        Stream to read raw-mode input from.
     * @param resource              $stdout       Stream to echo keystrokes/redraws to —
     *                                             injected rather than hardcoding the
     *                                             STDOUT constant, so this class can be
     *                                             exercised against a captured stream
     *                                             the same way ConsoleOutput can.
     */
    public function __construct(
        private InputInterface $inner,
        private TerminalCapabilities $terminal,
        private string $history_path,
        private $stdin = STDIN,
        private $stdout = STDOUT
    ) {
        // Registered once, here, rather than lazily on first use —
        // guarantees exactly one flush per instance regardless of how
        // many times read_line() is called.
        register_shutdown_function( [ $this, 'flush_history' ] );
    }

    /*
    |--------------------------------------------
    | InputInterface — history-aware
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public function read_line( string $prompt = '' ): ?string {
        $this->load_history();

        $line = $this->read_via_best_available_mode( $prompt );

        if ( null !== $line && '' !== $line ) {
            $this->history_push( $line );
        }

        return $line;
    }

    /**
     * Pick Tier 2 (raw mode, cursor/history navigation) when the
     * terminal supports it, falling back to Tier 3 (the wrapped
     * input's plain read_line()) otherwise — including when
     * TerminalCapabilities::enable_raw_mode() itself reports it
     * couldn't actually engage raw mode (stty/system unavailable),
     * which is the one case the old is_windows()/is_tty()/
     * stty_available()/function_available() checks here couldn't
     * detect ahead of time without duplicating enable_raw_mode()'s
     * own logic.
     *
     * @param string $prompt
     * @return string|null
     */
    private function read_via_best_available_mode( string $prompt ): ?string {
        if ( ! $this->terminal->is_windows() && $this->terminal->is_tty( $this->stdin ) ) {
            if ( $this->terminal->enable_raw_mode() ) {
                try {
                    return $this->read_line_raw( $prompt );
                } finally {
                    $this->terminal->restore_cooked_mode();
                }
            }
        }

        return $this->inner->read_line( $prompt );
    }

    /*
    |--------------------------------------------
    | InputInterface — delegated, no history
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     *
     * Delegated, not history-aware — prompt() is a mid-execution
     * question with a default, not a REPL command line; browsing
     * history while answering one isn't a real use case.
     */
    public function prompt( string $question, string $default = '' ): string {
        return $this->inner->prompt( $question, $default );
    }

    /**
     * {@inheritdoc}
     */
    public function confirm( string $question, bool $default = false ): bool {
        return $this->inner->confirm( $question, $default );
    }

    /**
     * {@inheritdoc}
     */
    public function choice( string $question, array $choices, $default = null ) {
        return $this->inner->choice( $question, $choices, $default );
    }

    /**
     * {@inheritdoc}
     *
     * Deliberately never recorded to history — a secret typed at this
     * prompt must not end up readable in the history file.
     */
    public function secret( string $prompt = '' ): string {
        return $this->inner->secret( $prompt );
    }

    /*
    |--------------------------------------------
    | TIER 2 — stty raw mode
    |--------------------------------------------
    */

    /**
     * Read a line in stty raw mode, implementing ↑/↓ history
     * navigation, ←/→ cursor movement, Home/End, printable character
     * insertion at the cursor, and Backspace/Delete handling manually.
     *
     * Assumes raw mode is already active — read_via_best_available_mode()
     * engages it via TerminalCapabilities::enable_raw_mode() before
     * calling this, and restores cooked mode afterward regardless of
     * how this method returns. This method has no stty knowledge of
     * its own beyond that assumption.
     *
     * Redraws only use relative cursor moves (\033[{n}D / \033[{n}C)
     * based on buffer length, never absolute column positions — the
     * prompt may contain ANSI color codes whose byte length doesn't
     * match its visible width, so jumping to a computed column would
     * be wrong; a relative move measured purely in buffer characters
     * is not affected by that.
     *
     * @param string $prompt
     * @return string|null The submitted line, or null on EOF.
     */
    private function read_line_raw( string $prompt ): ?string {
        fwrite( $this->stdout, $prompt );

        $buffer   = '';
        $cursor   = 0; // Index into $buffer where the next edit happens.
        $hist_idx = null;
        $saved    = '';
        $result   = null;
        $eof      = false;

        while ( true ) {
            $char = fgetc( $this->stdin );

            if ( false === $char ) {
                $eof = true;
                break;
            }

            $byte = ord( $char );

            // Enter.
            if ( 13 === $byte || 10 === $byte ) {
                fwrite( $this->stdout, PHP_EOL );
                $result = $buffer;
                break;
            }

            // Ctrl-C — abandon the line.
            if ( 3 === $byte ) {
                fwrite( $this->stdout, PHP_EOL );
                $result = '';
                break;
            }

            // Ctrl-D — EOF when buffer empty, otherwise ignore.
            if ( 4 === $byte ) {
                if ( '' === $buffer ) {
                    fwrite( $this->stdout, PHP_EOL );
                    $eof = true;
                    break;
                }
                continue;
            }

            // Backspace — delete the character before the cursor,
            // not necessarily the last character in the buffer.
            if ( 127 === $byte || 8 === $byte ) {
                if ( $cursor > 0 ) {
                    $buffer = substr( $buffer, 0, $cursor - 1 ) . substr( $buffer, $cursor );
                    --$cursor;

                    // Step left over the removed character, redraw
                    // everything after it plus one trailing space
                    // to erase the character that used to be there,
                    // then step back to the (new) cursor position.
                    $tail = substr( $buffer, $cursor ) . ' ';
                    fwrite( $this->stdout, "\033[D" . $tail . "\033[" . strlen( $tail ) . 'D' );
                }
                continue;
            }

            // ESC sequence — arrows, Home/End, Delete.
            if ( 27 === $byte ) {
                $seq = $this->read_escape_sequence();

                if ( '[A' === $seq ) {
                    [ $buffer, $hist_idx, $saved ] = $this->history_older( $buffer, $hist_idx, $saved, $prompt );
                    $cursor = strlen( $buffer );
                    continue;
                }

                if ( '[B' === $seq ) {
                    [ $buffer, $hist_idx ] = $this->history_newer( $hist_idx, $saved, $prompt );
                    $cursor = strlen( $buffer );
                    continue;
                }

                // Left arrow.
                if ( '[D' === $seq ) {
                    if ( $cursor > 0 ) {
                        --$cursor;
                        fwrite( $this->stdout, "\033[D" );
                    }
                    continue;
                }

                // Right arrow.
                if ( '[C' === $seq ) {
                    if ( $cursor < strlen( $buffer ) ) {
                        fwrite( $this->stdout, "\033[C" );
                        ++$cursor;
                    }
                    continue;
                }

                // Home — xterm sends "[H", some terminals send "[1~".
                if ( '[H' === $seq || '[1~' === $seq ) {
                    if ( $cursor > 0 ) {
                        fwrite( $this->stdout, "\033[{$cursor}D" );
                        $cursor = 0;
                    }
                    continue;
                }

                // End — xterm sends "[F", some terminals send "[4~".
                if ( '[F' === $seq || '[4~' === $seq ) {
                    $remaining = strlen( $buffer ) - $cursor;
                    if ( $remaining > 0 ) {
                        fwrite( $this->stdout, "\033[{$remaining}C" );
                        $cursor = strlen( $buffer );
                    }
                    continue;
                }

                // Forward Delete — removes the character AT the
                // cursor, distinct from Backspace which removes
                // the character before it.
                if ( '[3~' === $seq ) {
                    if ( $cursor < strlen( $buffer ) ) {
                        $buffer = substr( $buffer, 0, $cursor ) . substr( $buffer, $cursor + 1 );
                        $tail   = substr( $buffer, $cursor ) . ' ';
                        fwrite( $this->stdout, $tail . "\033[" . strlen( $tail ) . 'D' );
                    }
                    continue;
                }

                // Any other escape sequence — silently ignore.
                continue;
            }

            // Printable character — insert at the cursor, not
            // necessarily append at the end.
            if ( $byte >= 32 ) {
                $buffer = substr( $buffer, 0, $cursor ) . $char . substr( $buffer, $cursor );
                ++$cursor;

                $tail = substr( $buffer, $cursor - 1 );
                fwrite( $this->stdout, $tail );

                $back = strlen( $tail ) - 1;
                if ( $back > 0 ) {
                    fwrite( $this->stdout, "\033[{$back}D" );
                }
            }
        }

        if ( $eof ) {
            return null;
        }

        return trim( $result ?? '' );
    }

    /**
     * Read the bytes following an ESC byte to complete an escape
     * sequence.
     *
     * Handles both CSI forms: a single final letter (arrows send
     * "ESC [ A/B/C/D", Home/End on some terminals send "ESC [ H/F")
     * and a multi-byte numeric form terminated by "~" (Delete sends
     * "ESC [ 3 ~", Home/End on other terminals send "ESC [ 1 ~" /
     * "ESC [ 4 ~"). Reads until either terminator is seen or the
     * short read timeout elapses.
     *
     * @return string The sequence characters after the ESC byte (e.g. '[A', '[3~').
     */
    private function read_escape_sequence(): string {
        $r = [ $this->stdin ];
        $w = null;
        $e = null;

        if ( ! stream_select( $r, $w, $e, 0, 50000 ) ) {
            return ''; // Lone ESC key.
        }

        $next = fgetc( $this->stdin );

        if ( false === $next || '[' !== $next ) {
            return (string) $next;
        }

        $seq = '[';

        while ( true ) {
            $r = [ $this->stdin ];
            $w = null;
            $e = null;

            if ( ! stream_select( $r, $w, $e, 0, 50000 ) ) {
                break;
            }

            $byte = fgetc( $this->stdin );

            if ( false === $byte ) {
                break;
            }

            $seq .= $byte;

            // A letter or '~' terminates the sequence; digits in
            // between (as in "[3~") keep the read going.
            if ( ctype_alpha( $byte ) || '~' === $byte ) {
                break;
            }
        }

        return $seq;
    }

    /**
     * Navigate to an older history entry and redraw the line.
     *
     * @param string   $buffer   Current buffer contents.
     * @param int|null $hist_idx Current history cursor (null = fresh line).
     * @param string   $saved    Draft line saved before browsing started.
     * @param string   $prompt   The full prompt string (ANSI codes are fine).
     * @return array{string, int, string} [$new_buffer, $new_hist_idx, $saved]
     */
    private function history_older( string $buffer, ?int $hist_idx, string $saved, string $prompt ): array {
        $count = count( $this->history );

        if ( 0 === $count ) {
            return [ $buffer, $hist_idx, $saved ];
        }

        if ( null === $hist_idx ) {
            $saved    = $buffer;
            $hist_idx = $count - 1;
        } elseif ( $hist_idx > 0 ) {
            --$hist_idx;
        }

        $entry = $this->history[ $hist_idx ];
        $this->rewrite_line( $buffer, $entry, $prompt );

        return [ $entry, $hist_idx, $saved ];
    }

    /**
     * Navigate to a newer history entry, or restore the draft line.
     *
     * @param int|null $hist_idx Current history cursor.
     * @param string   $saved    The draft line saved before browsing.
     * @param string   $prompt   The full prompt string (ANSI codes are fine).
     * @return array{string, int|null} [$new_buffer, $new_hist_idx]
     */
    private function history_newer( ?int $hist_idx, string $saved, string $prompt ): array {
        if ( null === $hist_idx ) {
            return [ '', null ];
        }

        $count = count( $this->history );

        if ( $hist_idx >= $count - 1 ) {
            $this->rewrite_line( $this->history[ $hist_idx ] ?? '', $saved, $prompt );
            return [ $saved, null ];
        }

        ++$hist_idx;
        $entry = $this->history[ $hist_idx ];
        $this->rewrite_line( $this->history[ $hist_idx - 1 ], $entry, $prompt );

        return [ $entry, $hist_idx ];
    }

    /**
     * Overwrite the buffer area of the current line with new content.
     *
     * @param string $old    Buffer currently displayed after the prompt.
     * @param string $new    Replacement buffer content.
     * @param string $prompt The full prompt string (ANSI codes are fine).
     * @return void
     */
    private function rewrite_line( string $old, string $new, string $prompt ): void {
        $erase = strlen( $old ) > strlen( $new )
            ? str_repeat( ' ', strlen( $old ) - strlen( $new ) )
            : '';

        fwrite( $this->stdout, "\r" . $prompt . $new . $erase . "\r" . $prompt . $new );
    }

    /*
    |--------------------------------------------
    | TIER 1 — readline (kept for reference, unused)
    |--------------------------------------------
    */

    /**
     * Read a line using the readline extension.
     *
     * Not currently wired into read_line() — readline() does not
     * interpret ANSI escape codes in its prompt argument, it prints
     * them literally, which corrupts colored prompts. Kept here in
     * case a future ANSI-stripped-prompt variant is worth adding
     * rather than being silently dropped from the codebase.
     *
     * @param string $prompt
     * @return string|null
     */
    private function read_line_readline( string $prompt ): ?string {
        static $synced = false;

        if ( ! $synced ) {
            foreach ( $this->history as $entry ) {
                readline_add_history( $entry );
            }
            $synced = true;
        }

        $line = readline( $prompt );

        if ( false === $line ) {
            return null;
        }

        $line = trim( $line );

        if ( '' !== $line ) {
            readline_add_history( $line );
            $this->history_push( $line );
        }

        return $line;
    }

    /*
    |--------------------------------------------
    | HISTORY PERSISTENCE
    |--------------------------------------------
    */

    /**
     * Append an entry to both the recall buffer and this session's
     * pending-write queue, skipping consecutive duplicates (the same
     * convention as bash's HISTCONTROL=ignoredups). No size cap on
     * either — a CLI session's command count isn't a memory concern
     * worth trading away unbounded ↑/↓ recall for.
     *
     * @param string $line
     * @return void
     */
    private function history_push( string $line ): void {
        if ( ! empty( $this->history ) && end( $this->history ) === $line ) {
            return;
        }

        $this->history[]         = $line;
        $this->pending_entries[] = $line;
    }

    /**
     * Load the history file into the ↑/↓ recall buffer, filtering out
     * session marker lines and sanitizing what's left. Called once
     * per session; silently does nothing if the file doesn't exist
     * yet or isn't readable — same as .bash_history behaves on a
     * fresh shell profile with no prior history.
     *
     * @return void
     */
    private function load_history(): void {
        if ( $this->history_loaded ) {
            return;
        }

        $this->history_loaded = true;

        if ( ! is_readable( $this->history_path ) ) {
            return;
        }

        $lines = @file( $this->history_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: [];

        foreach ( $lines as $line ) {
            // Marker lines are timestamp headers, not commands —
            // written once per session by flush_history() — so they
            // never enter the recall buffer.
            if ( str_starts_with( $line, self::SESSION_MARKER_PREFIX ) ) {
                continue;
            }

            $this->history[] = $this->sanitize_for_recall( $line );
        }
    }

    /**
     * Append this session's pending entries to the history file as a
     * single block, prefixed with one timestamp marker line, so an
     * auditor can tell which commands ran in which session and when.
     *
     * Registered via register_shutdown_function() in the constructor
     * — runs on normal script termination and on fatal errors, but
     * see the class docblock for the case it does NOT cover (a signal
     * PHP's shutdown sequence never runs for). Public rather than
     * private: register_shutdown_function() needs a valid callable,
     * and there's no reason a caller couldn't force an early flush
     * (e.g. before a long-running command) if that's ever useful.
     *
     * No-op if there's nothing pending, or if the containing
     * directory isn't writable.
     *
     * @return void
     */
    public function flush_history(): void {
        if ( empty( $this->pending_entries ) ) {
            return;
        }

        $dir = dirname( $this->history_path );

        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
            return;
        }

        $sanitized = array_map( [ $this, 'sanitize_for_storage' ], $this->pending_entries );

        $block = self::SESSION_MARKER_PREFIX . date( 'Y-m-d H:i:s' ) . PHP_EOL
            . implode( PHP_EOL, $sanitized ) . PHP_EOL;

        file_put_contents( $this->history_path, $block, FILE_APPEND | LOCK_EX );

        $this->pending_entries = [];
    }

    /**
     * Sanitize a command line before writing it to the history file.
     *
     * Two concerns, not one:
     *   - Structural: an embedded newline/carriage-return in a single
     *     command would split into extra "lines" on disk once
     *     written, corrupting the one-entry-per-line format and
     *     potentially letting a crafted command masquerade as a fake
     *     session marker line of its own.
     *   - Terminal safety: raw control/escape bytes stored as-is would
     *     be replayed as real escape sequences the next time this
     *     line is recalled and echoed back to the terminal — stripped
     *     here too, on the write side, as defense in depth rather than
     *     relying solely on sanitize_for_recall() at load time.
     *
     * @param string $line
     * @return string
     */
    private function sanitize_for_storage( string $line ): string {
        $line = str_replace( [ "\r", "\n" ], ' ', $line );

        return preg_replace( '/[\x00-\x1F\x7F]/', '', $line );
    }

    /**
     * Strip control and escape characters from a line loaded from the
     * history file before it enters the recall buffer.
     *
     * A history file is effectively untrusted input once it's been
     * sitting on disk — it could have been edited, corrupted, or (in
     * a shared/compromised environment) tampered with. Loaded lines
     * get echoed straight back to the terminal during ↑ navigation
     * redraws, so raw control/escape bytes here — in particular ESC
     * (0x1B), the byte that begins every ANSI escape sequence this
     * class's own redraw logic interprets — could inject arbitrary
     * terminal behavior when a tampered entry is replayed.
     *
     * @param string $line
     * @return string
     */
    private function sanitize_for_recall( string $line ): string {
        return preg_replace( '/[\x00-\x1F\x7F]/', '', $line );
    }
}