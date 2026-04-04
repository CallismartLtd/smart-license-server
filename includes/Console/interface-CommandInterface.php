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
     * Examples: 'work', 'work:schedule', 'migrate', 'app', 'cache'
     *
     * @return string
     */
    public static function name(): string;

    /**
     * A short one-line description shown in the main command listing.
     *
     * Keep this to a single sentence — it appears in the aligned
     * command table printed by `smliser help`.
     *
     * @return string
     */
    public static function description(): string;

    /**
     * A usage synopsis shown at the top of per-command help output.
     *
     * Should describe the full call signature including subcommands
     * and positional arguments. Printed by `smliser help <command>`
     * and `smliser <command> --help`.
     *
     * Examples:
     *   'smliser cache <subcommand> [key]'
     *   'smliser app <subcommand> <slug> <type> [options]'
     *   'smliser work'
     *
     * @return string
     */
    public static function synopsis(): string;

    /**
     * Detailed help text shown when the user requests per-command help.
     *
     * Printed below the synopsis by `smliser help <command>` and
     * `smliser <command> --help`. Should document all subcommands,
     * positional arguments, flags, and examples.
     *
     * Return an empty string for simple commands that have no
     * subcommands or options beyond what the synopsis covers.
     *
     * @return string
     */
    public static function help(): string;

    /**
     * Execute the command.
     *
     * Receives the raw argument list from the runner. The runner
     * strips the script name and command name before passing args,
     * so the command only sees its own subcommands, positional
     * arguments, and flags.
     *
     * @param array<int|string, mixed> $args Arguments passed to the command.
     * @return void
     */
    public function execute( array $args = [] ): void;
}