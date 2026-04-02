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
use SmartLicenseServer\Cache\CacheStats;
use SmartLicenseServer\Cache\Exceptions\CacheTestException;
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
class SQLiteCacheAdapter implements CacheAdapterInterface {

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

        // Probabilistic prune — 1% of reads trigger cleanup of all expired rows.
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
     * Uses SELECT 1 with a WHERE match; a result row means the key
     * exists and has not expired. fetchArray() returning false means
     * no matching row — i.e. miss or expired.
     *
     * BUG FIX (original): queried FROM table (literal) instead of
     * FROM {$this->table}, and checked fetchArray() truthiness on the
     * EXISTS row without reading the boolean column value — EXISTS
     * always returns a row (containing 0 or 1), so the original always
     * returned true regardless of whether the key existed.
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

        // A row is returned only when the key exists and has not expired.
        // fetchArray() returns false when there are no rows — i.e. miss.
        return $row !== false && ( (int) $row[0] ) === 1;
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

    public static function get_id(): string {
        return 'sqlitecache';
    }

    public static function get_name(): string {
        return 'SQLite Cache';
    }

    public function get_settings_schema(): array {
        return [
            'cache_dir' => [
                'type'        => 'text',
                'label'       => 'Cache Directory',
                'required'    => true,
                'default'     => '',
                'description' => 'Absolute path to the directory where the SQLite cache database will be stored. Must be within the writable repository path.',
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

        // Reset statement cache — a new path means a new database file.
        $this->stmts = [];
        unset( $this->db );

        $this->initialize();
    }

    public function is_supported(): bool {
        return class_exists( SQLite3::class );
    }

    /*
    |---------------------
    | Diagnostics
    |---------------------
    */

    /**
     * Return runtime statistics derived from the SQLite database.
     *
     * All figures are computed with lightweight COUNT / SUM queries
     * against the cache table itself — no SQLite PRAGMA overhead beyond
     * page_count and page_size which are O(1) reads from the header.
     *
     * memory_total reflects the physical database file size (page_count ×
     * page_size) rather than a configurable ceiling, since SQLite has no
     * fixed memory limit. memory_used is the serialised byte footprint of
     * live (non-expired) cache values.
     *
     * Hit/miss counters are not tracked by SQLite itself; uptime is derived
     * from the oldest created_at timestamp if that column exists, falling
     * back to 0. Because SQLite is file-based and this adapter does not yet
     * record hits/misses, both are returned as 0.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->ensure_db() ) {
            return new CacheStats();
        }

        try {
            $now = time();

            // Live entry count and total serialised size of non-expired values.
            $row = $this->db->querySingle(
                "SELECT
                     COUNT(*)       AS entry_count,
                     SUM(LENGTH(cache_value)) AS value_bytes
                 FROM {$this->table}
                 WHERE expires_at IS NULL OR expires_at > {$now}",
                true
            );

            $entries     = (int)   ( $row['entry_count'] ?? 0 );
            $memory_used = (int)   ( $row['value_bytes'] ?? 0 );

            // Expired-but-not-yet-pruned entry count (informational).
            $expired = (int) $this->db->querySingle(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE expires_at IS NOT NULL AND expires_at <= {$now}"
            );

            // Physical file size: page_count × page_size (both O(1) PRAGMA reads).
            $page_count = (int) $this->db->querySingle( 'PRAGMA page_count' );
            $page_size  = (int) $this->db->querySingle( 'PRAGMA page_size' );
            $db_size    = $page_count * $page_size;

            // File path for the extra bag.
            $db_file = isset( $this->path )
                ? $this->path . '/smliser-cache.db'
                : '';

            return new CacheStats(
                hits         : 0,    // SQLite does not track read hits natively.
                misses       : 0,    // SQLite does not track read misses natively.
                entries      : $entries,
                memory_used  : $memory_used,
                memory_total : $db_size,
                uptime       : 0,    // No server process — file mtime could substitute if needed.
                extra        : [
                    'expired_entries' => $expired,
                    'db_file_size'    => $db_size,
                    'db_file'         => $db_file,
                    'journal_mode'    => (string) $this->db->querySingle( 'PRAGMA journal_mode' ),
                    'page_count'      => $page_count,
                    'page_size'       => $page_size,
                ],
            );
        } catch ( \Throwable ) {
            return new CacheStats();
        }
    }

    /**
     * Test whether the adapter can open a SQLite database and perform a
     * full write → read → delete round-trip at the supplied path.
     *
     * Creates an isolated temporary database in the supplied cache_dir
     * (or a system temp directory when cache_dir is absent) so the live
     * database is never touched. The probe file is deleted before returning.
     *
     * Validates that cache_dir is within SMLISER_REPO_DIR when provided,
     * matching the same constraint enforced by set_settings().
     *
     * @param array<string, mixed> $settings Settings shaped like get_settings_schema().
     * @return bool True if the path is writable and a round-trip succeeds.
     * @throws CacheTestException On any configuration or connectivity failure.
     */
    public function test( array $settings = [] ): bool {
        if ( ! $this->is_supported() ) {
            throw new CacheTestException(
                'SQLite3 extension is not available on this server.'
            );
        }

        $probe_file = null;

        try {
            $cache_dir = isset( $settings['cache_dir'] )
                ? rtrim( (string) $settings['cache_dir'], '/' )
                : null;

            if ( $cache_dir !== null ) {
                if ( ! str_starts_with( $cache_dir, SMLISER_REPO_DIR ) ) {
                    throw new CacheTestException(
                        sprintf(
                            'Cache directory "%s" is outside the allowed base path. It must be within %s.',
                            $cache_dir,
                            SMLISER_REPO_DIR
                        )
                    );
                }

                if ( ! is_dir( $cache_dir ) && ! mkdir( $cache_dir, 0755, true ) ) {
                    throw new CacheTestException(
                        sprintf( 'Could not create cache directory "%s". Check filesystem permissions.', $cache_dir )
                    );
                }

                $probe_file = $cache_dir . '/__smliser_sqlite_probe_' . \uniqid( '', true ) . '.db';
            } else {
                $probe_file = sys_get_temp_dir() . '/__smliser_sqlite_probe_' . \uniqid( '', true ) . '.db';
            }

            try {
                $sandbox = new \SQLite3(
                    $probe_file,
                    SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
                );
            } catch ( \Throwable $e ) {
                throw new CacheTestException(
                    sprintf( 'Could not open SQLite database at "%s": %s', $probe_file, $e->getMessage() ),
                    0,
                    $e
                );
            }

            if ( $sandbox->exec( 'CREATE TABLE IF NOT EXISTS probe (k TEXT PRIMARY KEY, v BLOB)' ) === false ) {
                $sandbox->close();
                throw new CacheTestException(
                    sprintf( 'Could not create probe table: %s', $sandbox->lastErrorMsg() )
                );
            }

            $probe_key   = '__probe_' . \uniqid( '', true );
            $probe_value = serialize( 1 );

            // Write.
            $stmt = $sandbox->prepare( 'INSERT INTO probe (k, v) VALUES (?, ?)' );
            if ( $stmt === false ) {
                $sandbox->close();
                throw new CacheTestException(
                    sprintf( 'Could not prepare write statement: %s', $sandbox->lastErrorMsg() )
                );
            }

            $stmt->bindValue( 1, $probe_key,   SQLITE3_TEXT );
            $stmt->bindValue( 2, $probe_value, SQLITE3_BLOB );

            if ( $stmt->execute() === false ) {
                $sandbox->close();
                throw new CacheTestException(
                    sprintf( 'Probe write failed: %s', $sandbox->lastErrorMsg() )
                );
            }

            // Read.
            $read = $sandbox->querySingle(
                "SELECT v FROM probe WHERE k = '" . \SQLite3::escapeString( $probe_key ) . "'"
            );

            if ( $read === null || unserialize( $read ) !== 1 ) {
                $sandbox->close();
                throw new CacheTestException(
                    'Probe read returned unexpected data — the write may have silently failed.'
                );
            }

            // Delete.
            $sandbox->exec(
                "DELETE FROM probe WHERE k = '" . \SQLite3::escapeString( $probe_key ) . "'"
            );

            if ( $sandbox->changes() !== 1 ) {
                $sandbox->close();
                throw new CacheTestException(
                    'Probe delete did not remove the expected row — cache eviction may be unreliable.'
                );
            }

            $sandbox->close();

            return true;

        } finally {
            // Always remove the probe file, even when an exception was thrown.
            if ( $probe_file !== null && file_exists( $probe_file ) ) {
                @unlink( $probe_file );
                @unlink( $probe_file . '-wal' );
                @unlink( $probe_file . '-shm' );
            }
        }
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

        if ( ! isset( $this->path ) ) {
            return;
        }

        try {
            $file = $this->path . '/smliser-cache.db';
            $dir  = dirname( $file );

            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0755, true );
            }

            $this->db = new SQLite3(
                $file,
                SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
            );

            // Wait up to 5 s on a locked database rather than failing immediately.
            $this->db->busyTimeout( 5000 );
            
            // Store temp tables in memory to reduce disk I/O.
            $this->db->exec( 'PRAGMA temp_store = MEMORY;' );

            // 4 MB page cache.
            $this->db->exec( 'PRAGMA cache_size = -4096;' );

            // 256 MB memory-mapped I/O.
            $this->db->exec( 'PRAGMA mmap_size = 268435456;' );

            $current_mode = $this->db->querySingle( 'PRAGMA journal_mode' );

            if ( 'wal' !== strtolower( $current_mode ) ) {
                // Use Write-Ahead Logging to allow concurrent reads during writes.
                $this->db->exec( 'PRAGMA journal_mode = WAL;' );

                // NORMAL is safe with WAL and faster than FULL.
                $this->db->exec( 'PRAGMA synchronous = NORMAL;' );                
            }

        } catch ( Exception ) {
            return;
        }

        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                cache_key   TEXT    PRIMARY KEY,
                cache_value BLOB    NOT NULL,
                expires_at  INTEGER DEFAULT NULL
            ) WITHOUT ROWID
        " );

        // Partial index covering only rows with an expiry — keeps the index
        // small and makes prune_expired() DELETE scans fast.
        $this->db->exec( "
            CREATE INDEX IF NOT EXISTS idx_{$this->table}_expires
            ON {$this->table} (expires_at)
            WHERE expires_at IS NOT NULL
        " );
    }

    /**
     * Return a cached prepared statement, preparing it on first use.
     *
     * @param  string $label  Arbitrary identifier for the statement.
     * @param  string $sql    SQL to prepare on first call.
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
     * Fast path on repeated calls — isset() on a typed property is free.
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