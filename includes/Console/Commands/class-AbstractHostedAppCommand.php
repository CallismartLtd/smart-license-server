<?php
/**
 * Abstract hosted app command class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */
declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CLIFilesystemAwareTrait;
use SmartLicenseServer\Console\CLIUtilsTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileSystemException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Abstract implementation of commands that are common to hosted applications.
 */
abstract class AbstractHostedAppCommand implements CommandInterface {
    use CLIUtilsTrait, CLIFilesystemAwareTrait;

    /*
    |-------------------
    | CommandInterface
    |-------------------
    */

    public static function name() : string {
        return static::get_type();
    }

    public static function description(): string {
        return sprintf( 'Inspect and manage %s.', static::get_type() );
    }

    public static function synopsis(): string {
        return sprintf( 'smliser %s <subcommand> [arguments] [--options]', static::get_type() );
    }

    public function execute( array $args = [] ): void {
        $this->start_timer();

        $subcommand = $args[0] ?? null;

        match ( $subcommand ) {
            'create'        => $this->create_app( array_slice( $args, 1 ) ),
            'update'        => $this->update_app( array_slice( $args, 1 ) ),
            'upload-asset'  => $this->upload_asset( array_slice( $args, 1 ) ),
            'change-status' => $this->change_status( array_slice( $args, 1 ) ),
            'trash'         => $this->trash_app( array_slice( $args, 1 ) ),
            'delete'        => $this->trash_app( array_slice( $args, 1 ) ),
            'purge'         => $this->purge_app( array_slice( $args, 1 ) ),
            'help'          => $this->handle_help(),
            null            => $this->handle_default(),
            default         => $this->handle_unknown( $subcommand ),
        };
    }

    public static function help(): string {
        $type           = static::get_type();
        $name           = \ucfirst( $type );
        $statuses       = array_keys( AbstractHostedApp::get_statuses() );
        $valid_statuses = implode( ', ', $statuses );

        $example_statuses = $statuses;
        \shuffle( $example_statuses );
        $current_status = $example_statuses[0] ?? 'active';
        
        return implode( PHP_EOL, [
            'Subcommands:',
            "  create                                   Create a new {$type}.",
            "  update --slug=<slug>                     Update an existing {$type}.",
            "  upload-asset --slug=<slug>               Upload a {$type} asset.",
            "  change-status --slug=<slug>              Change {$type} status.",
            "  trash --slug=<slug>                      Move a {$type} to trash.",
            "  delete --slug=<slug>                     Move a {$type} to trash (same as trash).",
            "  purge --slug=<slug>                      Permanently delete {$type}.",
            "  help                                     Show this help message.",
            "",
            "Options for create / update:",
            "  --slug=<slug>                            {$name} slug. Required for {$type} update.",
            "  --name=<name>                            {$name} display name. Required for new {$type}.",
            "  --author=<author>                        Author name. Required for new {$type}.",
            "  --version=<version>                      Version string.",
            "  --app-zip-file=<path>                    Local path to zip file (must be accessible).",
            "  --app-zip-url=<url>                      Remote URL to download zip from.",
            "  --app-json-file=<path>                   Local path to app.json.",
            "  --app-json-url=<url>                     Remote URL to download app.json from.",
            "  --author-url=<url>                       Author profile URL.",
            "  --download-url=<url>                     External download URL override.",
            "  --owner-id=<id>                          Owner ID (system_admin only).",
            "",
            "Note: A zip file is required for a new {$type}. Provide --app-zip-file or --app-zip-url.",
            "",
            "Options for upload-asset:",
            "  --asset-type=<type>                      icons, banners, screenshots, cover.",
            "  --path=<path>                            Local path to asset file.",
            "  --url=<url>                              Remote URL to download asset from.",
            "  --asset-name=<name>                      Filename override (for replace operations).",
            "",
            "Options for change-status / trash / delete subcommands:",
            "  --status=<status>                        New status for the {$type}.",
            "-f, --force, -y, --yes                     Skip confirmation prompt.",
            "Valid statuses:",
            "  {$valid_statuses}",
            "  (Note: 'trash' is managed via the trash/delete subcommands)",
            "",
            "Examples:",
            "  smliser {$type} create --name=\"My {$name}\" --author=\"Dev\" --app-zip-file=/tmp/my-{$type}.zip",
            "  smliser {$type} update --slug=my-{$type} --app-zip-url=https://example.com/{$type}.zip --version=2.0.0",
            "  smliser {$type} upload-asset --slug=my-{$type} --asset-type=icons --path=/tmp/icon.png",
            "  smliser {$type} change-status --slug=my-{$type} --status={$current_status}",
            "  smliser {$type} trash --slug=my-{$type}",
            "  smliser {$type} purge --slug=my-{$type}",
        ] );
    }


