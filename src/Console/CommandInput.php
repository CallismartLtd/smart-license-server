<?php
/**
 * Command input value object file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

/**
 * Typed, parsed result of a command invocation's $argv tail — produced
 * by OptionParser, consumed by CommandInterface::execute().
 *
 * Carries two distinct shapes deliberately kept apart:
 *
 *   - Arguments — positional values (e.g. `smliser license revoke 42`,
 *     where "42" is argument 0, or a named positional like "id" once
 *     OptionParser's definition supports naming them).
 *   - Options   — named flags/values (e.g. `--force`, `--type=premium`).
 *
 * This is the direct fix for the "inconsistent option value shapes"
 * issue found while reviewing OptionParser — every command reads
 * options and arguments through the same two accessor methods instead
 * of each hand-rolling its own `$args['foo'] ?? null` / `in_array`
 * checks against a raw array.
 */
final class CommandInput {

    /**
     * @param array<int|string, mixed> $arguments Positional arguments,
     *                                             indexed numerically
     *                                             unless/until OptionParser::class
     *                                             assigns them names.
     * @param array<string, mixed>     $options   Named options keyed by
     *                                             their long name (without
     *                                             the leading "--").
     */
    public function __construct(
        private array $arguments = [],
        private array $options = []
    ) {}

    /*
    |------------
    | ARGUMENTS
    |------------
    */

    /**
     * Get a positional argument by index or name.
     *
     * @param int|string $key
     * @param mixed      $default
     * @return mixed
     */
    public function get_argument( $key, $default = null ) {
        return $this->arguments[ $key ] ?? $default;
    }

    /**
     * Whether a positional argument was supplied.
     *
     * @param int|string $key
     * @return bool
     */
    public function has_argument( $key ): bool {
        return array_key_exists( $key, $this->arguments );
    }

    /**
     * All positional arguments as supplied.
     *
     * @return array<int|string, mixed>
     */
    public function get_arguments(): array {
        return $this->arguments;
    }

    /*
    |-----------
    | OPTIONS
    |-----------
    */

    /**
     * Get a named option's value.
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function get_option( string $name, $default = null ) {
        return $this->options[ $name ] ?? $default;
    }

    /**
     * Whether a named option was supplied at all (present, regardless
     * of value — distinguishes an explicit `--flag=` empty value from
     * the flag being absent, which get_option()'s default cannot).
     *
     * @param string $name
     * @return bool
     */
    public function has_option( string $name ): bool {
        return array_key_exists( $name, $this->options );
    }

    /**
     * All named options as supplied.
     *
     * @return array<string, mixed>
     */
    public function get_options(): array {
        return $this->options;
    }
}