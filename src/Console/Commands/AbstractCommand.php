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

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\CommandInterface;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Base class for leaf commands — the constructor shape
 * AbstractCommandRouter::route_command() instantiates every command
 * with (`new $class( $this->io, $this->output )`), plus defaults for
 * definition()/get_subcommands() so a simple command with neither
 * doesn't have to declare empty-array boilerplate.
 *
 * A command with subcommands overrides get_subcommands() and can
 * leave run() unimplemented in the sense of it never being called —
 * route_command() only calls run() when no subcommand was supplied.
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
        protected string $script_name
    ) {}

    /**
	 * Default factory implementation.
	 *
	 * @param InputInterface  $io
	 * @param OutputInterface $output
	 * @param string          $script_name
	 * @return static
	 */
	public static function make(
		InputInterface $io,
		OutputInterface $output,
		string $script_name
	): static {
		return new static( $io, $output, $script_name );
	}

    /**
     * {@inheritdoc}
     *
     * Default: no options/arguments beyond what the command's own
     * run()/subcommand handlers read positionally.
     */
    public static function definition(): array {
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
     * @param CommandInput $input
     * @return int
     */
    public function handle_help( CommandInput $input ) : int {
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