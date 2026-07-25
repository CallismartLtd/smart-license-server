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

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Utils\Format;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Manage and inspect the system settings.
 *
 * Usage:
 *   smliser settings set --name="email_provider" --value="smtp"
 *   smliser settings get <name>
 *   smliser settings delete <name>
 *   smliser settings help
 */
class SettingsCommand extends AbstractCommand {
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


    public function run( CommandInput $input ): int {
        $this->output->info( 'Active Settings Adapter: ' . get_class( smliser_settings()->get_adapter() ) );
        $this->output->writeln( 'Run `smliser settings help` to see available subcommands.' );

        return 0;
    }

    public function get_subcommands() : array {
        return [
            'set'    => [$this, 'handle_set'],
            'get'    => [$this, 'handle_get'],
            'delete' => [$this, 'handle_delete'],
            'list'   => [$this, 'handle_list'],
            'search' => [$this, 'handle_search'],
            'help'   => [$this, 'handle_help'],
        ];
    }

    /*
    |--------------------------------------------
    | SUBCOMMAND HANDLERS
    |--------------------------------------------
    */

    /**
     * Set or update a settings in the database
     * 
     * @param CommandInput $input.
     */
    public function handle_set( CommandInput $input ) : int {
        $name   = $input->get_argument( 'name', null );
        $value  = $input->get_argument( 'value', null );

        if ( empty( $name ) ) {
            $this->output->error( 'Usage: smliser settings set --name=option_name --value=option_value' );
            return 1;
        }

        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $result = smliser_settings()->set( $name, $value );

        if ( $result ) {
            $this->output->success( sprintf( 'Saved successfully. Completed in %ss', $stopwatch->elapsed() ) );
            return 0;
        } 
    
        $this->output->error( 'Unable to save to the database.' );

        return 1;
    }

    /**
     * Retrieve and display a specific settings key.
     *
     * @param CommandInput $input
     */
    public function handle_get( CommandInput $input  ): int {
        $key = $input->get_argument( 0, null );

        if ( empty( $key ) || is_array( $key ) || str_starts_with( $key, '--' ) ) {
            $this->output->error( 'Usage: smliser settings get <key>' );
            return 1;
        }

        $key        = (string) $key;
        $value      = smliser_settings()->get( $key, null, true );
        $formatted  = $this->format_option_value( $value );

        $this->output->info( "Settings value for [{$key}]:" );
        $this->output->table(
            ['Option Name', 'Option Value'],
            [
                [Format::truncate( $key, 35 ), $formatted]
            ]
        );

        return 0;
    }

    /**
     * Delete a specific settings key.
     *
     * @param array $args Subcommand arguments (excluding the subcommand itself).
     */
    public function handle_delete( array $args ): void {
        $key = $args[0] ?? null;

        if ( empty( $key ) || is_array( $key ) || str_starts_with( $key, '--' ) ) {
            $this->output->error( 'Usage: smliser settings delete <key>' );
            return;
        }

        if ( ! $this->io->confirm( "Delete settings key [{$key}]?" ) ) {
            $this->output->writeln( 'Aborted.' );
            return;
        }

        smliser_settings()->delete( $key )
            ? $this->output->success( "Key [{$key}] deleted from settings." )
            : $this->output->error( "Failed to delete key [{$key}]. It may not exist." );
    }

    /**
     * List settings (paginated).
     *
     * Usage:
     *   smliser settings list --page=1 --limit=20
     */
    public function handle_list( CommandInput $input ): int {

        $page  = (int) $input->get_argument( 'page', 1 );
        $limit = (int) $input->get_argument( 'limit', 30 );

        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $results = smliser_settings()->all( $page, $limit );

        if ( empty( $results ) ) {
            $this->output->error( 'No settings found.' );
            return 1;
        }

        $rows = [];

        foreach ( $results as $key => $value ) {
            $rows[] = [ Format::truncate( $key, 35 ), $this->format_option_value( $value ) ];
        }

        $this->output->table(
            [ 'Option Name', 'Option Value' ],
            $rows
        );

        $this->output->success( sprintf( 'Completed in %ss', $stopwatch->elapsed() ) );

        return 0;
    }

    /**
     * Search settings by key name.
     *
     * Usage:
     *   smliser settings search --query="license" --page=1 --limit=20
     */
    public function handle_search( CommandInput $input ): int {
        $query  = $input->get_argument( 'query', null );

        $page  = (int) $input->get_argument( 'page', 1 );
        $limit = (int) $input->get_argument( 'limit', 30 );

        if ( empty( $query ) ) {
            $this->output->error( 'Usage: smliser settings search --query="keyword" [--page=1 --limit=20]' );
            return 1;
        }

        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $results = smliser_settings()->search( $query, $page, $limit );

        if ( empty( $results ) ) {
            $this->output->error( "No settings found matching [{$query}]." );
            return 1;
        }

        $rows = [];

        foreach ( $results as $key => $value ) {
            $rows[] = [ Format::truncate( $key, 35 ), $this->format_option_value( $value ) ];
        }

        $this->output->info( "Search results for [{$query}]:" );

        $this->output->table(
            [ 'Option Name', 'Option Value' ],
            $rows
        );

        $this->output->success( sprintf( 'Completed in %ss', $stopwatch->elapsed() ) );

        return 0;
    }

    /**
     * Print help for the settings command.
     */
    protected function handle_help(): int {
        $this->output->info( 'Settings Command' );
        $this->output->newline();
        $this->output->info( 'Usage:' );
        $this->output->writeln( '  smliser settings <subcommand> [arguments]' );
        $this->output->writeln( $this->help() );
        
        $this->output->newline();

        return 0;
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