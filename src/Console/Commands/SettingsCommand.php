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
            '   set                 Insert or update the value of an option name.',
            '   get  <name>         Get the entry of an option name.',
            '   delete <name>       Remove a specific option entry from the table.',
            '   help                Show this help message.',
            '   list                List entries in options table.',
            '   search              Search the options table with a specific term.',
            'Options:',
            '--name     The option name.',
            '--value    The option value.',
            '--query    The term used to search the option table.',
            '--page     The current pagination number.',
            '--limit    The maximum number of results to return for listing and search results.',
            '',
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
        $name   = $input->get_option( 'name', null );
        $value  = $input->get_option( 'value', null );

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
        $name = $input->get_argument( 0, $input->get_option( 'name', null ) );

        if ( empty( $name ) || is_array( $name ) || str_starts_with( $name, '--' ) ) {
            $this->output->error( 'Usage: smliser settings get <name>' );
            return 1;
        }

        $name       = (string) $name;
        $value      = smliser_settings()->get( $name, null, true );
        $formatted  = $this->format_option_value( $value );

        $this->output->info( "Settings value for [{$name}]:" );
        $this->output->table(
            ['Option Name', 'Option Value'],
            [
                [Format::truncate( $name, 35 ), $formatted]
            ]
        );

        return 0;
    }

    /**
     * Delete a specific settings key.
     *
     * @param CommandInput $input).
     * @return int
     */
    public function handle_delete( CommandInput $input ): int {
        $key = $input->get_argument( 0, $input->get_option( 'name', null ) );

        if ( empty( $key ) || is_array( $key ) || str_starts_with( $key, '--' ) ) {
            $this->output->error( 'Usage: smliser settings delete <key>' );
            return 1;
        }

        if ( ! $this->io->confirm( "Delete settings key [{$key}]?" ) ) {
            $this->output->writeln( 'Aborted.' );
            return 0;
        }

        if ( smliser_settings()->delete( $key ) ) {
            $this->output->success( "Key [{$key}] deleted from settings." );
            return 0;
        }
        
        $this->output->error( "Failed to delete key [{$key}]. It may not exist." );

        return 1;
    }

    /**
     * List settings (paginated).
     *
     * Usage:
     *   smliser settings list --page=1 --limit=20
     */
    public function handle_list( CommandInput $input ): int {

        $page  = (int) $input->get_option( 'page', 1 );
        $limit = (int) $input->get_option( 'limit', 30 );

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
        $query  = $input->get_option( 'query', null );

        $page  = (int) $input->get_option( 'page', 1 );
        $limit = (int) $input->get_option( 'limit', 30 );

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