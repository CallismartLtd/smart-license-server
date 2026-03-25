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
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostingController;

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
            'upload-asset'  => $this->upload_asset( $args[1] ?? '', $args[2] ?? '' ),
            'change-status' => $this->change_status( $args[1] ?? null, $args[2] ?? null ),
            'trash'         => $this->trash_app( $args[1] ?? '' ),
            'delete'        => $this->trash_app( $args[1] ?? '', $args[2] ?? '' ),
            'purge'         => $this->purge_app( $args[1] ?? '', $args[2] ?? '' ),
            'help'          => $this->handle_help(),
            null            => $this->handle_default(),
            default         => $this->handle_unknown( $subcommand ),
        };
    }

    public static function help(): string {
        $type   = static::get_type();
        $name   = \ucfirst( $type );
        
        return implode( PHP_EOL, [
            'Subcommands:',
            "  create                                   Create a new {$type}.",
            "  update <slug>                            Update an existing {$type}.",
            "  upload-asset <slug>                      Upload a {$type} asset.",
            "  change status <slug> <status>            Change {$type} status.",
            "  trash <slug>                             Move a {$type} to trash.",
            "  delete <slug>                            Move a {$type} to trash (same as trash).",
            "  purge <slug>                             Permanently delete {$type}.",
            "  help                                     Show this help message.",
            "",
            "Options for create / update:",
            "  --slug=<slug>                            {$name} slug. Required for {$type} update.",
            "  --name=<name>                            Display name. Required for new {$type}.",
            "  --author=<author>                        Author name. Required for new {$type}.",
            "  --version=<version>                      Version string.",
            "  --app-zip-file=<path>                    Local path to zip file(must be accessible).",
            "  --app-zip-url=<url>                      Remote URL to download zip from.",
            "  --app-json-file=<path>                   Local path to app.json (software only).",
            "  --app-json-url=<url>                     Remote URL to download app.json from.",
            "  --author-url=<url>                       Author profile URL.",
            "  --download-url=<url>                     External download URL override.",
            "  --owner-id=<id>                          Owner ID (system_admin only).",
            "",
            "Options for upload-asset:",
            "  --asset-type=<type>                     icons, banners, screenshots, cover, screenshot.",
            "  --path=<path>                           Local path to asset file.",
            "  --url=<url>                             Remote URL to download asset from.",
            "  --asset-name=<name>                     Filename override (for replace operations).",
            "",
            "Valid statuses for the change-status subcommand:",
            "  active, deactivated, suspended",
            "  (trash is handled by the trash/delete subcommand)",
            "",
            "Examples:",
            "  smliser {$type} create --name=\"My {$name}\" --author=\"Dev\" --path=/tmp/my-{$type}.zip",
            "  smliser {$type} update my-{$type} --url=https://example.com/{$type}.zip --version=2.0.0",
            "  smliser {$type} upload-asset my-{$type} --asset-type=icons --path=/tmp/icon.png",
            "  smliser {$type} change-status my-{$type} inactive",
            "  smliser {$type} trash my-{$type}",
            "  smliser {$type} purge my-{$type}",
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
     * Create new app
     */
    private function create_app( array $args ) {

    }

    /**
     * Move an application to trash.
     * 
     * Note: Only system administrators can do this.
     * 
     * @param string $slug The app slug to trash.
     */
    private function trash_app( string $slug ): void {
        if ( ! $this->require_args(
            [ 'slug' => $slug ],
            sprintf( 'smliser %s trash <slug>', static::get_type() )
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
            'app_type'   => static::get_type(),
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
     * Note: Only system administrators can do this.
     * 
     * @param string $slug The app slug to flush.
     */
    private function purge_app( string $slug ): void {
        if ( ! $this->require_args(
            [ 'slug' => $slug ],
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

        $prompt  = $this->prompt( sprintf( 'Type "yes" to confirm permanent deletion of "%s":', $slug ), '' );
        if ( 'yes' !== strtolower( $prompt ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        $this->start_timer();
        
        $type   = static::get_type();
        $app    = HostedApplicationService::get_app_by_slug( $type, $slug );

        if ( ! $app ) {
            $this->error( sprintf( 'App "%s" of type "%s" not found.', $slug, $type ) );
            return;
        }

        if ( true ) {
            $this->done( sprintf( '"%s" permanently deleted.', $slug ) );
        } else {
            $this->error( sprintf( 'Failed to delete "%s".', $slug ) );
        }
    }

    /**
     * Change an application's status.
     */
    private function change_status( ?string $slug, ?string $status ): void {
        if ( ! $this->require_args(
            [ 'slug' => $slug, 'status' => $status ],
            sprintf( 'smliser %s status <slug> <status>', static::get_type() )
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
}