<?php
/**
 * WP-CLI input class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\WordPress\CLI;

use SmartLicenseServer\Console\Contracts\InputInterface;

/**
 * InputInterface implementation for the WP-CLI runner.
 *
 * WP-CLI has no general-purpose interactive prompt API — its own
 * convention (WP_CLI::confirm()) is designed to halt the whole process
 * on "no" rather than let the caller branch on a bool, and there is
 * no built-in equivalent of read_line()/choice()/secret() at all. Two
 * deliberate departures from a hypothetical "full parity" implementation:
 *
 *  - confirm() checks for a `--yes` flag (the conventional WP-CLI
 *    escape hatch for scripting) before falling back to a plain STDIN
 *    read, rather than delegating to WP_CLI::confirm() and inheriting
 *    its halt-on-no behavior — that would break the contract every
 *    other InputInterface implementation honors (a bool the caller
 *    decides what to do with).
 *  - read_line()/choice()/secret() fall back to plain STDIN reads with
 *    no hidden-input support. WP-CLI commands are conventionally
 *    expected to be scriptable/non-interactive; if a command genuinely
 *    needs secret() under `wp smliser`, document that the operator
 *    should be prompted for it outside of WP-CLI (e.g. via an env var
 *    or a separate `bin/smliser` invocation) rather than relying on
 *    hidden input working identically here.
 */
class WPCLIInput implements InputInterface {

    /**
     * The current invocation's assoc_args, used by confirm() to check
     * for --yes. Set by WPCLIRunner immediately before each
     * command's execute() call.
     *
     * @var array<string, mixed>
     */
    private array $assoc_args = [];

    /**
     * Set the current invocation's assoc_args.
     *
     * Must be called by the runner before constructing/executing a
     * command, since WP-CLI hands assoc_args to the callback rather
     * than making them available statically.
     *
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function set_assoc_args( array $assoc_args ): void {
        $this->assoc_args = $assoc_args;
    }

    /**
     * {@inheritdoc}
     */
    public function read_line( string $prompt = '' ): ?string {
        if ( '' !== $prompt ) {
            fwrite( STDOUT, $prompt );
        }

        $line = fgets( STDIN );

        return false === $line ? null : trim( $line );
    }

    /**
     * {@inheritdoc}
     */
    public function prompt( string $question, string $default = '' ): string {
        $prompt = '' !== $default
            ? sprintf( '%s [%s] ', $question, $default )
            : $question . ' ';

        $line = $this->read_line( $prompt ) ?? '';

        return '' !== $line ? $line : $default;
    }

    /**
     * {@inheritdoc}
     *
     * Honors --yes as WP-CLI users conventionally expect, without
     * inheriting WP_CLI::confirm()'s process-halting behavior on "no".
     */
    public function confirm( string $question, bool $default = false ): bool {
        if ( ! empty( $this->assoc_args['yes'] ) ) {
            return true;
        }

        $hint  = $default ? '[Y/n]' : '[y/N]';
        $input = strtolower( $this->read_line( sprintf( '%s %s: ', $question, $hint ) ) ?? '' );

        if ( '' === $input ) {
            return $default;
        }

        return in_array( $input, [ 'y', 'yes' ], true );
    }

    /**
     * {@inheritdoc}
     */
    public function choice( string $question, array $choices, $default = null ) {
        foreach ( $choices as $key => $label ) {
            fwrite( STDOUT, sprintf( '  [%s] %s' . PHP_EOL, $key, $label ) );
        }

        $answer = $this->read_line( $question . ': ' ) ?? '';

        return $choices[ $answer ] ?? $default;
    }

    /**
     * {@inheritdoc}
     *
     * No hidden-input support under WP-CLI — see class docblock. This
     * is a visible fallback, not a silent security gap: commands that
     * truly need secret() should avoid relying on it under this runner.
     */
    public function secret( string $prompt = '' ): string {
        return $this->read_line( $prompt ) ?? '';
    }
}