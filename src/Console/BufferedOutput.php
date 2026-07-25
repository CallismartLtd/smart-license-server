<?php
/**
 * Buffered output class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use SmartLicenseServer\Console\Contracts\OutputInterface;

/**
 * In-memory OutputInterface implementation for tests.
 *
 * Collects everything written to it into a string retrievable via
 * fetch(), instead of touching a real stream. Colorizing is skipped
 * entirely — test assertions should not have to account for ANSI
 * escape sequences.
 *
 * Table and progress-bar rendering are simplified rather than pixel
 * -matching ConsoleOutput's real formatting — tests exercising a
 * command's control flow don't need to assert exact bar/table layout,
 * only that the right calls happened with the right data. If a test
 * ever needs to assert exact table output, prefer asserting against
 * the row/header data the command passed rather than this class's
 * rendering.
 */
class BufferedOutput implements OutputInterface {

    private int $verbosity = self::VERBOSITY_NORMAL;

    /**
     * @var string[]
     */
    private array $lines = [];

    /**
     * @var string[]
     */
    private array $errors = [];

    public function write( string $message, bool $newline = false ): void {
        $this->lines[] = $message . ( $newline ? PHP_EOL : '' );
    }

    public function writeln( string $message ): void {
        $this->write( $message, true );
    }

    public function error( string $message ): void {
        $this->errors[] = $message;
    }

    public function info( string $message ): void {
        $this->writeln( $message );
    }

    public function success( string $message ): void {
        $this->writeln( '✔ ' . $message );
    }

    public function warning( string $message ): void {
        $this->writeln( '⚠ ' . $message );
    }

    public function newline( int $count = 1 ): void {
        $this->write( str_repeat( PHP_EOL, max( 1, $count ) ) );
    }

    public function table( array $headers, array $rows ): void {
        $this->writeln( implode( ' | ', $headers ) );

        foreach ( $rows as $row ) {
            $this->writeln( implode( ' | ', array_map( 'strval', array_values( $row ) ) ) );
        }
    }

    public function progress_start( int $total, string $label = '', int $width = 60 ): void {
        $this->writeln( sprintf( '[progress start] %s (0/%d)', $label, $total ) );
    }

    public function progress_advance( int $step = 1 ): void {
        // Intentionally no-op — tests care about start/finish, not every frame.
    }

    public function progress_update_label( string $label ): void {
        // Intentionally no-op.
    }

    public function progress_finish( string $final_label = '' ): void {
        $this->writeln( sprintf( '[progress finish] %s', $final_label ) );
    }

    public function set_verbosity( int $level ): void {
        $this->verbosity = $level;
    }

    public function get_verbosity(): int {
        return $this->verbosity;
    }

    /**
     * Return everything written so far (excluding error() calls) as a
     * single string.
     *
     * @return string
     */
    public function fetch(): string {
        return implode( '', $this->lines );
    }

    /**
     * Return everything passed to error() so far.
     *
     * @return string[]
     */
    public function fetch_errors(): array {
        return $this->errors;
    }
}