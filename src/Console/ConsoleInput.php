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
 * NonInteractiveRunner commands to carry the same complexity.
 */
class ConsoleInput implements InputInterface {

    /**
     * @param Terminal $terminal Shared capability detector,
     *                                        also owns the actual stty
     *                                        echo-suppression toggling.
     * @param resource              $stdin    Stream to read from.
     * @param resource              $stdout   Stream to write prompts to —
     *                                        injected rather than hardcoding
     *                                        the STDOUT constant, so this
     *                                        class can be exercised against
     *                                        a captured stream in tests.
     */
    public function __construct(
        private Terminal $terminal,
        private $stdin  = STDIN,
        private $stdout = STDOUT
    ) {}

    /*
    |--------------------------------------------
    | READING
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public function read_line( string $prompt = '' ): ?string {
        if ( '' !== $prompt ) {
            $this->write_out( $prompt );
        }

        $line = fgets( $this->stdin );

        return false === $line ? null : trim( $line );
    }

    /**
     * {@inheritdoc}
     */
    public function prompt( string $question, string $default = '' ): string {
        $prompt = '' !== $default
            ? sprintf( '%s [%s] ', $question, $default )
            : $question . ' ';

        $line = $this->read_line( $prompt ) ?? '';

        return '' !== $line ? $line : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function confirm( string $question, bool $default = false ): bool {
        $hint  = $default ? '[Y/n]' : '[y/N]';
        $input = strtolower( (string) $this->read_line( sprintf( '%s %s: ', $question, $hint ) ) );

        if ( '' === $input ) {
            return $default;
        }

        return in_array( $input, [ 'y', 'yes' ], true );
    }

    /**
     * {@inheritdoc}
     */
    public function choice( string $question, array $choices, $default = null ) {
        foreach ( $choices as $key => $label ) {
            $this->write_out( sprintf( '  [%s] %s' . PHP_EOL, $key, $label ) );
        }

        $answer = (string) $this->read_line( $question . ': ' );

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
            $this->write_out( $prompt );
        }

        if ( ! $this->terminal->is_tty( $this->stdin ) ) {
            return (string) $this->read_line();
        }

        if ( $this->terminal->is_windows() ) {
            return $this->windows_secret();
        }

        if ( $this->terminal->disable_echo() ) {
            $line = fgets( $this->stdin );
            $this->terminal->enable_echo();
            $this->write_out( PHP_EOL );

            return false === $line ? '' : rtrim( $line, "\r\n" );
        }

        // Nothing available to suppress echo — visible fallback rather
        // than failing the prompt outright.
        return (string) $this->read_line();
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
            return (string) $this->read_line();
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
            return (string) $this->read_line();
        }

        $input = stream_get_contents( $pipes[1] );
        fclose( $pipes[1] );
        proc_close( $process );

        $this->write_out( PHP_EOL );

        return false === $input ? '' : rtrim( $input, "\r\n" );
    }

    /**
     * Write prompt
     *
     * @param string $prompt
     * @return bool
     */
    private function write_out( string $prompt ): bool {
        return (bool) fwrite( $this->stdout, $prompt );
    }
}