<?php
/**
 * App command class file.
 *
 * Provides CLI subcommands for inspecting and managing hosted applications
 * across all three app types (plugin, theme, software).
 *
 * Read operations work without authentication — consistent with the REST API
 * which allows anonymous GET requests on the repository route.
 *
 * Write operations (status, trash, delete, purge) require an authenticated
 * principal on Guard. If no principal is set the command prints a clear
 * error and returns without calling any controller.
 *
 * Usage:
 *   smliser app list [--type=plugin|theme|software] [--status=active] [--page=1] [--limit=20]
 *   smliser app search <term> [--type=...] [--limit=20]
 *   smliser app get <slug> <type>
 *   smliser app count [--type=...] [--status=active]
 *   smliser app status <slug> <type> <status>
 *   smliser app trash <slug> <type>
 *   smliser app delete <slug> <type>
 *   smliser app purge <slug> <type>
 *   smliser app help
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CLIAwareTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Security\Context\Guard;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Manage hosted applications from the CLI.
 */
class AppCommand implements CommandInterface {
    use CLIAwareTrait;

    /*
    |--------------------------------------------
    | CommandInterface
    |--------------------------------------------
    */

    public static function name(): string {
        return 'app';
    }

    public static function description(): string {
        return 'Inspect and manage hosted applications.';
    }

    public static function synopsis(): string {
        return 'smliser app <subcommand> [arguments] [--options]';
    }

    public static function help(): string {
        return implode( PHP_EOL, [
            'Subcommands:',
            '  list                                    List hosted applications.',
            '  search <term>                           Search applications by name, slug, or author.',
            '  get <slug> <type>                       Show details for a specific application.',
            '  count                                   Count hosted applications.',
            '  status <slug> <type> <status>           Change application status.',
            '  trash <slug> <type>                     Move an application to trash.',
            '  delete <slug> <type>                    Move an application to trash (same as trash).',
            '  purge <slug> <type>                     Permanently delete an application.',
            '  help                                    Show this help message.',
            '',
            'Options for list / search / count:',
            '  --type=plugin|theme|software            Filter by app type. Repeatable.',
            '  --status=active|inactive|suspended      Filter by status. Default: active.',
            '  --page=<n>                              Page number. Default: 1.',
            '  --limit=<n>                             Items per page. Default: 20.',
            '',
            'Valid statuses for the status subcommand:',
            '  active, deactivated, suspended',
            '  (trash is handled by the trash/delete subcommand)',
            '',
            'Examples:',
            '  smliser app list',
            '  smliser app list --type=plugin --status=active',
            '  smliser app search "my plugin"',
            '  smliser app get my-plugin plugin',
            '  smliser app count --type=theme',
            '  smliser app status my-plugin plugin inactive',
            '  smliser app trash my-plugin plugin',
            '  smliser app purge my-plugin plugin',
        ] );
    }

    public function execute( array $args = [] ): void {
        $subcommand = $args[0] ?? null;

        match ( $subcommand ) {
            'list'    => $this->handle_list( array_slice( $args, 1 ) ),
            'search'  => $this->handle_search( array_slice( $args, 1 ) ),
            'get'     => $this->handle_get( $args[1] ?? null, $args[2] ?? null ),
            'count'   => $this->handle_count( array_slice( $args, 1 ) ),
            'status'  => $this->handle_status( $args[1] ?? null, $args[2] ?? null, $args[3] ?? null ),
            'trash'   => $this->handle_trash( $args[1] ?? null, $args[2] ?? null ),
            'delete'  => $this->handle_trash( $args[1] ?? null, $args[2] ?? null ),
            'purge'   => $this->handle_purge( $args[1] ?? null, $args[2] ?? null ),
            'help'    => $this->handle_help(),
            null      => $this->handle_default(),
            default   => $this->handle_unknown( $subcommand ),
        };
    }

    /*
    |--------------------------------------------
    | READ SUBCOMMANDS — no auth required
    |--------------------------------------------
    */

