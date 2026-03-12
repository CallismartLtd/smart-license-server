<?php
/**
 * Redis cache adapter.
 *
 * Provides a Redis-backed implementation of the CacheAdapterInterface.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use Redis;
use RedisException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Redis cache adapter.
 */
class RedisCacheAdapter implements CacheAdapterInterface {

    /**
     * Redis client instance.
     *
     * Null until connect() is called successfully.
     *
     * @var Redis|null
     */
    protected ?Redis $redis = null;

    /**
     * Optional cache key prefix.
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
    protected int $port = 6379;

    /**
     * Optional authentication password.
     *
     * @var string
     */
    protected string $password = '';

    /**
     * Redis database index (0–15).
     *
     * @var int
     */
    protected int $database = 0;

    /**
     * Constructor.
     *
     * The client is not connected at construction time.
     * Call set_settings() then any cache method (which connects lazily),
     * or use test() to validate settings before persisting them.
     */
    public function __construct() {}

    /*----------------------------------------------------------
     * CONNECTION
     *---------------------------------------------------------*/

    /**
     * Initialise and connect the Redis client using current settings.
     *
     * Idempotent — if a client is already connected this is a no-op.
     * Nulling $this->redis (done automatically by set_settings()) forces
     * a fresh connection on the next operation.
     *
     * @return bool True if connection and optional auth/select succeeded.
     */
    public function connect(): bool {
        if ( $this->redis instanceof Redis ) {
            return true;
        }

        try {
            $client = new Redis();
            $client->connect( $this->hostname, $this->port );

            if ( $this->password !== '' ) {
                $client->auth( $this->password );
            }

            if ( $this->database !== 0 ) {
                $client->select( $this->database );
            }

            $this->redis = $client;
            return true;
        } catch ( RedisException ) {
            $this->redis = null;
            return false;
        }
    }

    /**
     * Return true if the client is initialised.
     *
     * Does not guarantee the server is still reachable — use test() for that.
     *
     * @return bool
     */
    protected function is_connected(): bool {
        return $this->redis instanceof Redis;
    }

    /*----------------------------------------------------------
     * KEY BUILDER
     *---------------------------------------------------------*/

