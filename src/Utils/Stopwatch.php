<?php
/**
 * Stopwatch class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Utils;

/**
 * A minimal elapsed-time helper for commands that want to report how
 * long an operation took (e.g. "Completed in 2.341s.").
 *
 * Deliberately not part of OutputInterface — timing is a distinct
 * concern from writing output, and not every command needs it. A
 * command that wants elapsed-time reporting constructs its own
 * instance rather than the abstraction carrying timer state most
 * commands never touch.
 */
class Stopwatch {

    /**
     * @var float|null
     */
    private ?float $started_at = null;

    /**
     * Mark the start of a timed operation.
     *
     * @return void
     */
    public function start(): void {
        $this->started_at = microtime( true );
    }

    /**
     * Elapsed seconds since start(), rounded to milliseconds.
     *
     * Returns 0.0 if start() has not been called.
     *
     * @return float
     */
    public function elapsed(): float {
        if ( null === $this->started_at ) {
            return 0.0;
        }

        return round( microtime( true ) - $this->started_at, 3 );
    }

    /**
     * Reset timer.
     * 
     * @return void
     */
    public function reset() : void {
        $this->started_at = null;
    }
}