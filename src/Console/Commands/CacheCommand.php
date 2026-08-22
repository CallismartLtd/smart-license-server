<?php
/**
 * Cache command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Cache\CacheAdapterRegistry;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Utils\Format;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Manage and inspect the system cache.
 *
 * Usage:
 *   smliser cache stats
 *   smliser cache clear
 *   smliser cache get <key>
 *   smliser cache delete <key>
 *   smliser cache use-adapter <adapter_id>
 *   smliser cache help
 */
class CacheCommand extends AbstractCommand {

    public static function name(): string {
        return 'cache';
    }

    public static function description(): string {
        return 'Inspect and manage the system cache.';
    }

    public static function synopsis(): string {
        return 'smliser cache <subcommand> [key]';
    }

    public static function help(): string {
        return implode( PHP_EOL, [
            'Subcommands:',
            '  stats                        Show cache engine metrics.',
            '  clear                        Flush all cached data (confirms first).',
            '  get <key>                    Retrieve and display a specific cache key.',
            '  delete <key>                 Remove a specific key from the cache.',
            '  use-adapter <adapter_id>     Switch to a specific cache adapter.',
            '  help                         Show this help message.',
            '',
            'Examples:',
            '  smliser cache stats',
            '  smliser cache clear',
            '  smliser cache get smliser_some_key',
            '  smliser cache delete smliser_some_key',
            '  smliser cache use-adapter redis',
        ] );
    }

    /**
     * {@inheritdoc}
     *
     * Every handler here must stay public — AbstractCommandRouter
     * invokes these as callables from outside this class
     * (`$handler( $command_input )`), and PHP enforces method
     * visibility against the calling scope at invocation time, not
     * the scope the callable array was built in. A private handler
     * would compile fine and then fatal the moment it's dispatched.
     */
    public function get_subcommands(): array {
        return [
            'stats'       => [ $this, 'handle_stats' ],
            'clear'       => [ $this, 'handle_clear' ],
            'get'         => [ $this, 'handle_get' ],
            'delete'      => [ $this, 'handle_delete' ],
            'use-adapter' => [ $this, 'switch_adapter' ],
            'help'        => [ $this, 'handle_help' ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Only reached when no subcommand is given at all (`smliser cache`
     * with nothing after it) — AbstractCommandRouter already rejects
     * an unrecognised subcommand before this class is ever consulted,
     * so there's no "unknown subcommand" branch to handle here anymore.
     */
    public function run( CommandInput $input ): int {
        $this->output->info( 'Active Cache Adapter: ' . smliser_cache()->get_name() );
        $this->output->newline();
        $this->output->writeln( 'Run `smliser cache help` to see available subcommands.' );

        return 0;
    }

    /*
    |--------------------------------------------
    | SUBCOMMAND HANDLERS
    |--------------------------------------------
    */

    /**
     * Display cache engine statistics.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_stats( CommandInput $input ): int {
        $cache = smliser_cache();
        $stats = $cache->get_stats();

        $this->output->info( 'Cache Engine: ' . $cache->get_name() );
        $this->output->info( 'Connection status: ' . ( $cache->is_active() ? 'Connected' : 'Not Connected' ) );
        $this->output->newline();

        $this->output->table(
            [ 'Metric', 'Value' ],
            [
                [ 'Uptime',       Format::duration( (int) ( $stats->uptime ?? 0 ), 'short' ) ],
                [ 'Hits',         Format::number( (float) ( $stats->hits ?? 0 ) ) ],
                [ 'Misses',       Format::number( (float) ( $stats->misses ?? 0 ) ) ],
                [ 'Entries',      Format::number( (float) ( $stats->entries ?? 0 ) ) ],
                [ 'Memory Used',  Format::bytes( (int) ( $stats->memory_used ?? 0 ) ) ],
                [ 'Memory Total', Format::bytes( (int) ( $stats->memory_total ?? 0 ) ) ],
            ]
        );

        return 0;
    }

    /**
     * Flush the entire cache after confirmation.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_clear( CommandInput $input ): int {
        if ( ! $this->io->confirm( 'This will flush all cached data. Are you sure?' ) ) {
            $this->output->writeln( 'Aborted.' );
            return 0;
        }

        $stopwatch = new Stopwatch();
        $stopwatch->start();

        if ( ! smliser_cache()->clear() ) {
            $this->output->error( 'Failed to clear cache.' );
            return 1;
        }

        $this->output->success( sprintf( 'Cache cleared successfully. Completed in %ss.', $stopwatch->elapsed() ) );

        return 0;
    }

    /**
     * Retrieve and display a specific cache key.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_get( CommandInput $input ): int {
        $key = $input->get_argument( 0 );

        if ( empty( $key ) ) {
            $this->output->error( 'Usage: smliser cache get <key>' );
            return 1;
        }

        $value = smliser_cache()->get( $key );

        if ( false === $value ) {
            $this->output->warning( "Key [{$key}] not found in cache." );
            return 1;
        }

        $this->output->info( "Cache value for [{$key}]:" );
        $this->output->newline();

        if ( is_array( $value ) || is_object( $value ) ) {
            $this->output->table(
                [ 'Key', 'Value' ],
                array_map(
                    fn( $k, $v ) => [ $k, Format::decode( $v ) ],
                    array_keys( (array) $value ),
                    array_values( (array) $value )
                )
            );
        } else {
            $this->output->writeln( (string) $value );
        }

        return 0;
    }

    /**
     * Delete a specific cache key.
     *
     * @param CommandInput $input
     * @return int
     */
    public function handle_delete( CommandInput $input ): int {
        $key = $input->get_argument( 0 );

        if ( empty( $key ) ) {
            $this->output->error( 'Usage: smliser cache delete <key>' );
            return 1;
        }

        if ( ! $this->io->confirm( "Delete cache key [{$key}]?" ) ) {
            $this->output->writeln( 'Aborted.' );
            return 0;
        }

        if ( ! smliser_cache()->delete( $key ) ) {
            $this->output->error( "Failed to delete key [{$key}]. It may not exist." );
            return 1;
        }

        $this->output->success( "Key [{$key}] deleted from cache." );

        return 0;
    }

    /**
     * Change the cache adapter.
     *
     * @param CommandInput $input
     * @return int
     */
    public function switch_adapter( CommandInput $input ): int {
        $adapter_id = $input->get_argument( 0 );

        if ( empty( $adapter_id ) ) {
            $this->output->error( 'Usage: smliser cache use-adapter <adapter_id>' );
            return 1;
        }

        if ( ! CacheAdapterRegistry::instance()->has( $adapter_id ) ) {
            $this->output->error( sprintf( 'The cache adapter "%s" does not exist.', $adapter_id ) );
            return 1;
        }

        if ( ! CacheAdapterRegistry::instance()->set_default_adapter( $adapter_id ) ) {
            $this->output->error( 'Unable to set new adapter.' );
            return 1;
        }

        smliser_envProvider()->setCacheAdapter( true );

        $adapter_class = CacheAdapterRegistry::instance()->get( $adapter_id );
        $adapter_name  = $adapter_class ? $adapter_class::get_name() : $adapter_id;

        $this->output->success( sprintf( 'Now using %s.', $adapter_name ) );

        return 0;
    }
}