    /**
     * List hosted applications with optional filters.
     *
     * @param array $args Remaining args after 'list'.
     */
    private function handle_list( array $args ): void {
        $opts   = $this->parse_options( $args );
        $types  = isset( $opts['type'] ) ? (array) $opts['type'] : [ 'plugin', 'theme', 'software' ];
        $status = $opts['status'] ?? AbstractHostedApp::STATUS_ACTIVE;
        $page   = (int) ( $opts['page']  ?? 1 );
        $limit  = (int) ( $opts['limit'] ?? 20 );

        $this->start_timer();
        $this->info( sprintf( 'Fetching apps — type: %s, status: %s, page: %d', implode( ', ', $types ), $status, $page ) );

        $result = HostedApplicationService::get_apps( compact( 'page', 'limit', 'status', 'types' ) );

        $items = $result['items'] ?? [];
        $pagination = $result['pagination'] ?? [];

        if ( empty( $items ) ) {
            $this->warning( 'No applications found.' );
            return;
        }

        $rows = [];
        foreach ( $items as $app ) {
            $rows[] = [
                $app->get_id(),
                $app->get_name(),
                $app->get_slug(),
                $app->get_type(),
                $app->get_status(),
                $app->get_last_updated(),
            ];
        }

        $this->newline();
        $this->table(
            [ 'ID', 'Name', 'Slug', 'Type', 'Status', 'Updated' ],
            $rows
        );

        if ( ! empty( $pagination ) ) {
            $this->newline();
            $this->line( sprintf(
                'Page %d of %d  ·  %d total',
                $pagination['page'],
                $pagination['total_pages'],
                $pagination['total']
            ) );
        }

        $this->done( '', false );
    }

    /**
     * Search applications by term.
     *
     * @param array $args Remaining args after 'search'.
     */
    private function handle_search( array $args ): void {
        $term = $args[0] ?? null;

        if ( empty( $term ) ) {
            $this->error( 'Usage: smliser app search <term> [--type=...] [--limit=20]' );
            return;
        }

        $opts   = $this->parse_options( $args );
        $types  = isset( $opts['type'] ) ? (array) $opts['type'] : [ 'plugin', 'theme', 'software' ];
        $limit  = (int) ( $opts['limit'] ?? 20 );
        $page   = (int) ( $opts['page']  ?? 1 );

        $this->start_timer();
        $this->info( sprintf( 'Searching for "%s"...', $term ) );

        $result = HostedApplicationService::search_apps( [
            'term'  => $term,
            'types' => $types,
            'limit' => $limit,
            'page'  => $page,
        ] );

        $items = $result['items'] ?? [];

        if ( empty( $items ) ) {
            $this->warning( sprintf( 'No results found for "%s".', $term ) );
            return;
        }

        $rows = [];
        foreach ( $items as $app ) {
            $rows[] = [
                $app->get_id(),
                $app->get_name(),
                $app->get_slug(),
                $app->get_type(),
                $app->get_status(),
            ];
        }

        $this->newline();
        $this->table(
            [ 'ID', 'Name', 'Slug', 'Type', 'Status' ],
            $rows
        );

        $this->newline();
        $this->line( sprintf( '%d result(s) found.', count( $items ) ) );
        $this->done( '', true );
    }

