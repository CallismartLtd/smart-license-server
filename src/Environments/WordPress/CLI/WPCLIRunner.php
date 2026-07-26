<?php
/**
 * WP-CLI runner class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\WordPress\CLI;

use SmartLicenseServer\Console\AbstractCommandRouter;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\OptionParser;
use SmartLicenseServer\Console\Runners\RunnerInterface;
use SmartLicenseServer\Core\DotEnv;
use SmartLicenseServer\Environments\CLI\CLIIdentityProvider;
use WP_CLI;

/**
 * WP-CLI runner.
 *
 * Registers a single top-level `smliser` command with WP-CLI and
 * self-manages dispatch from there — WP-CLI is a transport for argv-
 * shaped data, not a second parser. This keeps every invocation, from
 * `bin/smliser`, the interactive shell, or `wp smliser ...`, running
 * through the exact same OptionParser -> CommandInput ->
 * CommandInterface::execute() pipeline.
 *
 *   wp smliser license revoke 42 --force
 *   wp smliser help
 *   wp smliser help license
 *
 * ## Known WP-CLI entry-point limitations
 *
 * WP-CLI's own free-form arg splitting runs before this class ever
 * sees anything, so two of OptionParser's supported syntaxes cannot
 * be recovered through this entry point:
 *
 *   - Space-separated "--key value" is not valid WP-CLI syntax at
 *     all — only "--key=value" and bare "--flag" reach $assoc_args.
 *   - A repeated flag (`--role=admin --role=editor`) has already been
 *     collapsed to its last value by the time $assoc_args arrives —
 *     WP-CLI's own splitter does not collect duplicates into arrays.
 *
 * Both are inherent to invoking through `wp`, not bugs in this class.
 *
 * ## Environment file resolution
 *
 * The constructor resolves the correct .env file from ABSPATH based
 * on the WordPress environment type returned by wp_get_environment_type():
 *
 *   production  → .env
 *   staging     → .env.staging
 *   development → .env.development
 *   local       → .env.local
 *
 * If the environment-specific file does not exist, it falls back to .env.
 *
 * ## Authentication
 *
 * Authentication via SMLISER_CLI_API_KEY is attempted once in init(),
 * before the command is registered. If successful, Guard holds the
 * principal for the lifetime of the process and all write commands
 * are available. If not, Guard has no principal and each command
 * handles the absence in its own context — read operations work,
 * write operations print a clear error.
 *
 * Only active when WP-CLI is loaded (WP_CLI constant is defined and true).
 */
class WPCLIRunner extends AbstractCommandRouter implements RunnerInterface {

    /*
    |----------------------
    | CONSTANTS
    |----------------------
    */

    /**
     * WP-CLI top-level command group.
     *
     * @var string
     */
    const SMLISER_CLI_NAMESPACE = 'smliser';

    /**
     * Map of WordPress environment types to .env filenames.
     *
     * @var array<string, string>
     */
    const ENV_FILE_MAP = [
        'production'  => '.env',
        'staging'     => '.env.staging',
        'development' => '.env.development',
        'local'       => '.env.local',
    ];

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * @param CommandRegistry $registry
     * @param InputInterface  $io
     * @param OutputInterface $output
     * @param OptionParser    $parser Shared with CLIRunner/InteractiveShell —
     *                                the same instance/class either way, so
     *                                option normalization behaves identically
     *                                regardless of entry point.
     */
    public function __construct(
        CommandRegistry $registry,
        InputInterface $io,
        OutputInterface $output,
        private OptionParser $parser = new OptionParser()
    ) {
        parent::__construct( $registry, $io, $output );
    }

