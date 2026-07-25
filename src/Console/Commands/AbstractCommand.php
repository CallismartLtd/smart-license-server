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
     * @param InputInterface  $io
     * @param OutputInterface $output
     */
    public function __construct(
        protected InputInterface $io,
        protected OutputInterface $output
    ) {}

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
}