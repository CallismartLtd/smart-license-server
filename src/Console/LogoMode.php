<?php
/**
 * Logo selection policy for the interactive shell banner.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.3.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

/**
 * How {@see InteractiveShell} picks which ASCII logo (if any) to print
 * in the welcome banner.
 *
 * `AUTO` preserves the historical behavior of deriving the logo from
 * the output verbosity level. Every other case is an explicit
 * operator/config override that takes priority over verbosity.
 */
enum LogoMode: string {

    /** Derive the logo from the current output verbosity (default, previous behavior). */
    case AUTO        = 'auto';

    /** Always print the large logo, regardless of verbosity. */
    case LARGE       = 'large';

    /** Always print the compact monospaced logo, regardless of verbosity. */
    case MONOSPACED  = 'monospaced';

    /** Never print a logo. */
    case NONE        = 'none';

    /**
     * Resolve a mode from a loose string, e.g. a CLI flag value or an
     * environment variable — `--logo=large`, `--logo=none`,
     * `SMLISER_CLI_LOGO=none`. Unrecognized/empty values fall back to
     * AUTO rather than throwing, since a malformed override shouldn't
     * crash the shell over something as trivial as a banner.
     *
     * @param string|null $value
     * @return self
     */
    public static function from_loose_string( ?string $value ): self {
        if ( null === $value || '' === $value ) {
            return self::AUTO;
        }

        return self::tryFrom( strtolower( trim( $value ) ) ) ?? self::AUTO;
    }

    /**
     * Convenience constructor that checks the `SMLISER_CLI_LOGO`
     * environment variable. Lets operators suppress or pin the logo
     * globally (e.g. in CI, or a `--no-logo`-style wrapper script)
     * without every caller having to thread a CLI flag through.
     *
     * @return self
     */
    public static function from_env(): self {
        $value = getenv( 'SMLISER_CLI_LOGO' );

        return self::from_loose_string( false === $value ? null : $value );
    }
}