    /*
    |----------------------
    | RunnerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Authenticates once via CLIIdentityProvider, then registers a
     * single top-level command with WP-CLI. Does nothing if WP-CLI is
     * not loaded.
     */
    public function init(): int {
        if ( ! $this->is_wpcli() ) {
            return 1;
        }

        $this->load_env();

        // Authenticate once — sets Guard::$principal if successful.
        // Silent on failure — commands handle missing auth contextually.
        ( new CLIIdentityProvider() )->authenticate();

        // Registered exactly once — not per-command. WP-CLI dispatches
        // every `wp smliser ...` invocation to exec_command(), which
        // resolves the actual subcommand itself via the registry.
        WP_CLI::add_command(
            static::SMLISER_CLI_NAMESPACE,
            [ $this, 'exec_command' ],
            [ 'before_invoke' => null ]
        );

        return 0;
    }

    /*
    |----------------------
    | DISPATCH
    |----------------------
    */

    /**
     * Manage Smart License Server from the command-line.
     *
     * Bare `wp smliser` or `wp smliser help [command]` print the same
     * listing CLIRunner prints for a bare invocation. Anything else
     * resolves the first token against the command registry and
     * dispatches through the shared OptionParser -> CommandInput ->
     * CommandInterface::execute() pipeline.
     *
     * ## EXAMPLES
     *
     *     wp smliser app list --limit=20 --force
     *     wp smliser help cache clear
     *
     * @param array<int, string>    $args       Positional tokens after `smliser`.
     * @param array<string, mixed>  $assoc_args Named flags, as split by WP-CLI.
     * @return void
     */
    public function exec_command( array $args, array $assoc_args ): void {
        
        if ( empty( $args ) || 'help' === $args[0] ) {
            $target = $args[1] ?? null;

            if ( $target && $this->registry->has( $target ) ) {
                $this->print_command_help( $this->registry->get( $target ) );
            } else {
                $this->print_contextual_help();
            }

            return;
        }

        [ $command, $subcommand, $args ] = $this->split_invocation( $args );

        $parsed  = $this->parser->parse( $this->to_raw_tokens( $args, $assoc_args ) );

        $input   = new CommandInput( $parsed['arguments'], $parsed['options'] );
        $exit    = $this->route_command( $input, $command, $subcommand );

        if ( 0 !== $exit ) {
            WP_CLI::halt( $exit );
        }
    }

    /*
    |----------------------
    | PRIVATE HELPERS
    |----------------------
    */

    /**
     * Reconstruct raw argv-style tokens from WP-CLI's pre-split arrays
     * so they can be run through the same OptionParser used by
     * CLIRunner and InteractiveShell — one normalization
     * implementation, not a second one reimplemented for WP-CLI.
     *
     * Lossy at the edges described in the class docblock: a repeated
     * flag has already been collapsed to its last value by the time
     * $assoc_args reaches this method, and space-separated
     * "--key value" was never valid syntax through this entry point.
     *
     * @param array<int, string>   $positional_args WP-CLI's $args.
     * @param array<string, mixed> $assoc_args      WP-CLI's $assoc_args.
     * @return array<int, string> Raw tokens, ready for OptionParser::parse().
     */
    private function to_raw_tokens( array $positional_args, array $assoc_args ): array {
        $tokens = $positional_args;

        foreach ( $assoc_args as $key => $value ) {
            if ( true === $value ) {
                $tokens[] = "--{$key}";
                continue;
            }

            $tokens[] = "--{$key}={$value}";
        }

        return $tokens;
    }

    /**
     * Load the correct .env file for the current WordPress environment.
     *
     * Resolution order:
     *   1. Environment-specific file (e.g. .env.development)
     *   2. Base .env fallback
     *
     * @return void
     */
    private function load_env(): void {
        $env_type = function_exists( 'wp_get_environment_type' )
            ? wp_get_environment_type()
            : 'production';

        $env_file = static::ENV_FILE_MAP[ $env_type ] ?? '.env';

        ( new DotEnv( SMLISER_ROOT ) )->load( $env_file );
    }

    /**
     * Whether WP-CLI is the current runtime.
     *
     * @return bool
     */
    private function is_wpcli(): bool {
        return defined( 'WP_CLI' ) && constant( 'WP_CLI' );
    }


    protected function print_contextual_help(): void {
        $this->print_global_help();
    }
}