<?php
/**
 * Console output interface file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Contracts
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Contracts;

/**
 * Contract for writing output to the operator's terminal — styled lines,
 * tables, progress bars, and verbosity gating.
 *
 * error() is intentionally separate from the other styled writers: a
 * correct implementation routes it to STDERR rather than STDOUT, so a
 * command's real output can still be piped into another program while
 * errors remain visible on the terminal.
 */
interface OutputInterface {

    /**
     * Verbosity level: suppress everything except error() and completion
     * messages that opt in to always printing.
     */
    const VERBOSITY_QUIET = 0;

    /**
     * Verbosity level: standard output. The default.
     */
    const VERBOSITY_NORMAL = 1;

    /**
     * Verbosity level: extended/diagnostic output.
     */
    const VERBOSITY_VERBOSE = 2;

    /**
     * Write a raw message, optionally followed by a newline.
     *
     * @param string $message
     * @param bool   $newline
     * @return void
     */
    public function write( string $message, bool $newline = false ): void;

    /**
     * Write a message followed by a newline.
     *
     * @param string $message
     * @return void
     */
    public function writeln( string $message ): void;

    /**
     * Write an error line to STDERR. Always prints, regardless of the
     * configured verbosity level.
     *
     * @param string $message
     * @return void
     */
    public function error( string $message ): void;

    /**
     * Write an informational line.
     *
     * @param string $message
     * @return void
     */
    public function info( string $message ): void;

    /**
     * Write a success line.
     *
     * @param string $message
     * @return void
     */
    public function success( string $message ): void;

    /**
     * Write a warning line.
     *
     * @param string $message
     * @return void
     */
    public function warning( string $message ): void;

    /**
     * Print one or more blank lines.
     *
     * @param int $count
     * @return void
     */
    public function newline( int $count = 1 ): void;

    /**
     * Print an auto-aligned table.
     *
     * @param string[] $headers Column headers.
     * @param array[]  $rows    Rows — each row is an indexed array of cell values.
     * @return void
     */
    public function table( array $headers, array $rows ): void;

    /**
     * Start a progress bar.
     *
     * @param int    $total The total number of steps.
     * @param string $label Optional label shown before the bar.
     * @param int    $width Bar width in characters.
     * @return void
     */
    public function progress_start( int $total, string $label = '', int $width = 60 ): void;

    /**
     * Advance the progress bar by one or more steps.
     *
     * @param int $step
     * @return void
     */
    public function progress_advance( int $step = 1 ): void;

    /**
     * Update the progress bar's label without advancing it.
     *
     * @param string $label
     * @return void
     */
    public function progress_update_label( string $label ): void;

    /**
     * Complete the progress bar and move to the next line.
     *
     * @param string $final_label Optional label to show on completion.
     * @return void
     */
    public function progress_finish( string $final_label = '' ): void;

    /**
     * Set the current verbosity level.
     *
     * @param int $level One of the VERBOSITY_* constants.
     * @return void
     */
    public function set_verbosity( int $level ): void;

    /**
     * Get the current verbosity level.
     *
     * @return int
     */
    public function get_verbosity(): int;
}