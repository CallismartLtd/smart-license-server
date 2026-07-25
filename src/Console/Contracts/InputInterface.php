<?php
/**
 * Console input interface file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Contracts
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Contracts;

/**
 * Contract for reading interactive input from the operator during a
 * command's execution — prompts, confirmations, choices, and hidden
 * (password-style) input.
 *
 * This is distinct from CommandInput, which carries the parsed
 * command-line arguments/options a command was invoked with. This
 * interface covers input requested mid-execution, after the command
 * has already started running — a command asking "are you sure?"
 * partway through, not the flags it was launched with.
 */
interface InputInterface {

    /**
     * Print an optional prompt and read one line of freeform input.
     *
     * Returns null on EOF (Ctrl-D / Ctrl-Z / closed stream) so callers
     * that loop on input — the interactive shell, chiefly — can tell
     * "the operator pressed enter on an empty line" (empty string,
     * keep looping) apart from "the input stream ended" (null, stop).
     *
     * @param string $prompt Optional prompt text to display before reading.
     * @return string|null The trimmed input line, or null on EOF.
     */
    public function read_line( string $prompt = '' ): ?string;

    /**
     * Prompt the user for freeform input.
     *
     * @param string $question The question to display.
     * @param string $default  Default value if the user presses enter.
     * @return string The user's input or the default.
     */
    public function prompt( string $question, string $default = '' ): string;

    /**
     * Prompt the operator for a yes/no confirmation.
     *
     * @param string $question The question to display.
     * @param bool   $default  Answer used when the operator presses enter
     *                         with no input.
     * @return bool True for yes, false for no.
     */
    public function confirm( string $question, bool $default = false ): bool;

    /**
     * Prompt the operator to pick one of several labelled choices.
     *
     * @param string   $question The question to display.
     * @param string[] $choices  Choices keyed by the value the operator
     *                           types to select them (e.g. a numeric index).
     * @param mixed    $default  Value returned if the operator's answer
     *                           does not match any key in $choices.
     * @return mixed The chosen value, or $default.
     */
    public function choice( string $question, array $choices, $default = null );

    /**
     * Prompt for hidden input (passwords, secrets, tokens).
     *
     * Implementations should suppress terminal echo where the platform
     * allows it; where it does not (no TTY, no stty, no readline), they
     * should fall back to a visible read rather than fail outright.
     *
     * @param string $prompt Optional prompt text to display before reading.
     * @return string The entered value. Empty string on EOF — a secret
     *                prompt has no meaningful "keep looping" behavior,
     *                so unlike read_line() this does not need null.
     */
    public function secret( string $prompt = '' ): string;
}