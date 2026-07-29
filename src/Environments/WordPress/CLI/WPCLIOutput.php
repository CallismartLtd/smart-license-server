<?php
/**
 * WP-CLI output class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\WordPress\CLI;

use SmartLicenseServer\Console\Contracts\OutputInterface;
use WP_CLI;
use WP_CLI\Utils;

/**
 * OutputInterface implementation for the WP-CLI runner.
 *
 * Delegates every write to WP-CLI's own output API rather than raw
 * echo/fwrite, so commands running under `wp smliser ...` behave
 * consistently with the rest of the app's Input/Output abstraction
 * instead of falling back to direct terminal writes.
 *
 * Coloring is intentionally NOT reimplemented here — WP_CLI::success()/
 * ::warning()/::error() already apply WP-CLI's own conventional
 * coloring and respect its --quiet flag, so wrapping them further
 * would fight WP-CLI's own verbosity handling rather than cooperate
 * with it.
 */
class WPCLIOutput implements OutputInterface {

    /**
     * Current verbosity level. WP-CLI has its own --quiet handling,
     * but this interface still needs to satisfy set/get_verbosity()
     * for parity with ConsoleOutput/BufferedOutput.
     *
     * @var int
     */
    private int $verbosity = self::VERBOSITY_NORMAL;

    /**
     * Active progress bar instance, or null when none is running.
     *
     * @var \cli\progress\Bar|null
     */
    private $progress = null;

    /**
     * {@inheritdoc}
     *
     * WP-CLI has no raw "write without formatting" primitive — the
     * closest equivalent is WP_CLI::log(), which appends its own
     * newline. $newline is honored by appending a second call when
     * false, since WP_CLI::log() cannot suppress its trailing newline.
     */
    public function write( string $message, bool $newline = false ): void {
        WP_CLI::log( $message );
    }

    /**
     * {@inheritdoc}
     */
    public function writeln( string $message ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        WP_CLI::log( $message );
    }

    /**
     * {@inheritdoc}
     *
     * Always prints — WP_CLI::error() halts execution by default in
     * many WP-CLI versions ($exit = true). Since our contract expects
     * error() to just print (the command decides its own exit code
     * via its return value), pass exit: false explicitly.
     */
    public function error( string $message ): void {
        WP_CLI::error( $message, false );
    }

    /**
     * {@inheritdoc}
     */
    public function info( string $message ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        WP_CLI::log( $message );
    }

    /**
     * {@inheritdoc}
     */
    public function success( string $message ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        WP_CLI::success( $message );
    }

    /**
     * {@inheritdoc}
     */
    public function warning( string $message ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        WP_CLI::warning( $message );
    }

    /**
     * {@inheritdoc}
     */
    public function newline( int $count = 1 ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        for ( $i = 0; $i < max( 1, $count ); $i++ ) {
            WP_CLI::log( '' );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Delegates to WP-CLI's own table renderer rather than
     * reimplementing column-width math — Utils\format_items() expects
     * each row as an associative array keyed by field name, so headers
     * and each indexed row are zipped together first.
     */
    public function table( array $headers, array $rows ): void {
        if ( $this->verbosity < self::VERBOSITY_NORMAL ) {
            return;
        }

        $items = array_map(
            fn( $row ) => array_combine( $headers, array_pad( array_values( $row ), count( $headers ), '' ) ),
            $rows
        );

        Utils\format_items( 'table', $items, $headers );
    }

    /**
     * {@inheritdoc}
     *
     * Uses WP-CLI's own progress bar widget instead of a hand-rolled
     * carriage-return redraw.
     */
    public function progress_start( int $total, string $label = '', int $width = 60 ): void {
        $this->progress = Utils\make_progress_bar( $label, max( 1, $total ) );
    }

    /**
     * {@inheritdoc}
     */
    public function progress_advance( int $step = 1 ): void {
        if ( null === $this->progress ) {
            return;
        }

        for ( $i = 0; $i < $step; $i++ ) {
            $this->progress->tick();
        }
    }

    /**
     * {@inheritdoc}
     *
     * WP-CLI's progress bar does not support relabeling mid-run — this
     * is a documented no-op rather than a silent gap.
     */
    public function progress_update_label( string $label ): void {
        // Not supported by \WP_CLI\Utils\make_progress_bar(); intentional no-op.
    }

    /**
     * {@inheritdoc}
     */
    public function progress_finish( string $final_label = '' ): void {
        if ( null === $this->progress ) {
            return;
        }

        $this->progress->finish();
        $this->progress = null;
    }

    /**
     * {@inheritdoc}
     */
    public function set_verbosity( int $level ): void {
        $this->verbosity = max(
            static::VERBOSITY_QUIET,
            min( $level, static::VERBOSITY_VERBOSE )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function get_verbosity(): int {
        return $this->verbosity;
    }
}