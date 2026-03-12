<?php
/**
 * Memcached cache adapter.
 *
 * Provides a Memcached-backed implementation of the CacheAdapterInterface.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use Memcached;
use SmartLicenseServer\Cache\CacheStats;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Memcached cache adapter.
 */
class MemcachedCacheAdapter implements CacheAdapterInterface {

    /**
     * Memcached client instance.
     *
     * Null until connect() is called successfully.
     *
     * @var Memcached|null
     */
    protected ?Memcached $memcached = null;

    /**
     * Cache key prefix.
     *
     * @var string
     */
    protected string $prefix = '';

    /**
     * Server hostname.
     *
     * @var string
     */
    protected string $hostname = 'localhost';

    /**
     * Server port.
     *
     * @var int
     */
    protected int $port = 11211;

    /**
     * Constructor.
     *
     * The client is not connected at construction time.
     * Call set_settings() then any cache method (which calls connect() lazily),
     * or rely on test() to validate settings before persisting them.
     */
    public function __construct() {}

    /*----------------------------------------------------------
     * CONNECTION
     *---------------------------------------------------------*/

    /**
     * Initialise and connect the Memcached client using current settings.
     *
     * Idempotent — if a client is already connected this is a no-op.
     * Re-call after set_settings() to reconnect with new credentials.
     *
     * @return bool True if the server was added successfully.
     */
    public function connect(): bool {
        if ( $this->memcached instanceof Memcached ) {
            return true;
        }

        if ( ! isset( $this->hostname ) || empty( $this->port ) ) {
            return false;
        }

        $this->memcached = new Memcached();

        return $this->memcached->addServer( $this->hostname, $this->port );
    }

    /**
     * Return true if the client is initialised.
     *
     * Does not guarantee the server is reachable — use test() for that.
     *
     * @return bool
     */
    protected function is_connected(): bool {
        if ( $this->memcached === null ) {
            $this->connect();
        }
        
        return $this->memcached instanceof Memcached;
    }

    /*----------------------------------------------------------
     * KEY BUILDER
     *---------------------------------------------------------*/

    /**
     * Prepend the configured prefix to a key.
     *
     * @param string $key Raw cache key.
     * @return string
     */
    protected function key( string $key ): string {
        return $this->prefix . $key;
    }

    /*----------------------------------------------------------
     * CACHE OPERATIONS
     *---------------------------------------------------------*/

