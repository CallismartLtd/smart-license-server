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
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands\Apps;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Commands\AbstractCommand;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Console\Traits\CLIUtilsTrait;
use SmartLicenseServer\Console\Traits\CLIFilesystemAwareTrait;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Manage hosted applications from the CLI.
 */
class AppCommand extends AbstractCommand {
    use CLIUtilsTrait, CLIFilesystemAwareTrait;

    public function __construct(
        FileSystem $file_system,
        InputInterface $io,
        OutputInterface $output,
        ScriptName $script_name
    ) {
        $this->file_system = $file_system;
        return parent::__construct( $io, $output, $script_name );
    }

    /*
    |--------------------------------------------
    | CommandInterface
    |--------------------------------------------
    */

    public static function name(): string {
        return 'app';
    }

    public function description(): string {
        return 'Inspect and manage hosted applications.';
    }

    public function synopsis(): string {
        return "{$this->script_name} app <subcommand> [arguments] [--options]";
    }

    public function help(): string {
        $script_name    = $this->script_name;
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
            "  {$script_name} app list",
            "  {$script_name} app list --type=plugin --status=active",
            "  {$script_name} app search \"my plugin\"",
            "  {$script_name} app get plugin my-plugin",
            "  {$script_name} app count --type=theme",
        ] );
    }

    public function run( CommandInput $input ): int {
        $this->output->info( static::description() );
        $this->output->newline();
        $this->output->writeln( 'Run `smliser app help` to see available subcommands.' );

        return 0;
    }

    public function get_subcommands() : array {
        return [
            'list'      => [$this, 'handle_list'],
            'search'    => [$this, 'handle_search'],
            'get'       => [$this, 'handle_get'],
            'count'     => [$this, 'handle_count'],
            'help'      => [$this, 'handle_help'],
        ];
    }

    /*
    |--------------------------------------------
    | READ SUBCOMMANDS — no auth required
    |--------------------------------------------
    */

    /**
     * List hosted applications with optional filters.
     */
    public function handle_list( CommandInput $input ): int {
        $types  = $input->has_option( 'type' ) ? (array) $input->get_option( 'type' ) : [ 'plugin', 'theme', 'software' ];
        $status = $opts['status'] ?? AbstractHostedApp::STATUS_ACTIVE;
        $page   = (int) $input->get_option( 'page', 1 );
        $limit  = (int) $input->get_option( 'limit', 20 );
        
        $stopwatch = new Stopwatch();
        $stopwatch->start();
        
        $this->output->info( sprintf( 'Fetching apps — type: %s, status: %s, page: %d', implode( ', ', $types ), $status, $page ) );

        $result = HostedApplicationService::get_apps( compact( 'page', 'limit', 'status', 'types' ) );

        $items      = $result['items'] ?? [];
        $pagination = $result['pagination'] ?? [];

        if ( empty( $items ) ) {
            $this->output->warning( 'No applications found.' );
            return 0;
        }

        $rows = [];
        foreach ( $items as $app ) {
            $rows[] = [
                $app->get_id(),
                $app->get_name(),
                $app->get_slug(),
                $app->get_type(),
                $app->get_status(),
                $app->get_created_at()->format( \smliser_datetime_format() ),
                $app->get_updated_at()->format( \smliser_datetime_format() ),
            ];
        }

        $this->output->newline();
        $this->output->table( [ 'ID', 'Name', 'Slug', 'Type', 'Status', 'Created', 'Updated' ], $rows );

        if ( ! empty( $pagination ) ) {
            $this->output->newline();
            $this->output->writeln( sprintf(
                'Page %d of %d  ·  %d total',
                $pagination['page'],
                $pagination['total_pages'],
                $pagination['total']
            ) );
        }

        $this->output->success(
            sprintf( 'Completed in %ss', $stopwatch->elapsed() )
        );

        return 0;
    }

    /**
     * Search applications by term.
     */
    public function handle_search( CommandInput $input ): int {
        $term = $input->get_option( 'term' ) ?? $input->get_argument(0);

        if ( empty( $term ) ) {
            $this->output->error( 'Usage: smliser app search [--term...] [--type=...] [--limit=20]' );
            return 1;
        }

        $types = $input->has_option( 'type' ) ? (array) $input->get_option( 'type', [] ) : [ 'plugin', 'theme', 'software' ];
        $limit = (int) $input->get_option( 'limit', 20 );
        $page  = (int) $input->get_option( 'page', 1 );

        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $this->output->info( sprintf( 'Searching for "%s"...', $term ) );

        $result = HostedApplicationService::search_apps( [
            'term'  => $term,
            'types' => $types,
            'limit' => $limit,
            'page'  => $page,
        ] );

        $items = $result['items'] ?? [];

        if ( empty( $items ) ) {
            $this->output->warning( sprintf( 'No results found for "%s".', $term ) );
            return 0;
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

        $this->output->newline();
        $this->output->table( [ 'ID', 'Name', 'Slug', 'Type', 'Status' ], $rows );
        $this->output->newline();
        $this->output->writeln( sprintf( '%d result(s) found.', count( $items ) ) );
        $this->output->success( sprintf( 'Completed in %ss', $stopwatch->elapsed() ) );

        return 0;
    }

    /**
     * Show full details for a single application.
     */
    public function handle_get( CommandInput $input ): int {
        $type   = $input->get_option( 'type' );
        $slug   = $input->get_option( 'slug' );

        if ( ! $this->require_args( [ 'type' => $type, 'slug' => $slug ], 'smliser app get <type> <slug>' ) ) {
            return 2;
        }

        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $app = HostedApplicationService::get_app_by_slug( $type, $slug );

        if ( ! $app ) {
            $this->output->error( sprintf( 'App "%s" of type "%s" not found.', $slug, $type ) );
            return 1;
        }

        $data = $app->get_rest_response();

        $this->output->newline();
        $this->output->info( sprintf( '%s / %s', $app->get_type(), $app->get_name() ) );
        $this->output->newline();

        $this->output->table( [ 'Field', 'Value' ], [
            [ 'ID',        $app->get_id() ],
            [ 'Name',      $app->get_name() ],
            [ 'Slug',      $app->get_slug() ],
            [ 'Type',      $app->get_type() ],
            [ 'Status',    $app->get_status() ],
            [ 'Version',   $data['version']   ?? '' ],
            [ 'Author',    $app->get_author() ],
            [ 'Homepage',  $app->get_homepage() ],
            [ 'Download',  $app->get_download_url() ],
            [ 'Monetized', $app->is_monetized() ? 'YES' : 'NO' ],
            [ 'Created',   $app->get_created_at()->format( \smliser_datetime_format() ) ],
            [ 'Updated',   $app->get_updated_at()->format( \smliser_datetime_format() ) ],
        ] );

        $this->output->success( \sprintf( 'Completed in %ss', $stopwatch->elapsed() ) );

        return 0;
    }

    /**
     * Count applications with optional filters.
     */
    public function handle_count( CommandInput $input ): int {
        $types  = $input->has_option( 'type' ) ? (array) $input->get_option( 'type', [] ) : [ 'plugin', 'theme', 'software' ];
        $status = $input->get_option( 'status', AbstractHostedApp::STATUS_ACTIVE );
        $count  = HostedApplicationService::count_apps( compact( 'types', 'status' ) );

        $this->output->info( sprintf(
            '%d application(s) — type(s): %s. status: %s',
            $count,
            implode( ', ', $types ),
            $status
        ) );

        return 0;
    }
}