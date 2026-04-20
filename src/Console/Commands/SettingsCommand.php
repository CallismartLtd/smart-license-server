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

defined( 'SMLISER_ABSPATH' ) || exit;

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
    private function handle_set( array $args ) : void {
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
    private function handle_get( array $args  ): void {
        $key = $args[0] ?? null;

        if ( empty( $key ) || is_array( $key ) || str_starts_with( $key, '--' ) ) {
            $this->error( 'Usage: smliser settings get <key>' );
            return;
        }

        $value      = smliser_settings()->get( $key, null, true );
        $value      = Format::decode( $value );


        if ( ! is_scalar( $value ) ) {
            $value      = is_object( $value ) ? get_object_vars( $value ) : $value;
            $formatted  = is_array( $value ) 
            ? sprintf( 'Array [%s]', Format::implode_deep( $value ) ) : 
            sprintf( 'NULL' );
        } else {
            $formatted  = is_bool( $value ) ? sprintf( '%s', $value ? 'TRUE' : 'FALSE' ) : $value;
        }

        $this->info( "Settings value for [{$key}]:" );
        $this->table(
            ['Option Name', 'Option Value'],
            [
                [$key, $formatted]
            ]
        );
    }

    /**
     * Delete a specific settings key.
     *
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    private function handle_delete( array $args ): void {
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
     * Show the active adapter name and quick usage hint.
     */
    private function handle_default(): void {
        $this->info( 'Active Settings Adapter: ' . get_class( smliser_settings()->get_adapter() ) );
        $this->newline();
        $this->line( 'Run `smliser settings help` to see available subcommands.' );
    }

    /**
     * Print help for the settings command.
     */
    private function handle_help(): void {
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
    private function handle_unknown( string $subcommand ): void {
        $this->error( "Unknown subcommand: {$subcommand}" );
        $this->newline();
        $this->handle_help();
    }
}