    /**
     * Retrieve a cached value.
     *
     * Returns false on a cache miss OR when the client is not connected.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    public function get( string $key ): mixed {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $value = $this->memcached->get( $this->key( $key ) );

        return $this->memcached->getResultCode() === Memcached::RES_SUCCESS
            ? $value
            : false;
    }

    /**
     * Store a value in cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time-to-live in seconds. 0 = forever.
     * @return bool
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        return $this->memcached->set( $this->key( $key ), $value, $ttl );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete( string $key ): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        return $this->memcached->delete( $this->key( $key ) );
    }

    /**
     * Determine whether a key exists in cache.
     *
     * Performs a real get and inspects the result code rather than
     * issuing a separate lookup, since Memcached has no native exists().
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has( string $key ): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $this->memcached->get( $this->key( $key ) );

        return $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    /**
     * Flush all keys from the Memcached server.
     *
     * @return bool
     */
    public function clear(): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        return $this->memcached->flush();
    }

    /**
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'memcached';
    }

    public function get_name(): string {
        return 'Memcached';
    }

    public function get_settings_schema(): array {
        return [
            'hostname' => [
                'type'        => 'text',
                'label'       => 'Server Host',
                'required'    => true,
                'default'     => 'localhost',
                'description' => 'Memcached server hostname or IP address.',
            ],
            'port' => [
                'type'        => 'number',
                'label'       => 'Port',
                'required'    => false,
                'default'     => 11211,
                'description' => 'Memcached server port. Typically 11211.',
            ],
            'prefix' => [
                'type'        => 'text',
                'label'       => 'Key Prefix',
                'required'    => false,
                'default'     => '',
                'description' => 'Optional string prepended to every cache key to avoid collisions in shared environments.',
            ],
        ];
    }

    /**
     * Apply adapter configuration.
     *
     * Resets the client so the next operation reconnects with the new settings.
     *
     * @param array<string, mixed> $settings
     * @return void
     */
    public function set_settings( array $settings ): void {
        if ( isset( $settings['hostname'] ) ) {
            $this->hostname = (string) $settings['hostname'];
        }

        if ( isset( $settings['port'] ) ) {
            $this->port = (int) $settings['port'];
        }

        if ( isset( $settings['prefix'] ) ) {
            $this->prefix = (string) $settings['prefix'];
        }

        // Force reconnection with the new values on the next operation.
        $this->memcached = null;
    }

    /**
     * @inheritDoc
     */
    public function is_supported(): bool {
        return class_exists( Memcached::class );
    }

    /**
    |----------------------
    | DIAGNOSTICS
    |----------------------
    */

    /**
     * Return runtime statistics from the Memcached server.
     *
     * Calls getStats() which returns per-server stats keyed by "host:port".
     * We sum across all servers so the returned CacheStats reflects the
     * entire pool when multiple servers are configured.
     *
     * curr_connections from Memcached's stats includes listener sockets
     * (one per bound interface — IPv4, IPv6, or both), so it is stored raw
     * in the extra bag for reference while client_connections gives the
     * true PHP client count via get_client_connections().
     *
     * Returns a zero-default CacheStats when the client is not connected
     * or the server returns no data.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->is_connected() ) {
            return new CacheStats();
        }

        $raw = $this->memcached->getStats();

        if ( empty( $raw ) ) {
            return new CacheStats();
        }

        // Aggregate across all servers in the pool.
        $hits         = 0;
        $misses       = 0;
        $entries      = 0;
        $memory_used  = 0;
        $memory_total = 0;
        $uptime       = 0;
        $evictions    = 0;
        $connections  = 0;

        foreach ( $raw as $server_stats ) {
            $hits         += (int) ( $server_stats['get_hits']         ?? 0 );
            $misses       += (int) ( $server_stats['get_misses']       ?? 0 );
            $entries      += (int) ( $server_stats['curr_items']       ?? 0 );
            $memory_used  += (int) ( $server_stats['bytes']            ?? 0 );
            $evictions    += (int) ( $server_stats['evictions']        ?? 0 );
            $connections  += (int) ( $server_stats['curr_connections'] ?? 0 );

            // Use the longest uptime across the pool as the representative value.
            $uptime = max( $uptime, (int) ( $server_stats['uptime'] ?? 0 ) );

            // limit_maxbytes is the configured memory ceiling per server.
            $memory_total += (int) ( $server_stats['limit_maxbytes'] ?? 0 );
        }

        return new CacheStats(
            hits         : $hits,
            misses       : $misses,
            entries      : $entries,
            memory_used  : $memory_used,
            memory_total : $memory_total,
            uptime       : $uptime,
            extra        : [
                'evictions'          => $evictions,
                'curr_connections'   => $connections,              // raw — includes listener sockets
                'client_connections' => $this->get_client_connections(), // true PHP client count
                'server_count'       => count( $raw ),
                'servers'            => array_keys( $raw ),
            ],
        );
    }

    /**
     * Return the number of true PHP client connections to the Memcached pool.
     *
     * Memcached's curr_connections stat includes its own listener sockets
     * (one per bound interface — typically 2 on dual-stack IPv4+IPv6 servers),
     * which inflates the number shown to users. This method queries
     * getStats('conns') — the per-connection detail available in Memcached
     * 1.6+ — and counts only sockets that are NOT in conn_listening state,
     * giving an accurate client-only count regardless of the server's
     * network configuration.
     *
     * Falls back to 0 if the extended stats are unavailable (older Memcached
     * versions or permission restrictions).
     *
     * @return int
     */
    private function get_client_connections(): int {
        if ( ! $this->is_connected() ) {
            return 0;
        }

        try {
            $conns = $this->memcached->getStats( 'conns' );

            if ( empty( $conns ) || ! is_array( $conns ) ) {
                return 0;
            }

            $client_count = 0;

            foreach ( $conns as $server_conns ) {
                if ( ! is_array( $server_conns ) ) {
                    continue;
                }

                foreach ( $server_conns as $key => $value ) {
                    // Each connection exposes a "<fd>:state" key.
                    // Listener sockets report "conn_listening" — exclude those.
                    if ( str_ends_with( (string) $key, ':state' )
                         && $value !== 'conn_listening' ) {
                        $client_count++;
                    }
                }
            }

            return $client_count;

        } catch ( \Throwable ) {
            return 0;
        }
    }

    /**
     * Test whether Memcached is reachable and operational with the supplied settings.
     *
     * Creates a temporary isolated client so the live $this->memcached
     * connection and the live cache are never touched. Applies the supplied
     * settings to the sandbox only, performs a write → read → delete
     * round-trip, then discards the sandbox.
     *
     * @param array<string, mixed> $settings Settings shaped like get_settings_schema().
     * @return bool True if the server is reachable and all three probe operations succeed.
     */
    public function test( array $settings = [] ): bool {
        if ( ! $this->is_supported() ) {
            return false;
        }

        try {
            $hostname = (string) ( $settings['hostname'] ?? $this->hostname );
            $port     = (int)    ( $settings['port']     ?? $this->port );
            $prefix   = (string) ( $settings['prefix']   ?? $this->prefix );

            $sandbox = new Memcached();
            $sandbox->addServer( $hostname, $port );

            $probe = $prefix . '__smliser_memcached_probe_' . \uniqid( '', true );

            $stored  = $sandbox->set( $probe, 1, 10 );
            $value   = $sandbox->get( $probe );
            $fetched = $sandbox->getResultCode() === Memcached::RES_SUCCESS;
            $deleted = $sandbox->delete( $probe );

            return $stored && $fetched && $value === 1 && $deleted;
        } catch ( \Throwable ) {
            return false;
        }
    }
}