    /*
    |-------------------
    | ABSTRACT METHODS
    |-------------------
    */

    /**
     * Get type.
     * 
     * @return string
     */
    abstract static protected function get_type() : string;


    /*
    |--------------------------------------------
    | HELP / DEFAULT / UNKNOWN
    |--------------------------------------------
    */

    private function handle_help(): void {
        $this->info( sprintf( '%s Command', \ucfirst( static::get_type() ) ) );
        $this->newline();
        $this->line( 'Usage:' );
        $this->line( '  ' . static::synopsis() );
        $this->newline();
        $this->line( static::help() );
    }

    private function handle_default(): void {
        $this->info( static::description() );
        $this->newline();
        $this->line( sprintf( 'Run `smliser %s help` to see available subcommands.', static::name()) );
    }

    private function handle_unknown( string $subcommand ): void {
        $this->error( sprintf( 'Unknown subcommand: %s', $subcommand ) );
        $this->newline();
        $this->handle_help();
    }

    /*
    |----------------------------------
    | CREATE|UPDATE|DELETE HOSTED APP
    |----------------------------------
    */

    /**
     * Create new app.
     * 
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    private function create_app( array $args ) {

        if ( ! $this->require_auth() ) {
            return;
        }

        $opts   = $this->parse_options( $args );
        $type   = static::get_type();
        $this->start_timer();
        $this->info( sprintf( 'Creating %s "%s"...', static::get_type(), $opts['name'] ?? '' ) );

        $request    = $this->buildRequest( $opts );

        if ( null === $request ) {
            return;
        }
        
        $response = HostingController::save_app( $request );

        if ( $response->ok() ) {
            $slug = $response->get_response_data()->get( 'smliser_resource' )?->get_slug() ?? static::get_type();

            $this->done( sprintf( '%s "%s" saved successfully.', ucfirst( $type ), $slug ) );
        } else {
            $this->error( $response->get_error_message() ?: 'Save failed.' );
        }

    }

    /**
     * Update an app
     */
    private function update_app( array $args ) {
        if ( ! $this->require_auth() ) {
            return;
        }

        $opts   = $this->parse_options( $args );
        $usage  = sprintf( 'smliser %s update --slug=<slug> [options]', static::get_type() );

        if ( ! $this->require_options( $opts, [ 'slug' ], $usage ) ) {
            return;
        }

        $type   = static::get_type();
        $this->start_timer();
        $this->info( sprintf( 'Updating %s "%s"...', $type, $opts['name'] ?? '' ) );
        
        
        $request    = $this->buildRequest( $opts );
        if ( null === $request ) {
            return;
        }

        $response = HostingController::save_app( $request );

        if ( $response->ok() ) {
            $slug = $response->get_response_data()->get( 'smliser_resource' )?->get_slug() ?? static::get_type();

            $this->done( sprintf( '%s "%s" updated successfully.', ucfirst( $type ), $slug ) );
        } else {
            $this->error( $response->get_error_message() ?: 'Update failed.' );
        }

    }

