<?php
/**
 * Console output class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use SmartLicenseServer\Console\Contracts\OutputInterface;

/**
 * Default OutputInterface implementation — writes to real STDOUT/STDERR
 * streams, with ANSI color, tables, and progress bars gated by an
 * injected TerminalCapabilities instance and the configured verbosity.
 *
 * The stream and error-stream parameters exist so tests can pass in
 * `fopen( 'php://memory', 'w' )` handles instead of the real streams —
 * see BufferedOutput for a simpler in-memory alternative that doesn't
 * need real stream resources at all.
 */
class ConsoleOutput implements OutputInterface {

    /*
    |--------------------------------------------
    | ANSI COLOR CODES
    |--------------------------------------------
    */

    const ANSI_RESET  = "\033[0m";
    const ANSI_BOLD   = "\033[1m";
    const ANSI_CYAN   = "\033[36m";
    const ANSI_GREEN  = "\033[32m";
    const ANSI_YELLOW = "\033[33m";
    const ANSI_RED    = "\033[31m";
    const ANSI_DIM    = "\033[2m";

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
    private int $verbosity = self::VERBOSITY_NORMAL;

    /**
     * Progress bar state. Null when no progress bar is active.
     *
     * @var array{total: int, current: int, label: string, width: int}|null
     */
    private ?array $progress = null;

    /**
     * @param TerminalCapabilities $terminal Shared capability detector.
     * @param resource             $stdout   Stream for normal output.
     * @param resource             $stderr   Stream for error()  output.
     */
    public function __construct(
        private TerminalCapabilities $terminal,
        private $stdout = STDOUT,
        private $stderr = STDERR
    ) {}

    /*
    |--------------------------------------------
    | RAW WRITES
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public function write( string $message, bool $newline = false ): void {
        fwrite( $this->stdout, $message . ( $newline ? PHP_EOL : '' ) );
    }

    /**
     * {@inheritdoc}
     */
    public function writeln( string $message ): void {
        $this->write_line( $message, self::VERBOSITY_NORMAL );
    }

    /*
    |--------------------------------------------
    | STYLED LINES
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     *
     * Always writes to $stderr regardless of the configured verbosity —
     * errors must remain visible even in quiet mode, and must not be
     * mixed into $stdout so piped output stays clean.
     */
    public function error( string $message ): void {
        fwrite( $this->stderr, $this->colorize( self::ANSI_RED, '✖ ' . $message ) . PHP_EOL );
    }

    /**
     * {@inheritdoc}
     */
    public function info( string $message ): void {
        $this->write_line( $this->colorize( self::ANSI_CYAN, $message ), self::VERBOSITY_NORMAL );
    }

    /**
     * {@inheritdoc}
     */
    public function success( string $message ): void {
        $this->write_line( $this->colorize( self::ANSI_GREEN, '✔ ' . $message ), self::VERBOSITY_NORMAL );
    }

    /**
     * {@inheritdoc}
     */
    public function warning( string $message ): void {
        $this->write_line( $this->colorize( self::ANSI_YELLOW, '⚠ ' . $message ), self::VERBOSITY_NORMAL );
    }

    /**
     * {@inheritdoc}
     */
    public function newline( int $count = 1 ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        $this->write( str_repeat( PHP_EOL, max( 1, $count ) ) );
    }

    /*
    |--------------------------------------------
    | TABLE
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public function table( array $headers, array $rows ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        $widths = array_map( 'strlen', $headers );

        foreach ( $rows as $row ) {
            foreach ( array_values( $row ) as $i => $cell ) {
                $widths[ $i ] = max( $widths[ $i ] ?? 0, strlen( (string) $cell ) );
            }
        }

        $separator = '+' . implode( '+', array_map( fn( $w ) => str_repeat( '-', $w + 2 ), $widths ) ) . '+';

        $this->write( $separator, true );

        $this->write( '|' );
        foreach ( $headers as $i => $header ) {
            $this->write( ' ' . $this->colorize( self::ANSI_BOLD, str_pad( $header, $widths[ $i ] ) ) . ' |' );
        }
        $this->write( '', true );

        $this->write( $separator, true );

        foreach ( $rows as $row ) {
            $this->write( '|' );
            foreach ( array_values( $row ) as $i => $cell ) {
                $this->write( ' ' . str_pad( (string) $cell, $widths[ $i ] ) . ' |' );
            }
            $this->write( '', true );
        }

        $this->write( $separator, true );
    }

    /*
    |--------------------------------------------
    | PROGRESS BAR
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public function progress_start( int $total, string $label = '', int $width = 60 ): void {
        $this->progress = [
            'total'   => max( 1, $total ),
            'current' => 0,
            'label'   => $label,
            'width'   => $width,
        ];

        $this->draw_progress();
    }

    /**
     * {@inheritdoc}
     */
    public function progress_advance( int $step = 1 ): void {
        if ( null === $this->progress ) {
            return;
        }

        $this->progress['current'] = min(
            $this->progress['current'] + $step,
            $this->progress['total']
        );

        $this->draw_progress();
    }

    /**
     * {@inheritdoc}
     */
    public function progress_update_label( string $label ): void {
        if ( null === $this->progress ) {
            return;
        }

        $this->progress['label'] = $label;
        $this->draw_progress();
    }

    /**
     * {@inheritdoc}
     */
    public function progress_finish( string $final_label = '' ): void {
        if ( null === $this->progress ) {
            return;
        }

        $this->progress['current'] = $this->progress['total'];

        if ( '' !== $final_label ) {
            $this->progress['label'] = $final_label;
        }

        $this->draw_progress();
        $this->write( '', true );

        $this->progress = null;
    }

    /**
     * Draw the current progress bar, overwriting the previous frame
     * in place using a carriage return.
     *
     * @return void
     */
    private function draw_progress(): void {
        if ( null === $this->progress || $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        $total   = $this->progress['total'];
        $current = $this->progress['current'];
        $width   = $this->progress['width'];
        $label   = str_pad( substr( $this->progress['label'], 0, 40 ), 40 );

        $percent = (int) floor( ( $current / $total ) * 100 );
        $filled  = (int) ( ( $current / $total ) * $width );
        $empty   = $width - $filled;

        $bar = $this->colorize( self::ANSI_GREEN, str_repeat( '█', $filled ) )
             . $this->colorize( self::ANSI_DIM, str_repeat( '░', $empty ) );

        $prefix = '' !== $label ? $label . ' ' : '';

        $this->write( sprintf( "\r%s[%s] %3d%% (%d/%d)  ", $prefix, $bar, $percent, $current, $total ) );

        fflush( $this->stdout );
    }

    /*
    |--------------------------------------------
    | VERBOSITY
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public function set_verbosity( int $level ): void {
        $this->verbosity = $level;
    }

    /**
     * {@inheritdoc}
     */
    public function get_verbosity(): int {
        return $this->verbosity;
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Write a line to $stdout if the current verbosity allows it.
     *
     * @param string $message
     * @param int    $verbosity Minimum verbosity required to print.
     * @return void
     */
    private function write_line( string $message, int $verbosity ): void {
        if ( $this->verbosity < $verbosity ) {
            return;
        }

        $this->write( $message, true );
    }

    /**
     * Wrap a message in ANSI color codes if the terminal supports them.
     *
     * @param string $code    ANSI escape code constant.
     * @param string $message The message to colorize.
     * @return string
     */
    private function colorize( string $code, string $message ): string {
        if ( ! $this->terminal->supports_ansi() ) {
            return $message;
        }

        return $code . $message . self::ANSI_RESET;
    }
}