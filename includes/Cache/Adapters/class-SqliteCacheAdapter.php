<?php
/**
 * SQLite Cache Adapter class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache\Adapters;

use Exception;
use LogicException;
use SQLite3;
use SQLite3Stmt;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * SQLite-backed cache adapter.
 *
 * ## Performance design
 *
 * - All expiry checks happen at the SQL layer via WHERE expires_at conditions
 *   so PHP never receives stale rows — no post-fetch expiry checks needed.
 * - Expired entry deletion is atomic with the read that discovers expiry:
 *   get() uses a single SELECT that excludes expired rows; a periodic
 *   DELETE prunes the table rather than deleting one key at a time.
 * - set() uses INSERT ... ON CONFLICT upsert — one round trip, no pre-check.
 * - Prepared statements are cached in $this->stmts so repeated calls to
 *   get/set/delete/has reuse the same SQLite3Stmt objects.
 * - Probabilistic pruning (1% of reads rather than writes) amortises the
 *   cost of the cleanup DELETE across the workload without delaying writes.
 * - initialize() only runs once per instance — ensure_db() gates everything
 *   with a fast isset() check on subsequent calls.
 */
class SqliteCacheAdapter implements CacheAdapterInterface {

    /**
     * The SQLite3 connection.
     *
     * @var SQLite3
     */
    protected SQLite3 $db;

    /**
     * Cache table name.
     *
     * @var string
     */
    protected string $table = 'smliser_cache';

    /**
     * Absolute path to the cache directory.
     *
     * @var string
     */
    protected string $path;

    /**
     * Prepared statement cache.
     *
     * Keyed by an arbitrary label so each statement is prepared once
     * per connection and reused on subsequent calls.
     *
     * @var array<string, SQLite3Stmt>
     */
    private array $stmts = [];

    /**
     * Constructor.
     */
    public function __construct() {}

    /*
    |---------------------
    | Public interface
    |---------------------
    */

    /**
     * Retrieve a cached value.
     *
     * Expiry is enforced entirely at the SQL layer — no PHP-side
     * time comparison needed. A 1-in-100 probabilistic prune runs
     * on reads to amortise cleanup cost without blocking writes.
     *
     * @param  string $key
     * @return mixed|false  The stored value, or false on miss/expiry.
     */
    public function get( string $key ): mixed {
        if ( ! $this->ensure_db() ) {
            return false;
        }

        // Probabilistic prune — 1% of reads trigger an async-style
        // cleanup of all expired rows.
        if ( mt_rand( 1, 100 ) === 1 ) {
            $this->prune_expired();
        }

        $stmt = $this->stmt(
            'get',
            "SELECT cache_value
             FROM {$this->table}
             WHERE cache_key = ?
               AND (expires_at IS NULL OR expires_at > ?)
             LIMIT 1"
        );

        if ( ! $stmt ) {
            return false;
        }

        $stmt->bindValue( 1, $key,   SQLITE3_TEXT );
        $stmt->bindValue( 2, time(), SQLITE3_INTEGER );

        $result = $stmt->execute();
        $stmt->reset();

        if ( ! $result ) {
            return false;
        }

        $row = $result->fetchArray( SQLITE3_ASSOC );
        $result->finalize();

        if ( ! $row ) {
            return false;
        }

        return unserialize( $row['cache_value'], [ 'allowed_classes' => true ] );
    }

    /**
     * Store a value in cache.
     *
     * Uses INSERT ... ON CONFLICT upsert so a single SQL statement
     * handles both insert and update — no pre-check for existing keys.
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $ttl   Seconds until expiry. 0 means no expiry.
     * @return bool
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        if ( ! $this->ensure_db() ) {
            return false;
        }

        $expires_at = $ttl > 0 ? time() + $ttl : null;
        $serialized = serialize( $value );

        $stmt = $this->stmt(
            'set',
            "INSERT INTO {$this->table} (cache_key, cache_value, expires_at)
             VALUES (?, ?, ?)
             ON CONFLICT(cache_key) DO UPDATE SET
                 cache_value = excluded.cache_value,
                 expires_at  = excluded.expires_at"
        );

        if ( ! $stmt ) {
            return false;
        }

        $stmt->bindValue( 1, $key,        SQLITE3_TEXT );
        $stmt->bindValue( 2, $serialized, SQLITE3_BLOB );
        $stmt->bindValue( 3, $expires_at, $expires_at === null ? SQLITE3_NULL : SQLITE3_INTEGER );

        $result = $stmt->execute();
        $stmt->reset();

        if ( ! $result ) {
            return false;
        }

        $result->finalize();

        // changes() returns rows affected — 1 on insert or update, 0 on no-op.
        return $this->db->changes() > 0;
    }

    /**
     * Delete a cache entry.
     *
     * @param  string $key
     * @return bool         True if a row was deleted, false if key did not exist.
     */
    public function delete( string $key ): bool {
        if ( ! $this->ensure_db() ) {
            return false;
        }

        $stmt = $this->stmt(
            'delete',
            "DELETE FROM {$this->table} WHERE cache_key = ?"
        );

        if ( ! $stmt ) {
            return false;
        }

        $stmt->bindValue( 1, $key, SQLITE3_TEXT );

        $result = $stmt->execute();
        $stmt->reset();

        if ( ! $result ) {
            return false;
        }

        $result->finalize();

        return $this->db->changes() > 0;
    }

