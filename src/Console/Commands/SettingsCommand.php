<?php
/**
 * Settings command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CLIUtilsTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Utils\Format;

/**
 * Manage and inspect the system settings.
 *
 * Usage:
 *   smliser settings set --name="email_provider" --value="smtp"
 *   smliser settings get <name>
 *   smliser settings delete <name>
 *   smliser settings help
 */
class SettingsCommand implements CommandInterface {
    use CLIUtilsTrait;

    public static function name(): string {
        return 'settings';
    }

    public static function description(): string {
        return 'Manage the system settings.';
    }
    public static function synopsis(): string {
        return 'smliser settings <subcommand> [arguments]';
    }

    public static function help(): string {
        return implode( PHP_EOL, [
            'Subcommands:',
            '  set              Set the value of the gieven settings key.',
            '  get  <key>       Get the value of the given settings key',
            '  delete <key>     Remove a specific key from the settings.',
            '  help             Show this help message.',
            '',
            'Examples:',
            '  smliser settings set --name="option_name" --value="option_value"',
            '  smliser settings get smliser_some_key',
            '  smliser settings delete smliser_some_key',
        ]);
    }


    public function execute( array $args = [] ): void {
        $subcommand = $args[0] ?? null;
        $commandArgs    = array_slice( $args, 1 );

        match ( $subcommand ) {
            'set'    => $this->handle_set( $commandArgs ),
            'get'    => $this->handle_get( $commandArgs ),
            'delete' => $this->handle_delete( $commandArgs ),
            'list'   => $this->handle_list( $commandArgs ),
            'search' => $this->handle_search( $commandArgs ),
            'help'   => $this->handle_help(),
            null     => $this->handle_default(),
            default  => $this->handle_unknown( $subcommand ),
        };
    }

    /*
    |--------------------------------------------
    | SUBCOMMAND HANDLERS
    |--------------------------------------------
    */

    /**
     * Set or update a settings in the database
     * 
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    protected function handle_set( array $args ) : void {
        $opts   = $this->parse_options( $args );
        $name   = $opts['name'] ?? null;
        $value  = $opts['value'] ?? null;

        if ( empty( $name ) ) {
            $this->error( 'Usage: smliser settings set --name=option_name --value=option_value' );
            return;
        }

        $this->start_timer();

        $result = smliser_settings()->set( $name, $value );

        if ( $result ) {
            $this->done( 'Saved successfully', true );
        } else {
            $this->error( 'Unable to save to the database' );
        }

    }

    /**
     * Retrieve and display a specific settings key.
     *
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    protected function handle_get( array $args  ): void {
        $key = $args[0] ?? null;

        if ( empty( $key ) || is_array( $key ) || str_starts_with( $key, '--' ) ) {
            $this->error( 'Usage: smliser settings get <key>' );
            return;
        }

        $value      = smliser_settings()->get( $key, null, true );
        $formatted  = $this->format_option_value( $value );

        $this->info( "Settings value for [{$key}]:" );
        $this->table(
            ['Option Name', 'Option Value'],
            [
                [Format::truncate( $key, 35 ), $formatted]
            ]
        );
    }

    /**
     * Delete a specific settings key.
     *
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    protected function handle_delete( array $args ): void {
        $key = $args[0] ?? null;

        if ( empty( $key ) || is_array( $key ) || str_starts_with( $key, '--' ) ) {
            $this->error( 'Usage: smliser settings delete <key>' );
            return;
        }

        if ( ! $this->confirm( "Delete settings key [{$key}]?" ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        smliser_settings()->delete( $key )
            ? $this->success( "Key [{$key}] deleted from settings." )
            : $this->error( "Failed to delete key [{$key}]. It may not exist." );
    }

    /**
     * List settings (paginated).
     *
     * Usage:
     *   smliser settings list --page=1 --limit=20
     */
    protected function handle_list( array $args ): void {

        $opts  = $this->parse_options( $args );
        $page  = isset( $opts['page'] ) ? (int) $opts['page'] : 1;
        $limit = isset( $opts['limit'] ) ? (int) $opts['limit'] : 30;

        $this->start_timer();

        $results = smliser_settings()->all( $page, $limit );

        if ( empty( $results ) ) {
            $this->error( 'No settings found.' );
            return;
        }

        $rows = [];

        foreach ( $results as $key => $value ) {
            $rows[] = [ Format::truncate( $key, 35 ), $this->format_option_value( $value ) ];
        }

        $this->table(
            [ 'Option Name', 'Option Value' ],
            $rows
        );

        $this->done( 'Done', true );
    }

    /**
     * Search settings by key name.
     *
     * Usage:
     *   smliser settings search --query="license" --page=1 --limit=20
     */
    protected function handle_search( array $args ): void {

        $opts   = $this->parse_options( $args );
        $query  = $opts['query'] ?? null;
        $page   = isset( $opts['page'] ) ? (int) $opts['page'] : 1;
        $limit  = isset( $opts['limit'] ) ? (int) $opts['limit'] : 20;

        if ( empty( $query ) ) {
            $this->error( 'Usage: smliser settings search --query="keyword" [--page=1 --limit=20]' );
            return;
        }

        $this->start_timer();

        $results = smliser_settings()->search( $query, $page, $limit );

        if ( empty( $results ) ) {
            $this->error( "No settings found matching [{$query}]." );
            return;
        }

        $rows = [];

        foreach ( $results as $key => $value ) {
            $rows[] = [ Format::truncate( $key, 35 ), $this->format_option_value( $value ) ];
        }

        $this->info( "Search results for [{$query}]:" );

        $this->table(
            [ 'Option Name', 'Option Value' ],
            $rows
        );

        $this->done( 'Done', true );
    }

    /**
     * Show the active adapter name and quick usage hint.
     */
    protected function handle_default(): void {
        $this->info( 'Active Settings Adapter: ' . get_class( smliser_settings()->get_adapter() ) );
        $this->newline();
        $this->line( 'Run `smliser settings help` to see available subcommands.' );
    }

    /**
     * Print help for the settings command.
     */
    protected function handle_help(): void {
        $this->info( 'Settings Command' );
        $this->newline();
        $this->line( 'Usage:' );
        $this->line( '  smliser settings <subcommand> [arguments]' );
        $this->line( $this->help() );
        $this->newline();
    }

    /**
     * Handle an unrecognised subcommand.
     *
     * @param string $subcommand
     */
    protected function handle_unknown( string $subcommand ): void {
        $this->error( "Unknown subcommand: {$subcommand}" );
        $this->newline();
        $this->handle_help();
    }

    /*
    |--------------------------------------------
    | FORMATTING
    |--------------------------------------------
    */

    /**
     * Maximum display length for an option value in a table cell.
     */
    private const VALUE_DISPLAY_LENGTH = 60;

    /**
     * Format an option value for table display.
     *
     * Handles all PHP types and keeps the output within
     * VALUE_DISPLAY_LENGTH characters to prevent table overflow.
     *
     * @param mixed $value Raw value from the settings store.
     * @return string Display-safe string.
     */
    private function format_option_value( mixed $value ): string {
        if ( is_null( $value ) ) {
            return 'NULL';
        }

        if ( is_bool( $value ) ) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ( is_scalar( $value ) ) {
            return Format::truncate( (string) $value, self::VALUE_DISPLAY_LENGTH );
        }

        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }

        // Array (or object cast to array).
        $flat = Format::implode_deep( $value );
        return Format::truncate( $flat, self::VALUE_DISPLAY_LENGTH );
    }
}