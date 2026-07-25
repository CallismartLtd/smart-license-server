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

use Override;
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
 * Tiers, selected per call based on platform/terminal capability:
 *
 *  Tier 2 — POSIX with stty available: raw-mode key handler. ↑/↓
 *           navigate in-memory + persisted history; Backspace/Delete
 *           work; Enter submits. History persists to disk across
 *           sessions.
 *
 *  Tier 3 — Windows, or POSIX without stty (piped / minimal
 *           environments): falls through to the wrapped input's plain
 *           read_line(). History is still recorded and persisted, but
 *           ↑/↓ navigation is unavailable since input arrives a full
 *           line at a time.
 *
 * A readline-extension tier (native history ring) is intentionally
 * not wired in here — see read_line_readline() below, kept for
 * reference but unused, matching the prior decision in this codebase
 * to skip it because readline() prints ANSI escape codes in the
 * prompt literally instead of interpreting them.
 */
class HistoryAwareInput implements InputInterface {

    /**
     * Maximum number of history entries kept in memory and on disk.
     */
    private const HISTORY_LIMIT = 1000;

    /**
     * In-memory history entries for the current session. Oldest
     * entry at index 0, newest at the end.
     *
     * @var string[]
     */
    private array $history = [];

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
     * @param TerminalCapabilities  $terminal     Shared capability detector.
     * @param string                $history_path Absolute path to the history file.
     * @param resource              $stdin        Stream to read raw-mode input from.
     */
    public function __construct(
        private InputInterface $inner,
        private TerminalCapabilities $terminal,
        private string $history_path,
        private $stdin = STDIN
    ) {}

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

        if ( ! $this->terminal->is_windows()
            && $this->terminal->is_tty( $this->stdin )
            && $this->terminal->stty_available()
            && $this->terminal->function_available( 'system' )
        ) {
            return $this->read_line_raw( $prompt );
        }

        $line = $this->inner->read_line( $prompt );

        if ( null !== $line && '' !== $line ) {
            $this->history_push( $line );
            $this->save_history();
        }

        return $line;
    }

    /*
    |--------------------------------------------
    | InputInterface — delegated, no history
    |--------------------------------------------
    */

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
     */
    #[Override]
    public function prompt(string $question, string $default = ''): string {
        return $this->inner->prompt( $question, $default );
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
     * navigation, printable character echo, and Backspace/Delete
     * handling manually.
     *
     * @param string $prompt
     * @return string|null The submitted line, or null on EOF.
     */
    private function read_line_raw( string $prompt ): ?string {
        fwrite( STDOUT, $prompt );

        @system( 'stty -icanon -echo min 1 time 0' );

        $buffer   = '';
        $hist_idx = null;
        $saved    = '';
        $result   = null;
        $eof      = false;

        try {
            while ( true ) {
                $char = fgetc( $this->stdin );

                if ( false === $char ) {
                    $eof = true;
                    break;
                }

                $byte = ord( $char );

                // Enter.
                if ( 13 === $byte || 10 === $byte ) {
                    fwrite( STDOUT, PHP_EOL );
                    $result = $buffer;
                    break;
                }

                // Ctrl-C — abandon the line.
                if ( 3 === $byte ) {
                    fwrite( STDOUT, PHP_EOL );
                    $result = '';
                    break;
                }

                // Ctrl-D — EOF when buffer empty, otherwise ignore.
                if ( 4 === $byte ) {
                    if ( '' === $buffer ) {
                        fwrite( STDOUT, PHP_EOL );
                        $eof = true;
                        break;
                    }
                    continue;
                }

                // Backspace.
                if ( 127 === $byte || 8 === $byte ) {
                    if ( '' !== $buffer ) {
                        $buffer = substr( $buffer, 0, -1 );
                        fwrite( STDOUT, "\x08 \x08" );
                    }
                    continue;
                }

                // ESC sequence — arrow keys.
                if ( 27 === $byte ) {
                    $seq = $this->read_escape_sequence();

                    if ( '[A' === $seq ) {
                        [ $buffer, $hist_idx, $saved ] = $this->history_older( $buffer, $hist_idx, $saved, $prompt );
                        continue;
                    }

                    if ( '[B' === $seq ) {
                        [ $buffer, $hist_idx ] = $this->history_newer( $hist_idx, $saved, $prompt );
                        continue;
                    }

                    continue;
                }

                // Printable character.
                if ( $byte >= 32 ) {
                    $buffer .= $char;
                    fwrite( STDOUT, $char );
                }
            }
        } finally {
            @system( 'stty icanon echo' );
        }

        if ( $eof ) {
            return null;
        }

        $line = trim( $result ?? '' );

        if ( '' !== $line ) {
            $this->history_push( $line );
            $this->save_history();
        }

        return $line;
    }

    /**
     * Read the bytes following an ESC byte to complete an escape
     * sequence (arrow keys send ESC [ A/B/C/D).
     *
     * @return string The sequence characters after the ESC byte (e.g. '[A').
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

        $r = [ $this->stdin ];
        $w = null;
        $e = null;

        if ( ! stream_select( $r, $w, $e, 0, 50000 ) ) {
            return '[';
        }

        $final = fgetc( $this->stdin );

        return false !== $final ? '[' . $final : '[';
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

        fwrite( STDOUT, "\r" . $prompt . $new . $erase . "\r" . $prompt . $new );
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
            $this->save_history();
        }

        return $line;
    }

    /*
    |--------------------------------------------
    | HISTORY PERSISTENCE
    |--------------------------------------------
    */

    /**
     * Append an entry to the in-memory history, enforcing the cap and
     * skipping consecutive duplicates.
     *
     * @param string $line
     * @return void
     */
    private function history_push( string $line ): void {
        if ( ! empty( $this->history ) && end( $this->history ) === $line ) {
            return;
        }

        $this->history[] = $line;

        if ( count( $this->history ) > self::HISTORY_LIMIT ) {
            array_shift( $this->history );
        }
    }

    /**
     * Load history from the persisted file into memory. Called once
     * per session; silently does nothing if the file is missing or
     * unreadable.
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

        $lines = @file( $this->history_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

        if ( ! is_array( $lines ) ) {
            return;
        }

        $this->history = array_values( array_slice( $lines, -self::HISTORY_LIMIT ) );
    }

    /**
     * Persist the current in-memory history to disk. Silently skips
     * if the directory is not writable.
     *
     * @return void
     */
    private function save_history(): void {
        $dir = dirname( $this->history_path );

        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
            return;
        }

        file_put_contents( $this->history_path, implode( PHP_EOL, $this->history ) . PHP_EOL, LOCK_EX );
    }
}