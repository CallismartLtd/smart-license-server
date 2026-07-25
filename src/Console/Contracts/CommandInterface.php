<?php
/**
 * Command interface file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Contracts
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Contracts;

use SmartLicenseServer\Console\CommandInput;

/**
 * Contract for a single leaf command (e.g. `smliser license revoke`).
 *
 * Implementations receive their parsed CommandInput via execute() and
 * their InputInterface/OutputInterface collaborators via constructor —
 * see AbstractCommand for the base class that wires that up so
 * individual commands don't repeat the constructor boilerplate.
 */
interface CommandInterface {

    /**
     * The command's registered name (the token typed after `smliser`,
     * or after its noun for two-level commands — e.g. "revoke" for
     * `smliser license revoke`).
     *
     * @return string
     */
    public static function name(): string;

    /**
     * One-line usage synopsis shown in per-command help.
     *
     * @return string
     */
    public static function synopsis(): string;

    /**
     * One-line description shown in the global command listing.
     *
     * @return string
     */
    public static function description(): string;

    /**
     * Extended help body shown in per-command help. Return an empty
     * string if there's nothing beyond the synopsis/description worth
     * showing.
     *
     * @return string
     */
    public static function help(): string;

    /**
     * The command's option/argument definition, consumed by an
     * OptionParserInterface implementation to produce this command's
     * CommandInput. Return an empty array for a command that takes no
     * arguments or options.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array;

    /**
     * Get subcommands and there handlers.
     * 
     * @return array<string, callable(CommandInput):int> Array of subcommand name 
     * and the handler, The handler may accept CommandInput and must return integer value.
     * Return an empty array for command that do not implement subcommands.
     */
    public function get_subcommands() : array;

    /**
     * Run the command.
     *
     * @param CommandInput $input The parsed arguments/options this
     *                            command was invoked with.
     * @return int Process exit code — 0 for success, non-zero for failure.
     */
    public function run( CommandInput $input ): int;
}