    /**
     * Show full details for a single application.
     *
     * @param string|null $slug
     * @param string|null $type
     */
    private function handle_get( ?string $slug, ?string $type ): void {
        if ( ! $this->require_args( [ 'slug' => $slug, 'type' => $type ], 'smliser app get <slug> <type>' ) ) {
            return;
        }

        $this->start_timer();
        $app = HostedApplicationService::get_app_by_slug( $type, $slug );

        if ( ! $app ) {
            $this->error( sprintf( 'App "%s" of type "%s" not found.', $slug, $type ) );
            return;
        }

        $data = $app->get_rest_response();

        $this->newline();
        $this->info( sprintf( '%s / %s', $app->get_type(), $app->get_name() ) );
        $this->newline();

        // Core fields as a two-column table.
        $fields = [
            [ 'ID',          $app->get_id() ],
            [ 'Name',        $app->get_name() ],
            [ 'Slug',        $app->get_slug() ],
            [ 'Type',        $app->get_type() ],
            [ 'Status',      $app->get_status() ],
            [ 'Version',     $data['version']     ?? '' ],
            [ 'Author',      $app->get_author() ],
            [ 'Homepage',    $app->get_homepage() ],
            [ 'Download',    $app->get_download_url() ],
            [ 'Monetized',   $app->is_monetized() ? 'yes' : 'no' ],
            [ 'Created',     $app->get_date_created() ],
            [ 'Updated',     $app->get_last_updated() ],
        ];

        $this->table( [ 'Field', 'Value' ], $fields );
        $this->done( '', true );
    }

    /**
     * Count applications with optional filters.
     *
     * @param array $args Remaining args after 'count'.
     */
    private function handle_count( array $args ): void {
        $opts   = $this->parse_options( $args );
        $types  = isset( $opts['type'] ) ? (array) $opts['type'] : [ 'plugin', 'theme', 'software' ];
        $status = $opts['status'] ?? AbstractHostedApp::STATUS_ACTIVE;

        $count = HostedApplicationService::count_apps( compact( 'types', 'status' ) );

        $this->info( sprintf(
            '%d application(s) — type: %s, status: %s',
            $count,
            implode( ', ', $types ),
            $status
        ) );
    }

    /*
    |--------------------------------------------
    | WRITE SUBCOMMANDS — auth required
    |--------------------------------------------
    */

