<?php
/**
 * WP-CLI runner class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Runners;

use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\CommandInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * WP-CLI runner.
 *
 * Registers all commands from the registry with WP-CLI using the
 * 'smliser' top-level command group:
 *
 *   wp smliser work
 *   wp smliser work:schedule
 *   wp smliser migrate
 *
 * Only active when WP-CLI is loaded (WP_CLI constant is defined and true).
 */
class WPCLIRunner implements RunnerInterface {

    /*
    |----------------------
    | CONSTANTS
    |----------------------
    */

    /**
     * WP-CLI top-level command group.
     *
     * All commands are registered under this namespace so they
     * appear as `wp smliser <command>` on the CLI.
     *
     * @var string
     */
    const WP_CLI_NAMESPACE = 'smliser';

    /*
    |----------------------
    | DEPENDENCIES
    |----------------------
    */

    /**
     * The command registry.
     *
     * @var CommandRegistry
     */
    private CommandRegistry $registry;

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * @param CommandRegistry $registry The command registry.
     */
    public function __construct( CommandRegistry $registry ) {
        $this->registry = $registry;
    }

    /*
    |----------------------
    | RunnerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Iterates the registry and registers each command with WP-CLI
     * under the 'smliser' namespace. Each command becomes:
     *   wp smliser <command-name>
     *
     * Does nothing if WP-CLI is not loaded.
     */
    public function register(): void {
        if ( ! $this->is_wpcli() ) {
            return;
        }

        foreach ( $this->registry->all() as $name => $class ) {
            $this->add_command( $name, $class );
        }
    }

    /*
    |----------------------
    | HELPERS
    |----------------------
    */

    /**
     * Register a single command with WP-CLI.
     *
     * Wraps the CommandInterface::execute() call inside a WP-CLI
     * compatible callback that receives WP-CLI's $args and $assoc_args.
     *
     * @param string                         $name  The command name.
     * @param class-string<CommandInterface> $class The command class.
     * @return void
     */
    private function add_command( string $name, string $class ): void {
        $synopsis = [
            'shortdesc' => $class::description(),
        ];

        \WP_CLI::add_command(
            static::WP_CLI_NAMESPACE . ' ' . $name,
            function( array $args, array $assoc_args ) use ( $class ) {
                ( new $class() )->execute( array_merge( $args, $assoc_args ) );
            },
            $synopsis
        );
    }

    /**
     * Whether WP-CLI is the current runtime.
     *
     * @return bool
     */
    private function is_wpcli(): bool {
        return defined( 'WP_CLI' ) && WP_CLI;
    }
}