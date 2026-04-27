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
 * - connect() only runs once per instance — is_connected() gates everything
 *   with a fast isset() check on subsequent calls.
 *
 * ## Stats design — atomic, race-free, zero hot-path cost
 *
 * The previous design wrote stats to the DB on every get/set/has call.
 * With multiple PHP-FPM workers each holding their own in-memory counters,
 * every write was a read-modify-write race: worker A reads hits=5, worker B
 * reads hits=5, both increment to 6, both write 6 — one increment is lost.
 *
 * The new design:
 *
 *  1. In-process accumulation — hits and misses are incremented in plain
 *     PHP integers ($pending_hits / $pending_misses). No DB touch at all
 *     during the hot path. Zero I/O, zero lock contention.
 *
 *  2. Atomic flush at shutdown — a single register_shutdown_function()
 *     call fires once per request/CLI process. It issues one SQL statement:
 *
 *       UPDATE stats SET hits = hits + $n, misses = misses + $m WHERE id = 1
 *
 *     Because the increment is expressed relative to the current DB value
 *     (hits + $n rather than an absolute value), concurrent workers never
 *     clobber each other — each one adds its own delta regardless of what
 *     the others wrote.
 *
 *  3. WAL journal mode keeps the flush non-blocking for concurrent readers.
 */
class SQLiteCacheAdapter implements CacheAdapterInterface {

    /*
    |--------------------------------------------
    | DATABASE STATE
    |--------------------------------------------
    */

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
     * Stats table name.
     *
     * @var string
     */
    protected string $stats_table = 'smliser_cache_stats';

    /**
     * Absolute path to the cache directory.
     *
     * @var string
     */
    protected string $path;

    /**
     * Database file name
     * 
     * @var string $db_filename
     */
    protected string $db_filename = 'smliser-cache.db';

    /**
     * Prepared statement cache.
     *
     * @var array<string, SQLite3Stmt>
     */
    private array $stmts = [];

    /*
    |--------------------------------------------
    | STATS ACCUMULATOR
    |--------------------------------------------
    */

    /**
     * Hit count accumulated in-process this request.
     *
     * Never written to the DB directly — flushed atomically at shutdown.
     *
     * @var int
     */
    private int $pending_hits = 0;

    /**
     * Miss count accumulated in-process this request.
     *
     * @var int
     */
    private int $pending_misses = 0;

    /**
     * Whether the shutdown flusher has been registered.
     *
     * One registration per adapter instance is enough.
     *
     * @var bool
     */
    private bool $shutdown_registered = false;

    /**
     * Page cache memory
     * 
     * @var int $cache_memory
     */
    protected int $cache_memory = 4;

    /**
     * Maximum storage memory (MB)
     * 
     * @var int $storage_limit
     */
    protected int $storage_limit = 512;

    /*
    |--------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------
    */

    public function __construct() {}

    /*
    |--------------------------------------------
    | CacheAdapterInterface — READ
    |--------------------------------------------
    */

    /**
     * Retrieve a cached value.
     *
     * Expiry is enforced entirely at the SQL layer. A 1-in-100
     * probabilistic prune runs on reads to amortise cleanup cost.
     * Stats are accumulated in-memory only — no DB write on this path.
     *
     * @param  string $key
     * @return mixed|false
     */
    public function get( string $key ): mixed {
        if ( ! $this->is_connected() ) {
            $this->record_miss();
            return false;
        }

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
            $this->record_miss();
            return false;
        }

        $stmt->bindValue( 1, $key,   SQLITE3_TEXT );
        $stmt->bindValue( 2, time(), SQLITE3_INTEGER );

        $result = $stmt->execute();
        $stmt->reset();

        if ( ! $result ) {
            $this->record_miss();
            return false;
        }

        $row = $result->fetchArray( SQLITE3_ASSOC );
        $result->finalize();

        if ( ! $row ) {
            $this->record_miss();
            return false;
        }

        $this->record_hit();

