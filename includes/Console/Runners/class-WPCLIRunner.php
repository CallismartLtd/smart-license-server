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

use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Console\CommandRegistry;
use SmartLicenseServer\Core\DotEnv;
use SmartLicenseServer\Environments\CLI\CLIIdentityProvider;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * WP-CLI runner.
 *
 * Registers all commands from the registry with WP-CLI under the
 * 'smliser' top-level command group:
 *
 *   wp smliser work
 *   wp smliser work:schedule
 *   wp smliser app save my-plugin plugin --path=/tmp/plugin.zip
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
 * Authentication via SMLISER_CLI_API_KEY is attempted once in register(),
 * before any command callbacks are registered. If successful, Guard holds
 * the principal for the lifetime of the process and all write commands
 * are available. If not, Guard has no principal and each command handles
 * the absence in its own context — read operations work, write operations
 * print a clear error.
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
     * @var string
     */
    const WP_CLI_NAMESPACE = 'smliser';

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
     * Load the environment-specific .env file and store the registry.
     *
     * The .env file is resolved from ABSPATH using the WordPress environment
     * type. Falls back to .env when the environment-specific file is missing.
     *
     * @param CommandRegistry $registry The command registry.
     */
    public function __construct( CommandRegistry $registry ) {
        $this->registry = $registry;
        $this->load_env();
    }

    /*
    |----------------------
    | RunnerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * Authenticates once via CLIIdentityProvider, then registers all
     * commands with WP-CLI. Does nothing if WP-CLI is not loaded.
     */
    public function register(): void {
        if ( ! $this->is_wpcli() ) {
            return;
        }

        // Authenticate once — sets Guard::$principal if successful.
        // Silent on failure — commands handle missing auth contextually.
        ( new CLIIdentityProvider() )->authenticate();

        foreach ( $this->registry->all() as $name => $class ) {
            $this->add_command( $name, $class );
        }
    }

    /*
    |----------------------
    | PRIVATE HELPERS
    |----------------------
    */

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

        $env_file = self::ENV_FILE_MAP[ $env_type ] ?? '.env';
        $dotenv   = new DotEnv( ABSPATH );

        // Load environment-specific file if it exists, otherwise fall back.
        if ( $env_file !== '.env' && file_exists( SMLISER_ABSPATH . $env_file ) ) {
            $dotenv->load( $env_file );
        } else {
            $dotenv->load( '.env' );
        }
    }

    /**
     * Register a single command with WP-CLI.
     *
     * Exposes synopsis, shortdesc, and longdesc so `wp smliser <command> --help`
     * shows full documentation consistent with `smliser help <command>`.
     *
     * @param string                         $name  The command name.
     * @param class-string<CommandInterface> $class The command class.
     * @return void
     */
    private function add_command( string $name, string $class ): void {
        \WP_CLI::add_command(
            static::WP_CLI_NAMESPACE . ' ' . $name,
            function( array $args, array $assoc_args ) use ( $class ) {
                ( new $class() )->execute( array_merge( $args, $assoc_args ) );
            },
            [
                'shortdesc' => $class::description(),
                'longdesc'  => $class::help(),

                // No 'synopsis' key — WP-CLI's structured synopsis format is
                // incompatible with our free-form subcommand routing. Omitting
                // it disables WP-CLI's argument validation entirely and lets
                // our commands handle all parsing internally via execute().
            ]
        );
    }

    /**
     * Whether WP-CLI is the current runtime.
     *
     * @return bool
     */
    private function is_wpcli(): bool {
        return defined( 'WP_CLI' ) && \WP_CLI;
    }
}