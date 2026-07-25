<?php
/**
 * Console input class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use SmartLicenseServer\Console\Contracts\InputInterface;

/**
 * Default InputInterface implementation — reads from a real STDIN
 * stream, with hidden-input support (secret()) via readline, stty, or
 * a PowerShell SecureString prompt on Windows.
 *
 * This does not include history navigation (↑/↓) — see
 * HistoryAwareInput, which wraps an instance of this class to add
 * that behavior for the interactive shell without forcing one-shot
 * CLIRunner commands to carry the same complexity.
 */
class ConsoleInput implements InputInterface {

    /**
     * @param TerminalCapabilities $terminal Shared capability detector.
     * @param resource              $stdin    Stream to read from.
     */
    public function __construct(
        private TerminalCapabilities $terminal,
        private $stdin = STDIN
    ) {}

    /*
    |--------------------------------------------
    | READING
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public function read_line( string $prompt = '' ): string {
        if ( '' !== $prompt ) {
            fwrite( STDOUT, $prompt );
        }

        $line = fgets( $this->stdin );

        return false === $line ? '' : trim( $line );
    }

    /**
     * {@inheritdoc}
     */
    public function confirm( string $question, bool $default = false ): bool {
        $hint  = $default ? '[Y/n]' : '[y/N]';
        $input = strtolower( $this->read_line( sprintf( '%s %s: ', $question, $hint ) ) );

        if ( '' === $input ) {
            return $default;
        }

        return in_array( $input, [ 'y', 'yes' ], true );
    }

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

        $input = $this->read_line( $prompt );

        return $input ?: $default;
    }

    /**
     * {@inheritdoc}
     */
    public function choice( string $question, array $choices, $default = null ) : mixed {
        foreach ( $choices as $key => $label ) {
            fwrite( STDOUT, sprintf( '  [%s] %s' . PHP_EOL, $key, $label ) );
        }

        $answer = $this->read_line( $question . ': ' );

        return $choices[ $answer ] ?? $default;
    }

    /*
    |--------------------------------------------
    | HIDDEN INPUT
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     *
     * Platform strategy:
     *  - No TTY:         plain read_line() — nothing to hide from a
     *                    non-interactive stream (piped input, CI, etc.).
     *  - Windows:        PowerShell `Read-Host -AsSecureString`, falling
     *                    back to a plain read if PowerShell is unavailable.
     *  - Linux / macOS:  readline hidden-input mode if available, else
     *                    `stty -echo`, else a plain (visible) fallback.
     */
    public function secret( string $prompt = '' ): string {
        if ( '' !== $prompt ) {
            fwrite( STDOUT, $prompt );
        }

        if ( ! $this->terminal->is_tty( $this->stdin ) ) {
            return $this->read_line();
        }

        if ( $this->terminal->is_windows() ) {
            return $this->windows_secret();
        }

        if ( $this->terminal->readline_available() ) {
            $input = $this->readline_hidden();
            fwrite( STDOUT, PHP_EOL );
            return trim( $input );
        }

        if ( $this->terminal->stty_available() && $this->terminal->function_available( 'system' ) ) {
            @system( 'stty -echo' );
            $line = fgets( $this->stdin );
            @system( 'stty echo' );
            fwrite( STDOUT, PHP_EOL );

            return false === $line ? '' : trim( $line );
        }

        // Nothing available to suppress echo — visible fallback rather
        // than failing the prompt outright.
        return $this->read_line();
    }

    /**
     * Read hidden input on Windows via PowerShell's SecureString prompt.
     *
     * The ps1 one-liner reads a SecureString and converts it back to
     * plain text so PHP receives it on stdout. Falls back to a plain
     * read if PowerShell/proc_open is not available.
     *
     * @return string
     */
    private function windows_secret(): string {
        if ( ! $this->terminal->function_available( 'proc_open' ) ) {
            return $this->read_line();
        }

        $ps1 = '$s=Read-Host -AsSecureString;'
             . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
             . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))';

        $cmd         = 'powershell -NoProfile -NonInteractive -Command "' . $ps1 . '"';
        $descriptors = [
            0 => [ 'file', 'php://stdin', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'file', 'php://stderr', 'w' ],
        ];

        $process = @proc_open( $cmd, $descriptors, $pipes );

        if ( ! is_resource( $process ) ) {
            return $this->read_line();
        }

        $input = stream_get_contents( $pipes[1] );
        fclose( $pipes[1] );
        proc_close( $process );

        fwrite( STDOUT, PHP_EOL );

        return trim( (string) $input );
    }

    /**
     * Read a line without terminal echo using the readline extension's
     * callback handler, installed with an empty prompt so readline
     * itself never draws anything (we've already printed our own).
     *
     * @return string
     */
    private function readline_hidden(): string {
        $input = '';

        readline_callback_handler_install( '', function ( $line ) use ( &$input ) {
            $input = $line;
        } );

        while ( true ) {
            $r = [ $this->stdin ];
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
}