        return unserialize( $row['cache_value'], [ 'allowed_classes' => true ] );
    }

    /**
     * Determine whether a non-expired cache entry exists.
     *
     * Expiry enforced at the SQL layer. Stats accumulated in-memory.
     *
     * @param  string $key
     * @return bool
     */
    public function has( string $key ): bool {
        if ( ! $this->is_connected() ) {
            $this->record_miss();
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
            $this->record_miss();
            return false;
        }

        $stmt->bindValue( 1, $key,   SQLITE3_TEXT );
        $stmt->bindValue( 2, time(), SQLITE3_INTEGER );

        $result = $stmt->execute();
        $stmt->reset();

        if ( ! $result ) {
            $this->record_miss();
            return false;
        }

        $row = $result->fetchArray( SQLITE3_NUM );
        $result->finalize();

        $hit = $row !== false && ( (int) $row[0] ) === 1;

        $hit ? $this->record_hit() : $this->record_miss();

        return $hit;
    }

    /*
    |--------------------------------------------
    | CacheAdapterInterface — WRITE
    |--------------------------------------------
    */

    /**
     * Store a value in cache.
     *
     * Uses INSERT ... ON CONFLICT upsert — one round trip, no pre-check.
     * Stats counters are not touched on writes.
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $ttl   Seconds until expiry. 0 = no expiry.
     * @return bool
     */
    public function set( string $key, mixed $value, int $ttl = 0 ): bool {
        if ( ! $this->is_connected() ) {
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
     * @return bool         True if a row was deleted.
     */
    public function delete( string $key ): bool {
        if ( ! $this->is_connected() ) {
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
     * Clear the entire cache table and reset persisted stats to zero.
     *
     * Also resets the in-process pending counters so the shutdown
     * flusher does not add phantom deltas on top of the zeroed stats.
     *
     * @return bool
     */
    public function clear(): bool {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $this->db->exec( "DELETE FROM {$this->table}" );
        $this->db->exec(
            "UPDATE {$this->stats_table} SET hits = 0, misses = 0 WHERE id = 1"
        );

        // Zero the pending accumulators so the shutdown flusher adds
        // nothing on top of the explicit reset above.
        $this->pending_hits   = 0;
        $this->pending_misses = 0;

        return true;
    }

    /**
     * Delete all expired entries from the cache table.
     *
     * Called probabilistically from get() and may also be called
     * explicitly from a scheduled maintenance task.
     *
     * @return int Number of rows deleted.
     */
    public function prune_expired(): int {
        if ( ! $this->is_connected() ) {
            return 0;
        }

        $this->db->exec(
            "DELETE FROM {$this->table}
             WHERE expires_at IS NOT NULL AND expires_at < " . time()
        );

        return $this->db->changes();
    }

    /*
    |--------------------------------------------
    | ADAPTER IDENTITY & STATS
    |--------------------------------------------
    */

    public static function get_id(): string {
        return 'sqlitecache';
    }

    public static function get_name(): string {
        return 'SQLite Cache';
    }

    /**
     * Return current cache statistics.
     *
     * Reads the persisted hit/miss totals from the stats table and
     * adds the current request's pending delta so the numbers are
     * always up-to-date for the caller without requiring a flush first.
     *
     * @return CacheStats
     */
    public function get_stats(): CacheStats {
        if ( ! $this->is_connected() ) {
            return new CacheStats();
        }

        $now = time();

        $row = $this->db->querySingle(
            "SELECT
                 COUNT(*) AS entry_count,
                 SUM(LENGTH(cache_value)) AS value_bytes
             FROM {$this->table}
             WHERE expires_at IS NULL OR expires_at > {$now}",
            true
        );

        $entries     = (int) ( $row['entry_count'] ?? 0 );
        $memory_used = (int) ( $row['value_bytes']  ?? 0 );

        $expired = (int) $this->db->querySingle(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE expires_at IS NOT NULL AND expires_at <= {$now}"
        );

        $page_count = (int) $this->db->querySingle( 'PRAGMA max_page_count' );
        $page_size  = (int) $this->db->querySingle( 'PRAGMA page_size' );
        $db_size    = $page_count * $page_size;
        $db_file    = $this->db_file();
        $uptime     = file_exists( $db_file ) ? $now - filemtime( $db_file ) : 0;

        // Load persisted totals, then add this request's in-flight delta
        // so callers always see the latest effective counts even before
        // the shutdown flusher has run.
        $stats_row = $this->db->querySingle(
            "SELECT hits, misses FROM {$this->stats_table} WHERE id = 1",
            true
        );

        $hits   = (int) ( $stats_row['hits']   ?? 0 ) + $this->pending_hits;
        $misses = (int) ( $stats_row['misses']  ?? 0 ) + $this->pending_misses;

        return new CacheStats(
            hits         : $hits,
            misses       : $misses,
            entries      : $entries,
            memory_used  : $memory_used,
            memory_total : $db_size,
            uptime       : $uptime,
            extra        : [
                'expired_entries' => $expired,
                'db_file_size'    => $db_size,
                'db_file'         => $db_file,
                'page_count'      => $page_count,
                'page_size'       => $page_size,
            ],
        );
    }

    /*
    |--------------------------------------------
    | ADAPTER CONFIGURATION
    |--------------------------------------------
    */

    /**
     * Return the adapter's settings schema.
     *
     * @return array<string, mixed>
     */
    public function get_settings_schema(): array {
        return [
            'db_filename' => [
                'type'        => 'text',
                'label'       => 'Cache File Name',
                'required'    => false,
                'default'     => $this->db_filename,
                'description' => 'The SQLite database file name(default: smliser-cache.db).',
            ],

            'cache_dir' => [
                'type'        => 'text',
                'label'       => 'Cache Directory',
                'required'    => true,
                'default'     => rtrim( SMLISER_CACHE_DIR, '/' ) . '/',
                'description' => 'Absolute path to the directory where the SQLite cache database will be stored. Must be within the writable repository path.',
            ],
            'cache_memory' => [
                'type'        => 'number',
                'label'       => 'Cache Memory (MB)',
                'required'    => false,
                'default'     => $this->cache_memory,
                'description' => 'Amount of memory SQLite can use for its page cache. Enter in megabytes(default: 4 MB).',
            ],
            'storage_limit' => [
                'type'        => 'number',
                'label'       => 'Disk Storage Limit (MB)',
                'required'    => true,
                'default'     => $this->storage_limit,
                'description' => 'Maximum disk space the cache database is allowed to occupy. Once reached, further writes will fail until space is cleared.',
            ],
        ];
    }

    /**
     * Apply adapter settings.
     *
     * @param  array<string, mixed> $settings
     * @return void
     */
    public function set_settings( array $settings ): void {
        $cache_dir      = $settings['cache_dir'] ?? '';
        $cache_memory   = (int) ( $settings['cache_memory'] ?? $this->cache_memory );
        $storage_limit  = (int) ( $settings['storage_limit'] ?? $this->storage_limit );
        $db_filename    = (string) ( $settings['db_filename'] ?? $this->db_filename );

        if ( ! $cache_dir || ! str_starts_with( $cache_dir, SMLISER_REPO_DIR ) ) {
            throw new LogicException(
                'Cache directory must be valid and within the writable repository path.'
            );
        }

        $this->path = rtrim( $cache_dir, '/' );

        // Validate cache_memory within sensible bounds
        if ( $cache_memory !== $this->cache_memory && ( $cache_memory < 1 || $cache_memory > 1024 ) ) {
            $cache_memory = $this->cache_memory; // fallback default.
        }

        if ( '' === $db_filename ) {
            $db_filename    = $this->db_filename;
        }

        $this->cache_memory     = $cache_memory;
        $this->storage_limit    = $storage_limit;
        $this->db_filename      = $db_filename;

        // Reset statement cache — a new path means a new database file.
        $this->stmts = [];
        unset( $this->db );
    }

    /**
     * Test whether the adapter can connect and operate with the given settings.
     *
     * Performs a temporary write → read → delete round-trip against a
     * probe key without disturbing any existing cache entries.
     *
     * @param  array<string, mixed> $settings Settings to test.
     * @return bool True if all three round-trip steps succeed.
     */
    public function test( array $settings ): bool {
        $sandbox    = new static;
        try {
            // Apply candidate settings without persisting them.
            $sandbox->set_settings( $settings );

            if ( empty( $sandbox->path ) ) {
                throw new CacheTestException( 'SQLite cache path is not configured.' );
            }

            if ( ! is_dir( $sandbox->path ) ) {
                throw new CacheTestException(
                    sprintf( 'SQLite cache directory does not exist: %s', $sandbox->path )
                );
            }

            if ( ! is_writable( $sandbox->path ) ) {
                throw new CacheTestException(
                    sprintf( 'SQLite cache directory is not writable: %s', $sandbox->path )
                );
            }

            // Open a fresh connection against the candidate path.
            unset( $sandbox->db );
            $sandbox->stmts = [];
            $sandbox->connect();

            if ( ! isset( $sandbox->db ) ) {
                throw new CacheTestException( 'Could not open SQLite database.' );
            }

            // Write → read → delete round-trip with an isolated probe key.
            $probe    = '__smliser_probe_' . bin2hex( random_bytes( 8 ) );
            $expected = 'smliser_ok';

            if ( ! $sandbox->set( $probe, $expected, 30 ) ) {
                throw new CacheTestException( 'SQLite probe write failed.' );
            }

            if ( $sandbox->get( $probe ) !== $expected ) {
                throw new CacheTestException( 'SQLite probe read returned unexpected value.' );
            }

            $sandbox->delete( $probe );

            return true;

        } catch ( CacheTestException $e ) {
            throw $e;
        } catch ( \Throwable $e ) {
            throw new CacheTestException( $e->getMessage() );
        } finally {

            $files  = glob( $sandbox->db_file() . '*' );

            foreach( (array) $files as $file ) {
                @unlink( $file );
            }
        }
            
    }

    /*
    |--------------------------------------------
    | STATS FLUSH
    |--------------------------------------------
    */

    /**
     * Record a cache hit and ensure the shutdown flusher is registered.
     *
     * All hit recording goes through here — the flusher registration
     * cannot be forgotten regardless of which code path produces the hit.
     *
     * @return void
     */
    private function record_hit(): void {
        $this->pending_hits++;
        $this->ensure_shutdown_flusher();
    }

    /**
     * Record a cache miss and ensure the shutdown flusher is registered.
     *
     * All miss recording goes through here — including early-return paths
     * (connection failure, statement failure, empty result). This was the
     * original bug: bare $pending_misses++ increments on early-return paths
     * never called ensure_shutdown_flusher(), so miss-only requests never
     * registered the flusher and those misses were never persisted.
     *
     * @return void
     */
    private function record_miss(): void {
        $this->pending_misses++;
        $this->ensure_shutdown_flusher();
    }

    /**
     * Register the shutdown flusher exactly once per adapter instance.
     *
     * The flusher uses a relative UPDATE (hits = hits + $n) rather than
     * writing an absolute value, making it safe under concurrent workers:
     * each process adds only its own delta regardless of what other
     * workers wrote between the start and end of this request.
     *
     * @return void
     */
    private function ensure_shutdown_flusher(): void {
        if ( $this->shutdown_registered ) {
            return;
        }

        $this->shutdown_registered = true;

        register_shutdown_function( function (): void {
            $this->flush_stats();
        } );
    }

    /**
     * Atomically persist the in-process hit/miss delta to the stats table.
     *
     * Called once by the shutdown flusher. Safe to call manually (e.g.
     * in tests or long-running CLI commands that want to checkpoint stats
     * mid-run).
     *
     * Uses a relative UPDATE so concurrent workers never clobber each
     * other's increments.
     *
     * @return void
     */
    public function flush_stats(): void {
        if ( ! isset( $this->db ) ) {
            return;
        }

        if ( $this->pending_hits === 0 && $this->pending_misses === 0 ) {
            return;
        }

        $hits   = $this->pending_hits;
        $misses = $this->pending_misses;

        // Reset before the write so a re-entrant call (e.g. triggered by
        // an exception handler) doesn't double-count.
        $this->pending_hits   = 0;
        $this->pending_misses = 0;

        $this->db->exec(
            "UPDATE {$this->stats_table}
             SET hits   = hits   + {$hits},
                 misses = misses + {$misses}
             WHERE id = 1"
        );
    }

    /*
    |--------------------------------------------
    | CONNECTION
    |--------------------------------------------
    */

    protected function connect(): void {
        if ( isset( $this->db ) ) {
            return;
        }

        try {
            if ( ! $this->is_supported() ) {
                throw new Exception( 'SQLite is not supported.' );
            }

            if ( ! isset( $this->path ) ) {
                throw new Exception( 'SQLiteCacheAdapter: Database path is not set.' );
            }

            $file = $this->db_file();
            $dir  = dirname( $file );

            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0755, true );
            }

            $this->db = new SQLite3(
                $file,
                SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE
            );

            $this->db->busyTimeout( 5000 );
            $this->db->exec( 'PRAGMA temp_store   = MEMORY;' );

            // Convert MB to negative page count (SQLite convention)
            $page_size = (int) $this->db->querySingle( 'PRAGMA page_size' );
            $cache_pages = -1 * ( $this->cache_memory * 1024 ) / $page_size;
            $this->db->exec( "PRAGMA cache_size = {$cache_pages};" );

            $max_pages = ($this->storage_limit * 1024 * 1024) / $page_size;
            $this->db->exec( "PRAGMA max_page_count = " . (int) $max_pages . ";" );

            $this->db->exec( 'PRAGMA mmap_size    = 268435456;' );
            $this->db->exec( 'PRAGMA journal_mode = WAL;' );

        } catch ( Exception $e ) {
            error_log( $e->getMessage() );
            return;
        }

        // Cache table.
        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                cache_key   TEXT    PRIMARY KEY,
                cache_value BLOB    NOT NULL,
                expires_at  INTEGER DEFAULT NULL
            ) WITHOUT ROWID
        " );

        $this->db->exec( "
            CREATE INDEX IF NOT EXISTS idx_{$this->table}_expires
            ON {$this->table} (expires_at)
            WHERE expires_at IS NOT NULL
        " );

        // Stats table — single row, id = 1.
        $this->db->exec( "
            CREATE TABLE IF NOT EXISTS {$this->stats_table} (
                id     INTEGER PRIMARY KEY,
                hits   INTEGER NOT NULL DEFAULT 0,
                misses INTEGER NOT NULL DEFAULT 0
            )
        " );

        $this->db->exec(
            "INSERT OR IGNORE INTO {$this->stats_table} (id, hits, misses) VALUES (1, 0, 0)"
        );
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
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

    private function is_connected(): bool {
        if ( isset( $this->db ) ) {
            return true;
        }
        $this->connect();
        return isset( $this->db );
    }

    public function is_supported(): bool {
        return class_exists( SQLite3::class );
    }

    private function db_file() : string {
        return rtrim( $this->path, '/' ) . '/' . $this->db_filename;
    }
}