    /**
     * Move an application to trash.
     * 
     * Note: Only system administrators can do this.
     * 
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    private function trash_app( array $args ): void {
        $opts   = $this->parse_options( $args );
        $slug   = $opts['slug'] ?? '';
        $usage  = sprintf( 'smliser %s trash --slug=<slug>', static::get_type() );

        if ( ! $this->require_args( [ 'slug' => $slug ], $usage ) ) {
            return;
        }
        if ( ! $this->require_auth() ) {
            return;
        }

        $args   = array(
            "--slug=$slug",
            "--status=" . AbstractHostedApp::STATUS_TRASH,
            "--yes"
        );

        $this->change_status( $args );
    }

    /**
     * Permanently delete an application — bypasses trash.
     * 
     * Note: Only system administrators can do this.
     * 
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    private function purge_app( array $args ): void {
        $opts   = $this->parse_options( $args );
        $slug   = $opts['slug'] ?? '';

        if ( ! $this->require_args(
            [ 'slug' => $slug ],
            sprintf( 'smliser %s purge --slug=<slug>', static::get_type() )
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

        $this->start_timer();
        
        $type   = static::get_type();
        $app    = HostedApplicationService::get_app_by_slug( $type, $slug );

        if ( ! $app ) {
            $this->error( sprintf( '%s with slug "%s" not found.', ucfirst( $type ), $slug ) );
            return;
        }

        $prompt  = $this->prompt( sprintf( 'Type "yes" to confirm permanent deletion of "%s":', $app->get_name() ), '' );
        if ( 'yes' !== strtolower( $prompt ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        $repo_class = HostedApplicationService::get_app_repository_class( $type );

        try {
            $app_dir    = $repo_class->enter_slug( $slug );
        } catch( FileSystemException ) {
            // Maybe in trash?
            $types      = \smliser_pluralize( $type );
            $app_dir    = FileSystemHelper::join_path( SMLISER_TRASH_DIR, $types, $slug );
        }

        try {
            $repo_class->delete( $app_dir, true );
            $app->delete();
            $this->done( sprintf( '"%s" permanently deleted.', $slug ) );
        } catch ( \Throwable $e ) {
            $this->error( sprintf( 'Failed to delete "%s": %s', $slug, $e->getMessage() ) );
        }
    }

    /**
     * Change an application's status.
     * 
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    private function change_status( array $args ) : void {
        $opts   = $this->parse_options( $args );
        $usage  = sprintf( 'smliser %s change-status --slug=<slug> --status=<status>', static::get_type() );
        
        if ( ! $this->require_options( $opts, [ 'slug', 'status' ], $usage ) ) {
            return;
        }

        $slug   = $opts['slug'];
        $status = $opts['status'];

        if ( ! $this->require_auth() ) {
            return;
        }

        $confirmed  = $opts['y'] ?? $opts['yes'] ?? $opts['force'] ?? $opts['f'] ?? false;

        if ( ! $confirmed && ! $this->confirm( sprintf( 'Change status of "%s" to "%s"?', $slug, $status ) ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        $this->start_timer();

        $request = new Request( [
            'app_slug'   => $slug,
            'app_type'   => static::get_type(),
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
     * Upload app asset.
     * 
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    private function upload_asset( array $args ) : void {

        $opts   = $this->parse_options( $args );
        $usage  = sprintf( 'smliser %s upload-asset --slug=<slug> --asset-type=<type> [--path=<path> | --url=<url>] [--asset-name=<name>]', static::get_type() );

        if ( ! $this->require_options( $opts, [ 'slug', 'asset-type' ], $usage ) ) {
            return;
        }

        $request    = $this->buildAssetsRequest( $opts );

        if ( ! $request ) {
            return;
        }

        $response   = HostingController::app_asset_upload( $request );

        if ( $response->ok() ) {
            // Get the results of the upload.
            $data       = $response->get_body();
            $results    = $data['result'] ?? [];
            

            if ( $results instanceof Exception ) {
                $this->error( $results->get_error_message() );
                return;
            }

            if ( $request->has( 'asset_name' ) ) {
                // This is a single file PUT request.
                $t_header   = ['Asset Name', 'Asset URL'];
                $t_row      = [
                    [
                        $results['asset_name'] ?? '',
                        $results['asset_url'] ?? ''
                    ]
                ];

                $this->table( $t_header, $t_row );
                $this->done( 'Asset uploaded.', true );
                return;
            }
            
            $this->line( 'Upload results:' );
            
            $uploaded   = $results['uploaded'] ?? [];
            $failed     = $results['failed'] ?? [];

            if ( ! empty( $failed ) ) {
                $this->table(
                    ['Error', 'Message'],
                    array_map( function( $er ) {
                        return [key( $er ), smliser_implode_deep( $er ) ];
                    }, $failed )
                );                
            } else {
                $this->line( 'All assets uploaded successfully.' );
                $t_header   = ['Asset Names', 'Asset URLs'];
                $t_rows     = array_map( function( $asset ) {
                    return [
                        $asset['asset_name'] ?? '',
                        $asset['asset_url'] ?? ''
                    ];
                }, $uploaded );

                $this->table( $t_header, $t_rows );

                $message = count( $uploaded ) === 1
                    ? 'Asset uploaded.'
                    : sprintf( '%d assets uploaded.', count( $uploaded ) );
                $this->done( $message, true );

            }


        } else {
            $this->error( $response->get_error_message() ?: 'Asset upload failed.' );
        }
    }

    /**
     * Build app request object.
     */
    private function buildRequest( $opts ) {
        // Resolve zip file — --app-zip-path takes precedence over --app-zip-url.
        $zip_file    = null;
        $zip_cleanup = null;

        if ( ! empty( $opts['app-zip-path'] ) ) {
            $zip_file = $this->resolve_local_file( (string) $opts['app-zip-path'] );
            if ( $zip_file === null ) {
                return;
            }
        } elseif ( ! empty( $opts['app-zip-url'] ) ) {
            $zip_file = $this->download_to_tmp( (string) $opts['app-zip-url'] );
            if ( $zip_file === null ) {
                return;
            }
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
            $json_file = $this->download_to_tmp( (string) $opts['app-json-url'] );
            if ( $json_file === null ) {
                return;
            }
        }

        // Build request params.
        $params = [
            'app_type'   => static::get_type(),
            'app_name'   => $opts['name'] ?? '',
            'app_author' => $opts['author'] ?? '',
            'app_version'=> $opts['version']       ?? '',
            'app_author_url'    => $opts['author-url']  ?? '',
            'app_download_url'  => $opts['download-url'] ?? '',
        ];

        if ( ! empty( $opts['owner-id'] ) ) {
            $params['app_owner_id'] = (int) $opts['owner-id'];
        }

        if ( ! empty( $opts['slug'] ) ) {
            $params['app_slug'] = $opts['slug'];
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

        return $request;

    }

    /**
     * Build asset upload request object.
     * 
     * @param array $opts Parsed command options.
     * @return Request|null The request object or null on failure.
     */
    private function buildAssetsRequest( array $opts ) : ?Request {
        // Resolve asset file — --path takes precedence over --url.
        $asset_files    = [];

        if ( ! empty( $opts['path'] ) ) {
            if ( is_array( $opts['path'] ) ) {
                foreach ( $opts['path'] as $path ) {
                    $asset_file = $this->resolve_local_file( (string) $path );
                    if ( $asset_file === null ) {
                        return null;
                    }
                    $asset_files[] = $asset_file;
                }
            } else {
                $asset_file = $this->resolve_local_file( (string) $opts['path'] );
                if ( $asset_file === null ) {
                    return null;
                }
                $asset_files[] = $asset_file;
            }

        } elseif ( ! empty( $opts['url'] ) ) {
            if ( is_array( $opts['url'] ) ) {
                foreach ( $opts['url'] as $url ) {
                    $asset_file = $this->download_to_tmp( (string) $url );
                    if ( $asset_file === null ) {
                        return null;
                    }
                    $asset_files[] = $asset_file;
                }
            } else {
                $asset_file = $this->download_to_tmp( (string) $opts['url'] );
                if ( $asset_file === null ) {
                    return null;
                }
                $asset_files[] = $asset_file;
            }
        } else {
            $this->error( 'Either --path or --url must be provided for the asset.' );
            return null;
        }

        // Build request params.
        $params = [
            'app_type'   => static::get_type(),
            'app_slug'   => $opts['slug'] ?? '',
            'asset_type' => $opts['asset-type'] ?? '',
        ];

        $method = 'POST';

        if ( ! empty( $opts['asset-name'] ) ) {
            $params['asset_name']   = $opts['asset-name'];
            $method                 = 'PUT';
        }

        $request = new Request( params: $params, method: $method );

        // Inject asset files into request.
        $request = $this->inject_uploaded_files( $request, $asset_files, 'asset_file' );
        if ( $request === null ) {
            return null;
        }

        if ( $request->has( 'asset_name' ) ) {
            $asset_name = $request->get( 'asset_name' );
            $request->get_files( 'asset_file' )?->get(0)?->set_new_name( $asset_name );
        }

        return $request;
    }
}