    /**
     * Change an application's status.
     *
     * @param string|null $slug
     * @param string|null $type
     * @param string|null $status
     */
    private function handle_status( ?string $slug, ?string $type, ?string $status ): void {
        if ( ! $this->require_args(
            [ 'slug' => $slug, 'type' => $type, 'status' => $status ],
            'smliser app status <slug> <type> <status>'
        ) ) {
            return;
        }

        if ( $status === AbstractHostedApp::STATUS_TRASH ) {
            $this->error( 'Use `smliser app trash <slug> <type>` to move an app to trash.' );
            return;
        }

        if ( ! $this->require_auth() ) {
            return;
        }

        if ( ! $this->confirm( sprintf( 'Change status of "%s" to "%s"?', $slug, $status ) ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        $this->start_timer();

        $request = new Request( [
            'app_slug'   => $slug,
            'app_type'   => $type,
            'app_status' => $status,
        ], method: 'POST' );

        $response = HostingController::change_app_status( $request );

        if ( $response->ok() ) {
            $this->done( sprintf( 'Status of "%s" changed to "%s".', $slug, $status ) );
        } else {
            $this->error( $response->get_error_message() );
        }
    }

    /**
     * Move an application to trash.
     *
     * @param string|null $slug
     * @param string|null $type
     */
    private function handle_trash( ?string $slug, ?string $type ): void {
        if ( ! $this->require_args(
            [ 'slug' => $slug, 'type' => $type ],
            'smliser app trash <slug> <type>'
        ) ) {
            return;
        }

        if ( ! $this->require_auth() ) {
            return;
        }

        if ( ! $this->confirm( sprintf( 'Move "%s" to trash?', $slug ) ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        $this->start_timer();

        $request = new Request( [
            'app_slug'   => $slug,
            'app_type'   => $type,
            'app_status' => AbstractHostedApp::STATUS_TRASH,
        ], method: 'POST' );

        $response = HostingController::change_app_status( $request );

        if ( $response->ok() ) {
            $this->done( sprintf( '"%s" moved to trash.', $slug ) );
        } else {
            $this->error( $response->get_error_message() );
        }
    }

    /**
     * Permanently delete an application — bypasses trash.
     *
     * Requires double confirmation. This is destructive and irreversible.
     *
     * @param string|null $slug
     * @param string|null $type
     */
    private function handle_purge( ?string $slug, ?string $type ): void {
        if ( ! $this->require_args(
            [ 'slug' => $slug, 'type' => $type ],
            'smliser app purge <slug> <type>'
        ) ) {
            return;
        }

        if ( ! $this->require_auth() ) {
            return;
        }

        $this->warning( sprintf( 'This will permanently delete "%s". This cannot be undone.', $slug ) );

        if ( ! $this->confirm( 'Are you sure?', false ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        if ( ! $this->confirm( sprintf( 'Type "yes" to confirm permanent deletion of "%s":', $slug ), false ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        $this->start_timer();

        $app = HostedApplicationService::get_app_by_slug( $type, $slug );

        if ( ! $app ) {
            $this->error( sprintf( 'App "%s" of type "%s" not found.', $slug, $type ) );
            return;
        }

        if ( $app->delete() ) {
            $this->done( sprintf( '"%s" permanently deleted.', $slug ) );
        } else {
            $this->error( sprintf( 'Failed to delete "%s".', $slug ) );
        }
    }

    /*
    |--------------------------------------------
    | HELP / DEFAULT / UNKNOWN
    |--------------------------------------------
    */

    private function handle_help(): void {
        $this->info( 'App Command' );
        $this->newline();
        $this->line( 'Usage:' );
        $this->line( '  ' . static::synopsis() );
        $this->newline();
        $this->line( static::help() );
    }

    private function handle_default(): void {
        $this->info( static::description() );
        $this->newline();
        $this->line( 'Run `smliser app help` to see available subcommands.' );
    }

    private function handle_unknown( string $subcommand ): void {
        $this->error( sprintf( 'Unknown subcommand: %s', $subcommand ) );
        $this->newline();
        $this->handle_help();
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Check that a principal is set on Guard.
     *
     * Prints a contextual error and returns false if no principal
     * is available so the caller can return early.
     *
     * @return bool
     */
    private function require_auth(): bool {
        if ( Guard::has_principal() ) {
            return true;
        }

        $this->error( 'Authentication required.' );
        $this->line( 'Set SMLISER_CLI_API_KEY in your .env file and ensure the service account is active.' );
        return false;
    }

    /**
     * Validate that required positional arguments are present.
     *
     * Prints the usage hint and returns false on the first missing arg.
     *
     * @param array<string, string|null> $args  Map of name => value.
     * @param string                     $usage Usage hint printed on failure.
     * @return bool
     */
    private function require_args( array $args, string $usage ): bool {
        foreach ( $args as $name => $value ) {
            if ( empty( $value ) ) {
                $this->error( sprintf( 'Missing required argument: <%s>', $name ) );
                $this->line( 'Usage: ' . $usage );
                return false;
            }
        }

        return true;
    }

    /**
     * Parse --key=value and --key options from an args array.
     *
     * Ignores positional arguments (those not starting with --).
     * Repeatable options (e.g. --type=plugin --type=theme) are
     * accumulated into an array.
     *
     * @param array<int, string> $args
     * @return array<string, string|string[]>
     */
    private function parse_options( array $args ): array {
        $opts = [];

        foreach ( $args as $arg ) {
            if ( ! str_starts_with( $arg, '--' ) ) {
                continue;
            }

            $arg = substr( $arg, 2 );

            if ( str_contains( $arg, '=' ) ) {
                [ $key, $value ] = explode( '=', $arg, 2 );
            } else {
                $key   = $arg;
                $value = 'true';
            }

            // Accumulate repeatable options into arrays.
            if ( isset( $opts[ $key ] ) ) {
                $opts[ $key ] = array_merge( (array) $opts[ $key ], [ $value ] );
            } else {
                $opts[ $key ] = $value;
            }
        }

        return $opts;
    }
}