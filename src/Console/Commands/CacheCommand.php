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
use SmartLicenseServer\Console\CLIAwareTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Utils\Format;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Manage and inspect the system cache.
 *
 * Usage:
 *   smliser cache stats
 *   smliser cache clear
 *   smliser cache get <key>
 *   smliser cache delete <key>
 *   smliser cache help
 */
class CacheCommand implements CommandInterface {
    use CLIAwareTrait;

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
        ]);
    }


    public function execute( array $args = [] ): void {
        $subcommand = $args[0] ?? null;

        match ( $subcommand ) {
            'stats'         => $this->handle_stats(),
            'clear'         => $this->handle_clear(),
            'get'           => $this->handle_get( $args[1] ?? null ),
            'delete'        => $this->handle_delete( $args[1] ?? null ),
            'use-adapter'   => $this->switch_adapter( $args[1] ?? null ),
            'help'          => $this->handle_help(),
            null            => $this->handle_default(),
            default         => $this->handle_unknown( $subcommand ),
        };
    }

    /*
    |--------------------------------------------
    | SUBCOMMAND HANDLERS
    |--------------------------------------------
    */

    /**
     * Display cache engine statistics.
     */
    private function handle_stats(): void {
        $cache = smliser_cache();
        $stats = $cache->get_stats();

        $this->info( 'Cache Engine: ' . $cache->get_name() );
        $this->newline();

        $this->table(
            [ 'Metric', 'Value' ],
            [
                [ 'Uptime',       $this->format_uptime( $stats->uptime ?? 0 ) ],
                [ 'Hits',         number_format( (float) ( $stats->hits ?? 0 ) ) ],
                [ 'Misses',       number_format( (float) ( $stats->misses ?? 0 ) ) ],
                [ 'Entries',      number_format( (float) ( $stats->entries ?? 0 ) ) ],
                [ 'Memory Used',  $this->format_bytes( $stats->memory_used ?? 0 ) ],
                [ 'Memory Total', $this->format_bytes( $stats->memory_total ?? 0 ) ],
            ]
        );
    }

    /**
     * Flush the entire cache after confirmation.
     */
    private function handle_clear(): void {
        if ( ! $this->confirm( 'This will flush all cached data. Are you sure?' ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        $this->start_timer();

        smliser_cache()->clear()
            ? $this->done( 'Cache cleared successfully.' )
            : $this->error( 'Failed to clear cache.' );
    }

    /**
     * Retrieve and display a specific cache key.
     *
     * @param string|null $key
     */
    private function handle_get( ?string $key ): void {
        if ( empty( $key ) ) {
            $this->error( 'Usage: smliser cache get <key>' );
            return;
        }

        $value = smliser_cache()->get( $key );

        if ( $value === false ) {
            $this->warning( "Key [{$key}] not found in cache." );
            return;
        }

        $this->info( "Cache value for [{$key}]:" );
        $this->newline();

        if ( is_array( $value ) || is_object( $value ) ) {
            $this->table(
                [ 'Key', 'Value' ],
                array_map(
                    fn( $k, $v ) => [ $k, Format::decode( $v )  ],
                    array_keys( (array) $value ),
                    array_values( (array) $value )
                )
            );
        } else {
            $this->line( (string) $value );
        }
    }

    /**
     * Delete a specific cache key.
     *
     * @param string|null $key
     */
    private function handle_delete( ?string $key ): void {
        if ( empty( $key ) ) {
            $this->error( 'Usage: smliser cache delete <key>' );
            return;
        }

        if ( ! $this->confirm( "Delete cache key [{$key}]?" ) ) {
            $this->line( 'Aborted.' );
            return;
        }

        smliser_cache()->delete( $key )
            ? $this->success( "Key [{$key}] deleted from cache." )
            : $this->error( "Failed to delete key [{$key}]. It may not exist." );
    }

    /**
     * Change the cache adapter.
     *
     * @param string|null $adapter_id
     */
    private function switch_adapter( ?string $adapter_id ): void {
        if ( empty( $adapter_id ) ) {
            $this->error( 'Usage: smliser cache use-adapter <adapter_id>' );
            return;
        }

        if ( ! CacheAdapterRegistry::instance()->has( $adapter_id ) ) {
            $this->error( sprintf( 'The cache adapter "%s" does not exist.', $adapter_id ) );
            return;
        }

        $success    = CacheAdapterRegistry::instance()->set_default_adapter( $adapter_id );

        if ( $success ) {
            $adapter_class  = CacheAdapterRegistry::instance()->get( $adapter_id );
            $adapter_name   = $adapter_class ? $adapter_class::get_name() : $adapter_id;
            $message        = sprintf( 'Now using %s.', $adapter_name );

            if( \is_interactive_shell() ) {
                $message .= ' Please restart CLI to effect changes.';
            }
            $this->success( $message );
        } else {
            $this->error( 'Unable to set new adapter.' );
        }
    }

    /**
     * Show the active adapter name and quick usage hint.
     */
    private function handle_default(): void {
        $this->info( 'Active Cache Adapter: ' . smliser_cache()->get_name() );
        $this->newline();
        $this->line( 'Run `smliser cache help` to see available subcommands.' );
    }

    /**
     * Print help for the cache command.
     */
    private function handle_help(): void {
        $this->info( 'Cache Command' );
        $this->newline();
        $this->line( 'Usage:' );
        $this->line( '  smliser cache <subcommand> [argument]' );
        $this->newline();

        $this->table(
            [ 'Subcommand', 'Argument', 'Description' ],
            [
                [ 'stats',  '',      'Show cache engine metrics.' ],
                [ 'clear',  '',      'Flush all cached data (confirms first).' ],
                [ 'get',    '<key>', 'Retrieve and display a specific cache key.' ],
                [ 'delete', '<key>', 'Remove a specific key from the cache.' ],
                [ 'use-adapter', '<adapter_id>', 'Switch to a specific cache adapter.' ],
                [ 'help',   '',      'Show this help message.' ],
            ]
        );

        $this->newline();
        $this->line( 'Examples:' );
        $this->line( '  smliser cache stats' );
        $this->line( '  smliser cache clear' );
        $this->line( '  smliser cache get smliser_some_key' );
        $this->line( '  smliser cache delete smliser_some_key' );
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

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Format bytes into a human-readable string.
     *
     * @param int $bytes
     * @return string
     */
    private function format_bytes( int $bytes ): string {
        if ( $bytes <= 0 ) {
            return '0 B';
        }

        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $i     = (int) floor( log( $bytes, 1024 ) );

        return round( $bytes / pow( 1024, $i ), 2 ) . ' ' . $units[ $i ];
    }

    /**
     * Format seconds into a human-readable uptime string.
     *
     * @param int $seconds
     * @return string
     */
    private function format_uptime( int $seconds ): string {
        if ( $seconds < 1 ) {
            return 'N/A';
        }

        $days    = intdiv( $seconds, 86400 );
        $hours   = intdiv( $seconds % 86400, 3600 );
        $minutes = intdiv( $seconds % 3600, 60 );

        $parts = [];
        if ( $days > 0 )    $parts[] = "{$days}d";
        if ( $hours > 0 )   $parts[] = "{$hours}h";
        if ( $minutes > 0 ) $parts[] = "{$minutes}m";

        return $parts ? implode( ' ', $parts ) : '< 1m';
    }
}