    /**
     * Determine whether a non-expired cache entry exists.
     *
     * Expiry is enforced at the SQL layer — no PHP time comparison.
     * Does not delete the expired entry if found — that is handled
     * by the probabilistic prune in get() and by prune_expired().
     *
     * @param  string $key
     * @return bool
     */
    public function has( string $key ): bool {
        if ( ! $this->ensure_db() ) {
            return false;
        }

        $stmt = $this->stmt(
            'has',
            "SELECT 1
             FROM {$this->table}
             WHERE cache_key = ?
               AND (expires_at IS NULL OR expires_at > ?)
             LIMIT 1"
        );

        if ( ! $stmt ) {
            return false;
        }

        $stmt->bindValue( 1, $key,   SQLITE3_TEXT );
        $stmt->bindValue( 2, time(), SQLITE3_INTEGER );

        $result = $stmt->execute();
        $stmt->reset();

        if ( ! $result ) {
            return false;
        }

        $row = $result->fetchArray( SQLITE3_NUM );
        $result->finalize();

        return $row !== false;
    }

    /**
     * Clear the entire cache table.
     *
     * @return bool
     */
    public function clear(): bool {
        if ( ! $this->ensure_db() ) {
            return false;
        }

        return (bool) $this->db->exec( "DELETE FROM {$this->table}" );
    }

    /**
     * Delete all expired entries from the cache table.
     *
     * Called probabilistically from get() and may also be called
     * explicitly from a scheduled maintenance task.
     *
     * @return int  Number of rows deleted.
     */
    public function prune_expired(): int {
        if ( ! $this->ensure_db() ) {
            return 0;
        }

        $this->db->exec(
            "DELETE FROM {$this->table}
             WHERE expires_at IS NOT NULL AND expires_at < " . time()
        );

        return $this->db->changes();
    }

    /*
    |---------------------
    | Adapter identity
    |---------------------
    */

    public function get_id(): string {
        return 'sqlitecache';
    }

    public function get_name(): string {
        return 'SQLite Cache';
    }

    public function get_settings_schema(): array {
        return [
            'cache_dir' => [
                'type'        => 'text',
                'label'       => 'Cache Directory',
                'required'    => true,
                'description' => 'Absolute path to the cache directory.',
            ],
        ];
    }

    public function set_settings( array $settings ): void {
        $cache_dir = $settings['cache_dir'] ?? '';

        if ( ! $cache_dir || ! str_starts_with( $cache_dir, SMLISER_REPO_DIR ) ) {
            throw new LogicException(
                'Cache directory must be a valid, and within the writable repository path.'
            );
        }

        $this->path = rtrim( $cache_dir, '/' );
        $this->initialize();
    }

    public function is_supported(): bool {
        return class_exists( SQLite3::class );
    }

    /*
    |---------------------
    | Private helpers
    |---------------------
    */

    /**
     * Open the SQLite connection and create the cache table if needed.
     *
     * Called once — subsequent calls from ensure_db() are gated by
     * the isset( $this->db ) fast path so this body only executes once
     * per adapter instance.
     *
     * @return void
     */
    protected function initialize(): void {
        if ( isset( $this->db ) ) {
            return;
        }

        if ( ! $this->is_supported() ) {
            return;
        }

        try {
            $file  = $this->path . '/smliser-cache.db';
            $dir   = dirname( $file );

            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0755, true );
            }

            $this->db = new SQLite3(
                $file,
                SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
            );

            // WAL mode allows concurrent reads during a write.
            $this->db->exec( 'PRAGMA journal_mode = WAL;' );

            // NORMAL sync is safe with WAL and significantly faster than FULL.
            $this->db->exec( 'PRAGMA synchronous = NORMAL;' );

            // Keep temp tables in memory.
            $this->db->exec( 'PRAGMA temp_store = MEMORY;' );

            // 4 MB page cache.
            $this->db->exec( 'PRAGMA cache_size = -4096;' );

        } catch ( Exception $e ) {
            // Swallow — ensure_db() will return false and all public
            // methods degrade gracefully to false/null returns.
            return;
        }

        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                cache_key   TEXT    PRIMARY KEY,
                cache_value BLOB    NOT NULL,
                expires_at  INTEGER DEFAULT NULL
            ) WITHOUT ROWID
        " );

        // Partial index — only indexes rows that actually have an expiry,
        // which is exactly the set the DELETE in prune_expired() scans.
        $this->db->exec( "
            CREATE INDEX IF NOT EXISTS idx_{$this->table}_expires
            ON {$this->table} (expires_at)
            WHERE expires_at IS NOT NULL
        " );
    }

    /**
     * Return a cached prepared statement, preparing it on first use.
     *
     * Prepared statements are reused across calls within the same
     * request/process lifetime — one prepare per label per connection.
     *
     * @param  string          $label  Arbitrary identifier for the statement.
     * @param  string          $sql    SQL to prepare on first call.
     * @return SQLite3Stmt|false
     */
    private function stmt( string $label, string $sql ): SQLite3Stmt|false {
        if ( ! isset( $this->stmts[ $label ] ) ) {
            $stmt = @$this->db->prepare( $sql );

            if ( ! $stmt ) {
                return false;
            }

            $this->stmts[ $label ] = $stmt;
        }

        return $this->stmts[ $label ];
    }

    /**
     * Ensure the database connection is open.
     *
     * Fast path on repeated calls — isset() on a typed property is
     * essentially free. Only falls through to initialize() once.
     *
     * @return bool  True if the connection is available.
     */
    private function ensure_db(): bool {
        if ( isset( $this->db ) ) {
            return true;
        }

        $this->initialize();

        return isset( $this->db );
    }
}