<?php
/**
 * Command interface file.
 *
 * Defines the contract every console command must implement.
 * Commands are environment-agnostic — they know nothing about
 * WP-CLI, Artisan, or plain PHP CLI. The runner bridges the gap.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Console command contract.
 */
interface CommandInterface {

    /**
     * The command name as typed on the CLI.
     *
     * Use colon notation for namespaced commands.
     *
     * Examples: 'work', 'work:schedule', 'migrate', 'install:roles'
     *
     * @return string
     */
    public static function name(): string;

    /**
     * A short human-readable description shown in help output.
     *
     * @return string
     */
    public static function description(): string;

    /**
     * Execute the command.
     *
     * Receives the raw argument list from the runner. The runner
     * strips the command name itself before passing args so the
     * command only sees its own options and positional arguments.
     *
     * @param array<int|string, mixed> $args Arguments passed to the command.
     * @return void
     */
    public function execute( array $args = [] ): void;
}