    /**
     * Prepend the configured prefix to a raw key.
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
     * Retrieve a cached value by key.
     *
     * Values are stored serialized so any PHP type round-trips correctly.
     * Returns false on a cache miss or when the client is not connected.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    public function get( string $key ): mixed {
        if ( ! $this->is_connected() ) {
            return false;
        }

        try {
            $value = $this->redis->get( $this->key( $key ) );

            return $value !== false ? unserialize( $value ) : false;
        } catch ( RedisException ) {
            return false;
        }
    }

    /**
     * Store a value in the cache.
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

        try {
            $payload = serialize( $value );
            $rkey    = $this->key( $key );

            return $ttl > 0
                ? (bool) $this->redis->setex( $rkey, $ttl, $payload )
                : (bool) $this->redis->set( $rkey, $payload );
        } catch ( RedisException ) {
            return false;
        }
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

        try {
            return (bool) $this->redis->del( $this->key( $key ) );
        } catch ( RedisException ) {
            return false;
        }
    }

    /**
     * Determine whether a cache key exists.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has( string $key ): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        try {
            return (bool) $this->redis->exists( $this->key( $key ) );
        } catch ( RedisException ) {
            return false;
        }
    }

    /**
     * Flush the selected Redis database.
     *
     * Only flushes the database index this adapter is configured to use,
     * not the entire Redis instance, to avoid wiping unrelated data.
     *
     * @return bool
     */
    public function clear(): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        try {
            return (bool) $this->redis->flushDB();
        } catch ( RedisException ) {
            return false;
        }
    }

    /**
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'redis';
    }

    public function get_name(): string {
        return 'Redis Cache';
    }

    public function get_settings_schema(): array {
        return [
            'hostname' => [
                'type'        => 'text',
                'label'       => 'Server Host',
                'required'    => true,
                'default'     => 'localhost',
                'description' => 'Redis server hostname or IP address.',
            ],
            'port' => [
                'type'        => 'number',
                'label'       => 'Port',
                'required'    => false,
                'default'     => 6379,
                'description' => 'Redis server port. Typically 6379.',
            ],
            'password' => [
                'type'        => 'password',
                'label'       => 'Password',
                'required'    => false,
                'default'     => '',
                'description' => 'Leave blank if the server has no AUTH configured.',
            ],
            'database' => [
                'type'        => 'number',
                'label'       => 'Database Index',
                'required'    => false,
                'default'     => 0,
                'description' => 'Redis logical database index (0–15). Defaults to 0.',
            ],
            'prefix' => [
                'type'        => 'text',
                'label'       => 'Key Prefix',
                'required'    => false,
                'default'     => '',
                'description' => 'Optional string prepended to every key to avoid collisions in shared environments.',
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

        if ( isset( $settings['password'] ) ) {
            $this->password = (string) $settings['password'];
        }

        if ( isset( $settings['database'] ) ) {
            $this->database = (int) $settings['database'];
        }

        if ( isset( $settings['prefix'] ) ) {
            $this->prefix = (string) $settings['prefix'];
        }

        // Force reconnection with the new values on the next operation.
        $this->redis = null;
    }

    /**
     * @inheritDoc
     */
    public function is_supported(): bool {
        return class_exists( Redis::class );
    }

    /**
    |----------------------
    | DIAGNOSTICS
    |----------------------
    */

    /**
     * Return runtime statistics from the Redis server.
     *
     * Calls INFO and parses the flat key:value response Redis returns.
     * We read from the 'stats', 'memory', and 'server' sections.
     *
     * Redis does not expose a single "entries in current DB" figure via INFO;
     * we query DBSIZE for an accurate key count scoped to the selected database.
     *
     * Returns a zero-default CacheStats when the client is not connected
     * or INFO returns nothing.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->is_connected() ) {
            return new CacheStats();
        }

        try {
            $info = $this->redis->info();

            if ( empty( $info ) ) {
                return new CacheStats();
            }

            // Keyspace hits/misses live under the 'stats' section.
            $hits  = (int) ( $info['keyspace_hits']   ?? 0 );
            $misses = (int) ( $info['keyspace_misses'] ?? 0 );

            // used_memory reflects actual heap allocation by Redis.
            $memory_used  = (int) ( $info['used_memory']      ?? 0 );
            // maxmemory is 0 when no limit is configured.
            $memory_total = (int) ( $info['maxmemory']         ?? 0 );

            // Uptime in seconds from the 'server' section.
            $uptime = (int) ( $info['uptime_in_seconds'] ?? 0 );

            // DBSIZE returns the key count for the currently selected database.
            $entries = (int) $this->redis->dbSize();

            return new CacheStats(
                hits         : $hits,
                misses       : $misses,
                entries      : $entries,
                memory_used  : $memory_used,
                memory_total : $memory_total,
                uptime       : $uptime,
                extra        : [
                    'redis_version'        => $info['redis_version']          ?? '',
                    'connected_clients'    => (int) ( $info['connected_clients']    ?? 0 ),
                    'evicted_keys'         => (int) ( $info['evicted_keys']         ?? 0 ),
                    'expired_keys'         => (int) ( $info['expired_keys']         ?? 0 ),
                    'used_memory_human'    => $info['used_memory_human']       ?? '',
                    'maxmemory_policy'     => $info['maxmemory_policy']        ?? '',
                    'connected_slaves'     => (int) ( $info['connected_slaves']     ?? 0 ),
                    'database'             => $this->database,
                ],
            );
        } catch ( RedisException ) {
            return new CacheStats();
        }
    }

    /**
     * Test whether Redis is reachable and operational with the supplied settings.
     *
     * Creates a temporary isolated Redis client so the live $this->redis
     * connection and the live cache are never affected. Applies the supplied
     * settings to the sandbox only, performs a write → read → delete
     * round-trip with a short TTL, verifies the value, then discards the client.
     *
     * This method must never throw — all exceptions resolve to false.
     *
     * @param array<string, mixed> $settings Settings shaped like get_settings_schema().
     * @return bool True if Redis is reachable and all probe operations succeed.
     */
    public function test( array $settings = [] ): bool {
        if ( ! $this->is_supported() ) {
            return false;
        }

        try {
            $hostname = (string) ( $settings['hostname'] ?? $this->hostname );
            $port     = (int)    ( $settings['port']     ?? $this->port );
            $password = (string) ( $settings['password'] ?? $this->password );
            $database = (int)    ( $settings['database'] ?? $this->database );
            $prefix   = (string) ( $settings['prefix']   ?? $this->prefix );

            $sandbox = new Redis();
            $sandbox->connect( $hostname, $port );

            if ( $password !== '' ) {
                $sandbox->auth( $password );
            }

            if ( $database !== 0 ) {
                $sandbox->select( $database );
            }

            $probe   = $prefix . '__smliser_redis_probe_' . \uniqid( '', true );
            $payload = serialize( 1 );

            $stored  = (bool) $sandbox->setex( $probe, 10, $payload );
            $raw     = $sandbox->get( $probe );
            $value   = $raw !== false ? unserialize( $raw ) : null;
            $deleted = (bool) $sandbox->del( $probe );

            return $stored && $value === 1 && $deleted;
        } catch ( \Throwable ) {
            return false;
        }
    }
}