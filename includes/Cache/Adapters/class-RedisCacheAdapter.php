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

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Redis cache adapter.
 */
class RedisCacheAdapter implements CacheAdapterInterface {

    /**
     * Redis client instance.
     *
     * @var Redis
     */
    protected Redis $redis;

    /**
     * Optional cache key prefix.
     *
     * @var string
     */
    protected string $prefix = '';

    /**
     * Server hostname
     * 
     * @var string
     */
    protected string $hostname  = '';

    /**
     * Port
     * 
     * @var int
     */
    protected int $port = 6379;

    /**
     * Constructor.
     *
     * @param Redis  $redis  Redis client instance.
     * @param string $prefix Optional key prefix.
     */
    public function __construct() {}

    /**
     * Build the full cache key.
     *
     * @param string $key Cache key.
     * @return string
     */
    protected function key( string $key ): string {
        return $this->prefix . $key;
    }

    /**
     * Retrieve a cached value by key.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    public function get( string $key ): mixed {
        $value = $this->redis->get( $this->key( $key ) );

        if ( false === $value ) {
            return false;
        }

        return unserialize( $value );
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
        $payload = serialize( $value );
        $key     = $this->key( $key );

        if ( $ttl > 0 ) {
            return (bool) $this->redis->setex( $key, $ttl, $payload );
        }

        return (bool) $this->redis->set( $key, $payload );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete( string $key ): bool {
        return (bool) $this->redis->del( $this->key( $key ) );
    }

    /**
     * Determine whether a cache key exists.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has( string $key ): bool {
        return (bool) $this->redis->exists( $this->key( $key ) );
    }

    /**
     * Clear the entire cache.
     *
     * WARNING: This flushes the entire Redis database.
     *
     * @return bool
     */
    public function clear(): bool {
        return (bool) $this->redis->flushDB();
    }

    /**
    |----------------------
    | ADAPTER IDENTITY
    |----------------------
    */

    public function get_id() : string {
        return 'redis';
    }

    public function get_name() : string {
        return 'Redis Cache';
    }

    public function get_settings_schema() : array {
        return [
            'hostname' => [
                'type'        => 'text',
                'label'       => 'Server Host',
                'required'    => true,
                'description' => 'Redis server hostname. e.g. localhost',
            ],
            'port' => [
                'type'        => 'number',
                'label'       => 'Port',
                'required'    => false,
                'description' => 'Typically 6379.',
            ],
        ];
    }

    public function set_settings( array $settings ) : void {
        if ( isset( $settings['hostname'] ) ) {
            $this->hostname = (string) $settings['hostname'];
        }

        if ( isset( $settings['port'] ) ) {
            $this->port = (int) $settings['port'];
        }
    }

    public function is_supported() : bool {
        return class_exists( Redis::class );
    }
}