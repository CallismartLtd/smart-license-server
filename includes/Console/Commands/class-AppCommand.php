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
 * Write operations require an authenticated principal on Guard. If no
 * principal is set the command prints a clear error and returns without
 * calling any controller.
 *
 * Usage:
 *   smliser app list [--type=plugin|theme|software] [--status=active] [--page=1] [--limit=20]
 *   smliser app search <term> [--type=...] [--limit=20]
 *   smliser app get <type> <slug>
 *   smliser app count [--type=...] [--status=active]
 *   smliser app save <slug> <type> [--name=...] [--path=... | --url=...]
 *   smliser app upload-asset <slug> <type> --asset-type=<type> [--path=... | --url=...]
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

use SmartLicenseServer\Console\CLIFilesystemAwareTrait;
use SmartLicenseServer\Console\CLIUtilsTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Security\Context\Guard;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Manage hosted applications from the CLI.
 */
class AppCommand implements CommandInterface {
    use CLIUtilsTrait, CLIFilesystemAwareTrait;

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
            '  get <type> <slug>                       Show details for a specific application.',
            '  count                                   Count hosted applications.',
            '  help                                    Show this help message.',
            '',
            'Options for list / search / count:',
            '  --type=plugin|theme|software            Filter by app type. Repeatable.',
            '  --status=active|inactive|suspended      Filter by status. Default: active.',
            '  --page=<n>                              Page number. Default: 1.',
            '  --limit=<n>                             Items per page. Default: 20.',
            '',
            'Examples:',
            '  smliser app list',
            '  smliser app list --type=plugin --status=active',
            '  smliser app search "my plugin"',
            '  smliser app get plugin my-plugin',
            '  smliser app count --type=theme',
        ] );
    }

    public function execute( array $args = [] ): void {
        $subcommand = $args[0] ?? null;

        match ( $subcommand ) {
            'list'          => $this->handle_list( array_slice( $args, 1 ) ),
            'search'        => $this->handle_search( array_slice( $args, 1 ) ),
            'get'           => $this->handle_get( $args[1] ?? '', $args[2] ?? '' ),
            'count'         => $this->handle_count( array_slice( $args, 1 ) ),
            'help'          => $this->handle_help(),
            null            => $this->handle_default(),
            default         => $this->handle_unknown( $subcommand ),
        };
    }

    /*
    |--------------------------------------------
    | READ SUBCOMMANDS — no auth required
    |--------------------------------------------
    */

    /**
     * List hosted applications with optional filters.
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

        $items      = $result['items']      ?? [];
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
        $this->table( [ 'ID', 'Name', 'Slug', 'Type', 'Status', 'Updated' ], $rows );

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
     */
    private function handle_search( array $args ): void {
        $term = $args[0] ?? null;

        if ( empty( $term ) ) {
            $this->error( 'Usage: smliser app search <term> [--type=...] [--limit=20]' );
            return;
        }

        $opts  = $this->parse_options( $args );
        $types = isset( $opts['type'] ) ? (array) $opts['type'] : [ 'plugin', 'theme', 'software' ];
        $limit = (int) ( $opts['limit'] ?? 20 );
        $page  = (int) ( $opts['page']  ?? 1 );

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
        $this->table( [ 'ID', 'Name', 'Slug', 'Type', 'Status' ], $rows );
        $this->newline();
        $this->line( sprintf( '%d result(s) found.', count( $items ) ) );
        $this->done( '', true );
    }

    /**
     * Show full details for a single application.
     * 
     * @param string $type App type.
     * @param string $slug App slug
     */
    private function handle_get( string $type, string $slug ): void {
        if ( ! $this->require_args( [ 'slug' => $slug, 'type' => $type ], 'smliser app get <type> <slug>' ) ) {
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

        $this->table( [ 'Field', 'Value' ], [
            [ 'ID',        $app->get_id() ],
            [ 'Name',      $app->get_name() ],
            [ 'Slug',      $app->get_slug() ],
            [ 'Type',      $app->get_type() ],
            [ 'Status',    $app->get_status() ],
            [ 'Version',   $data['version']   ?? '' ],
            [ 'Author',    $app->get_author() ],
            [ 'Homepage',  $app->get_homepage() ],
            [ 'Download',  $app->get_download_url() ],
            [ 'Monetized', $app->is_monetized() ? 'yes' : 'no' ],
            [ 'Created',   $app->get_date_created() ],
            [ 'Updated',   $app->get_last_updated() ],
        ] );

        $this->done( '', true );
    }

    /**
     * Count applications with optional filters.
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
    | FILE SUBCOMMANDS — auth required
    |--------------------------------------------
    */

    /**
     * Create or update a hosted application.
     *
     * Accepts a zip file via --path (local) or --url (remote download).
     * For software apps, an app.json manifest can be provided via
     * --app-json-path or --app-json-url.
     *
     * Usage:
     *   smliser app save <slug> <type> [options]
     *
     * Options:
     *   --name=<name>            Application display name. Required for new apps.
     *   --author=<author>        Author name. Required for new apps.
     *   --version=<version>      Version string.
     *   --path=<path>            Absolute local path to the zip file.
     *   --url=<url>              Remote URL to download the zip from.
     *   --app-json-path=<path>   Absolute local path to app.json (software only).
     *   --app-json-url=<url>     Remote URL to download app.json from (software only).
     *   --author-url=<url>       Author profile URL.
     *   --download-url=<url>     External download URL override.
     *   --owner-id=<id>          Owner ID (system_admin only).
     *
     * @param array $args Remaining args after 'save'.
     */
    private function handle_save( array $args ): void {
        $slug = $args[0] ?? null;
        $type = $args[1] ?? null;

        if ( ! $this->require_args(
            [ 'slug' => $slug, 'type' => $type ],
            'smliser app save <slug> <type> [--name=...] [--path=... | --url=...]'
        ) ) {
            return;
        }

        if ( ! $this->require_auth() ) {
            return;
        }

        $opts = $this->parse_options( $args );

        // Resolve zip file — --path takes precedence over --url.
        $zip_file    = null;
        $zip_cleanup = null;

        if ( ! empty( $opts['path'] ) ) {
            $zip_file = $this->resolve_local_file( (string) $opts['path'] );
            if ( $zip_file === null ) {
                return;
            }
        } elseif ( ! empty( $opts['url'] ) ) {
            $result = $this->download_to_tmp( (string) $opts['url'], 'zip' );
            if ( $result === null ) {
                return;
            }
            [ $zip_file, $zip_cleanup ] = $result;
        }

        // Resolve app.json — --app-json-path takes precedence over --app-json-url.
        $json_file    = null;
        $json_cleanup = null;

        if ( ! empty( $opts['app-json-path'] ) ) {
            $json_file = $this->resolve_local_file( (string) $opts['app-json-path'] );
            if ( $json_file === null ) {
                return;
            }
        } elseif ( ! empty( $opts['app-json-url'] ) ) {
            $result = $this->download_to_tmp( (string) $opts['app-json-url'], 'json' );
            if ( $result === null ) {
                return;
            }
            [ $json_file, $json_cleanup ] = $result;
        }

        $this->start_timer();
        $this->info( sprintf( 'Saving %s "%s"...', $type, $slug ) );

        // Build request params.
        $params = [
            'app_slug'   => $slug,
            'app_type'   => $type,
            'app_name'   => $opts['name']         ?? '',
            'app_author' => $opts['author']        ?? '',
            'app_version'=> $opts['version']       ?? '',
            'app_author_url'    => $opts['author-url']  ?? '',
            'app_download_url'  => $opts['download-url'] ?? '',
        ];

        if ( ! empty( $opts['owner-id'] ) ) {
            $params['app_owner_id'] = (int) $opts['owner-id'];
        }

        $request = new Request( params: $params, method: 'POST' );

        // Inject zip file into request if provided.
        if ( $zip_file !== null ) {
            $request = $this->inject_uploaded_file( $request, $zip_file, 'app_zip_file' );
            if ( $request === null ) {
                $this->cleanup( $zip_cleanup, $json_cleanup );
                return;
            }
        }

        // Inject app.json into request if provided.
        if ( $json_file !== null ) {
            $request = $this->inject_uploaded_file( $request, $json_file, 'app_json_file' );
            if ( $request === null ) {
                $this->cleanup( $zip_cleanup, $json_cleanup );
                return;
            }
        }

        try {
            $response = HostingController::save_app( $request );

            if ( $response->ok() ) {
                $this->done( sprintf( '%s "%s" saved successfully.', ucfirst( $type ), $slug ) );
            } else {
                $this->error( $response->get_error_message() ?: 'Save failed.' );
            }
        } finally {
            $this->cleanup( $zip_cleanup, $json_cleanup );
        }
    }

    /**
     * Upload one or more assets for a hosted application.
     *
     * Usage:
     *   smliser app upload-asset <slug> <type> --asset-type=<type> [--path=... | --url=...]
     *
     * Options:
     *   --asset-type=<type>   Asset type (icons, banners, screenshots, cover, screenshot).
     *   --path=<path>         Absolute local path to the asset file.
     *   --url=<url>           Remote URL to download the asset from.
     *   --asset-name=<name>   Asset filename override (used for PUT/replace operations).
     *
     * @param array $args Remaining args after 'upload-asset'.
     */
    private function handle_upload_asset( array $args ): void {
        $slug = $args[0] ?? null;
        $type = $args[1] ?? null;

        if ( ! $this->require_args(
            [ 'slug' => $slug, 'type' => $type ],
            'smliser app upload-asset <slug> <type> --asset-type=<type> [--path=... | --url=...]'
        ) ) {
            return;
        }

        if ( ! $this->require_auth() ) {
            return;
        }

        $opts       = $this->parse_options( $args );
        $asset_type = $opts['asset-type'] ?? null;
        $asset_name = $opts['asset-name'] ?? null;

        if ( empty( $asset_type ) ) {
            $this->error( 'Missing required option: --asset-type=<type>' );
            $this->line( 'Valid types: icons, banners, screenshots, cover, screenshot' );
            return;
        }

        // Resolve asset file.
        $asset_file = null;
        $cleanup    = null;

        if ( ! empty( $opts['path'] ) ) {
            $asset_file = $this->resolve_local_file( (string) $opts['path'] );
            if ( $asset_file === null ) {
                return;
            }
        } elseif ( ! empty( $opts['url'] ) ) {
            $result = $this->download_to_tmp( (string) $opts['url'] );
            if ( $result === null ) {
                return;
            }
            [ $asset_file, $cleanup ] = $result;
        } else {
            $this->error( 'Provide an asset file via --path=<path> or --url=<url>.' );
            return;
        }

        $this->start_timer();
        $this->info( sprintf( 'Uploading %s asset for "%s"...', $asset_type, $slug ) );

        $params = [
            'app_slug'   => $slug,
            'app_type'   => $type,
            'asset_type' => $asset_type,
        ];

        if ( $asset_name !== null ) {
            $params['asset_name'] = $asset_name;
        }

        $request = new Request( $params, method: 'POST' );
        $request = $this->inject_uploaded_file( $request, $asset_file, 'asset_file' );

        if ( $request === null ) {
            $this->cleanup( $cleanup );
            return;
        }

        try {
            $response = HostingController::app_asset_upload( $request );

            if ( $response->ok() ) {
                $this->done( sprintf( 'Asset uploaded for "%s".', $slug ) );
            } else {
                $this->error( $response->get_error_message() ?: 'Asset upload failed.' );
            }
        } finally {
            $this->cleanup( $cleanup );
        }
    }

    /*
    |--------------------------------------------
    | FILE HELPERS
    |--------------------------------------------
    */

    /*
    |--------------------------------------------
    | WRITE SUBCOMMANDS — auth required
    |--------------------------------------------
    */

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
}