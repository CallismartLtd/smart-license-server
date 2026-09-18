<?php
/**
 * Abstract command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\Contracts\CommandInterface;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Base class for leaf commands.
 *
 * A command with subcommands overrides get_subcommands() and can
 * leave run() unimplemented in the sense of it never being called —
 * caller only calls run() when no subcommand was supplied.
 * Whether that should instead print available subcommands rather than
 * silently doing nothing is a per-command decision; this base class
 * doesn't impose one.
 */
abstract class AbstractCommand implements CommandInterface {
    /**
     * Timer
     */
    protected Stopwatch $timer;

    /**
     * @param InputInterface  $io
     * @param OutputInterface $output
     */
    public function __construct(
        protected InputInterface $io,
        protected OutputInterface $output,
        protected ScriptName $script_name
    ) {}

    /**
     * {@inheritdoc}
     *
     * Default: no options/arguments beyond what the command's own
     * run()/subcommand handlers read positionally.
     */
    public function definition(): array {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * Default: no subcommands — override in commands that have them.
     */
    public function get_subcommands(): array {
        return [];
    }

    /**
     * Handle help subcommand.
     * 
     * @return int
     */
    public function handle_help() : int {
        $this->output->info( $this->description() );
        $this->output->newline();
        $this->output->info( 'Usage:' );
        $this->output->writeln( $this->synopsis() );
        $this->output->writeln( $this->help() );
        
        $this->output->newline();

        return 0;
    }

    /*
    |-----------------------
    | PRIVATE HELPERS
    |-----------------------
    */
    /**
     * Start the timer.
     */
    protected function start_timer() : static {
        if ( ! isset( $this->timer ) ) {
            $this->timer = new Stopwatch();
        }

        $this->timer->start();

        return $this;
    }

    /**
     * Stop the timer and return time elapsed.
     */
    protected function stop_timer() : float {
        if ( ! isset( $this->timer ) ) {
            $this->timer = new Stopwatch();
        }
        
        $elaped = $this->timer->elapsed();
        $this->timer->reset();
        return $elaped;
    }
}