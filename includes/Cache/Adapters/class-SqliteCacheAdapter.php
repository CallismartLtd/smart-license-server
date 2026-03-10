<?php
/**
 * SQLite Cache Adapter
 *
 * Implements the CacheAdapterInterface using the internal SqliteAdapter.
 *
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use SmartLicenseServer\Database\DatabaseAdapterInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * SQLite-backed cache adapter.
 */
class SqliteCacheAdapter implements CacheAdapterInterface {

    /**
     * Database adapter instance.
     *
     * @var DatabaseAdapterInterface
     */
    protected DatabaseAdapterInterface $db;

    /**
     * Cache table name.
     *
     * @var string
     */
    protected string $table;

    /**
     * Constructor.
     *
     * @param DatabaseAdapterInterface $db    Database adapter.
     * @param string                   $table Cache table name.
     */
    public function __construct( DatabaseAdapterInterface $db, string $table = 'smliser_cache' ) {
        $this->db    = $db;
        $this->table = $table;

        $this->initialize();
    }

    /**
     * Ensure the cache table exists.
     *
     * @return void
     */
    protected function initialize(): void {

        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                cache_key TEXT PRIMARY KEY,
                cache_value BLOB NOT NULL,
                expires_at INTEGER
            )
        ";

        $this->db->query( $sql );

        $this->db->query(
            "CREATE INDEX IF NOT EXISTS idx_{$this->table}_expires 
             ON {$this->table} (expires_at)"
        );
    }

    /**
     * Retrieve a cached value.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    public function get( string $key ): mixed {

        $row = $this->db->get_row(
            "SELECT cache_value, expires_at 
             FROM {$this->table} 
             WHERE cache_key = ? 
             LIMIT 1",
            [ $key ]
        );

        if ( ! $row ) {
            return false;
        }

        if ( $row['expires_at'] && $row['expires_at'] < time() ) {
            $this->delete( $key );
            return false;
        }

        return unserialize( $row['cache_value'] );
    }

    /**
     * Store a value in cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time-to-live in seconds.
     * @return bool
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {

        $expires = $ttl > 0 ? time() + $ttl : null;

        $data = [
            'cache_key'   => $key,
            'cache_value' => serialize( $value ),
            'expires_at'  => $expires,
        ];

        $existing = $this->has( $key );

        if ( $existing ) {
            return (bool) $this->db->update(
                $this->table,
                $data,
                [ 'cache_key' => $key ]
            );
        }

        return (bool) $this->db->insert(
            $this->table,
            $data
        );
    }

    /**
     * Delete a cache entry.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete( string $key ): bool {

        return (bool) $this->db->delete(
            $this->table,
            [ 'cache_key' => $key ]
        );
    }

    /**
     * Determine whether a cache entry exists.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function has( string $key ): bool {

        $row = $this->db->get_row(
            "SELECT expires_at 
             FROM {$this->table} 
             WHERE cache_key = ? 
             LIMIT 1",
            [ $key ]
        );

        if ( ! $row ) {
            return false;
        }

        if ( $row['expires_at'] && $row['expires_at'] < time() ) {
            $this->delete( $key );
            return false;
        }

        return true;
    }

    /**
     * Clear the entire cache.
     *
     * @return bool
     */
    public function clear(): bool {

        return (bool) $this->db->query(
            "DELETE FROM {$this->table}"
        );
    }
}