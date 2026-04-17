<?php
/**
 * Runner interface file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Runners;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Defines the contract every environment-specific command runner must implement.
 *
 * A runner bridges the CommandRegistry and the host environment. It is
 * responsible for resolving commands from the registry and wiring them
 * into whatever dispatch mechanism the environment provides.
 */
interface RunnerInterface {

    /**
     * Register all commands from the registry with the host environment.
     *
     * For plain PHP CLI this runs the dispatch loop immediately.
     * For WP-CLI this registers each command with WP_CLI::add_command().
     * For Laravel this registers each command as an Artisan command.
     *
     * @return void
     */
    public function register(): void;
}