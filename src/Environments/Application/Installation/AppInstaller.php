<?php
/**
 * App installer class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use Callismart\DBPrism\Adapters\Contracts\DatabaseAdapterInterface;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Utils\Table;
use SmartLicenseServer\Background\Jobs\Accounts\SignupEmailJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Core\DotEnv;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environments\Application\Boot\InstallationState;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Schema\DatabaseAdapterRegistry;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Provides the Unified API to install this application on a the server.
 * 
 * This class:
 * - performs environment sanity checks.
 * - creates all required directories.
 * - makes the .env file from the core .env.example file.
 * - handles initial database connection.
 * - creates all the registered tables.
 * - installs the default roles.
 * - creates the administrator account.
 * - creates the .htaccess file from the .htaccess.example file.
 */
class AppInstaller {
    /**
     * .env keys holding application secrets the installer generates.
     *
     * SMLISER_CLI_API_KEY is deliberately excluded: it is issued by the
     * application itself, not generated at install time.
     *
     * @var string[]
     */
    protected const APP_SECRET_KEYS = array(
        'SMLISER_SECRET',
        'SMLISER_SALT',
    );

    /**
     * Random bytes per generated secret (base64-encodes to 64 characters).
     */
    protected const APP_SECRET_BYTES = 48;

    /**
     * .env key holding the application's public URL.
     */
    public const APP_URL_KEY = 'SMLISER_APP_URL';

    /**
     * Results of link_public_assets().
     */
    public const ASSETS_LINKED    = 'linked';    // A symlink was created.
    public const ASSETS_COPIED    = 'copied';    // Symlinks are unavailable; the assets were copied.
    public const ASSETS_UNCHANGED = 'unchanged'; // The correct symlink already existed.
    public const ASSETS_KEPT      = 'kept';      // A folder not made by the installer exists; left alone.

    /**
     * Marker file identifying a copy of the assets made by link_public_assets().
     */
    public const ASSETS_COPY_MARKER = '.smliser-assets-copy';

    /**
	 * The required directories keyed by readable names.
	 * 
	 * @var array<string, string>
	 */
	protected array $required_directories = array(
		'Application Root'    => \SMLISER_ROOT,
		'Runtime Directory'   => \SMLISER_RUNTIME_DIR,
		'Storage Directory'   => \SMLISER_STORAGE_DIR,
		'Repository Root'     => \SMLISER_REPO_DIR,
		'Plugins Repository'  => \SMLISER_PLUGINS_REPO_DIR,
		'Themes Repository'   => \SMLISER_THEMES_REPO_DIR,
		'Software Repository' => \SMLISER_SOFTWARE_REPO_DIR,
		'Cache Directory'     => \SMLISER_CACHE_DIR,
		'Temporary Directory' => \SMLISER_TMP_DIR,
		'Uploads Directory'   => \SMLISER_UPLOADS_DIR,
		'Logs Directory'      => \SMLISER_LOGS_DIR,
	);

    /**
     * Class constructor.
     *
     * The Database may hold a NullDBAdapter placeholder before installation.
     * Database-backed steps refuse to run until a verified connection has
     * been activated with use_connection().
     *
     * @param Database                $db        The shared application database.
     * @param DatabaseAdapterRegistry $adapters  Database adapter registry.
     * @param FileSystem              $fs        Filesystem API.
     * @param JobQueue                $job_queue Background job queue.
     * @param InstallationState       $state     The installation state file.
     * @param MaintenanceFlag         $flag      The maintenance flag file.
     */
    public function __construct(
        protected Database $db,
        protected DatabaseAdapterRegistry $adapters,
        protected FileSystem $fs,
        protected JobQueue $job_queue,
        protected InstallationState $state,
        protected MaintenanceFlag $flag
    ) {}

    /**
     * Performs environment sanity checks and evaluates database, cache, and package management requirements.
     *
     * @param callable(string $check, string $status, string $message)|null $success_callback
     * @param callable(string $check, string $status, string $message)|null $failure_callback
     * @return array{passed: bool, errors: array<string, string>, warnings: array<string, string>, recommendations: array<string, string>}
     */
    public function verify_environment_sanity(
        ?callable $success_callback = null,
        ?callable $failure_callback = null
    ): array {
        $results = [
            'passed'          => true,
            'errors'          => [],
            'warnings'        => [],
            'recommendations' => [],
        ];

        $report = function( string $check, bool $is_ok, string $message, string $level = 'CRITICAL' ) use ( &$results, $success_callback, $failure_callback ): void {
            if ( $is_ok && 'CRITICAL' === $level ) {
                $success_callback && $success_callback( $check, 'OK', $message );
                return;
            }

            if ( 'CRITICAL' === $level && ! $is_ok ) {
                $results['passed']           = false;
                $results['errors'][ $check ] = $message;
                $failure_callback && $failure_callback( $check, 'CRITICAL', $message );
            } elseif ( 'WARNING' === $level ) {
                $results['warnings'][ $check ] = $message;
                $failure_callback && $failure_callback( $check, 'WARNING', $message );
            } else {
                $results['recommendations'][ $check ] = $message;
                $success_callback && $success_callback( $check, 'RECOMMENDED', $message );
            }
        };

        // PHP Version
        $report(
            'PHP Version',
            version_compare( PHP_VERSION, '8.4.0', '>=' ),
            version_compare( PHP_VERSION, '8.4.0', '>=' )
                ? sprintf( 'PHP %s installed.', PHP_VERSION )
                : sprintf( 'PHP 8.4.0+ required. Current version: %s', PHP_VERSION ),
            'CRITICAL'
        );

        // Package Management & Updates
        $report(
            'Zip Extension',
            extension_loaded( 'zip' ),
            extension_loaded( 'zip' )
				? 'ZipArchive loaded for software updates, themes, and package extraction.'
				: 'The "zip" extension is missing. Package uploads and updates will fail.',
            'CRITICAL'
        );

        // Database Engine Breakdown
        $db_extensions = [
            'mysqli'     => extension_loaded( 'mysqli' ),
            'sqlite3'    => extension_loaded( 'sqlite3' ),
            'pdo'        => extension_loaded( 'pdo' ),
            'pdo_mysql'  => extension_loaded( 'pdo_mysql' ),
            'pdo_pgsql'  => extension_loaded( 'pdo_pgsql' ),
            'pdo_sqlite' => extension_loaded( 'pdo_sqlite' ),
        ];

        $has_any_db = array_reduce( $db_extensions, fn( $carry, $status ) => $carry || $status, false );

        $report(
            'Database Availability',
            $has_any_db,
            $has_any_db
                ? 'At least one supported database extension is loaded.'
                : 'No supported database drivers found (mysqli, sqlite3, or pdo extensions missing).',
            'CRITICAL'
        );

        // Detailed Database Driver Recommendations & Remarks
        if ( $db_extensions['mysqli'] ) {
            $report( 'DB: mysqli (MySQL)', true, 'Native mysqli driver loaded. Preferred for low-overhead MySQL connections.', 'RECOMMENDATION' );
        } elseif ( $db_extensions['pdo_mysql'] ) {
            $report( 'DB: pdo_mysql', true, 'PDO MySQL loaded. Consider enabling native "mysqli" for better performance and speed.', 'RECOMMENDATION' );
        }

        if ( $db_extensions['sqlite3'] ) {
            $report( 'DB: SQLite3', true, 'Native SQLite3 driver loaded. Preferred for light, zero-config file databases.', 'RECOMMENDATION' );
        } elseif ( $db_extensions['pdo_sqlite'] ) {
            $report( 'DB: pdo_sqlite', true, 'PDO SQLite loaded.', 'RECOMMENDATION' );
        }

        if ( $db_extensions['pdo_pgsql'] ) {
            $report( 'DB: PostgreSQL (PDO)', true, 'pdo_pgsql loaded for PostgreSQL connections.', 'RECOMMENDATION' );
        }

        // Cache Adapters & Security Persistence Check
        $cache_adapters = [
            'redis'     => extension_loaded( 'redis' ),
            'memcached' => extension_loaded( 'memcached' ),
            'apcu'      => extension_loaded( 'apcu' ),
            'sqlite3'   => extension_loaded( 'sqlite3' ),
        ];

        $active_persistent_caches = array_keys( array_filter( $cache_adapters ) );

        if ( ! empty( $active_persistent_caches ) ) {
            $report(
                'Persistent Cache',
                true,
                sprintf( 'Persistent cache available via [%s]. MFA tokens, reset tokens, and brute-force tracking will persist accurately.', implode( ', ', $active_persistent_caches ) ),
                'RECOMMENDATION'
            );
        } else {
            $report(
                'Persistent Cache',
                false,
                'No persistent cache extension (redis, memcached, apcu, sqlite3) found. Defaulting to in-memory array cache. CAUTION: Password resets, MFA tokens, and rate-limiting counters will NOT persist across process restarts!',
                'WARNING'
            );
        }

        return $results;
    }

    /**
     * Creates the required directories.
     * 
     * @param callable(string $type, string $dir, string $message)|null $success_callback
     * @param callable(string $type, string $dir, string $message)|null $failure_callback
     * @return void
     */
    public function create_required_directories(
        ?callable $success_callback = null,
        ?callable $failure_callback = null
        ) : void {

        foreach ( $this->required_directories as $type => $dir ) {

            if ( $this->fs->is_dir( $dir ) ) {
                $failure_callback && $failure_callback( $type, $dir, 'Exists' );
                continue;
            }

            if ( ! $this->fs->mkdir( $dir ) ) {
                $failure_callback && $failure_callback( $type, $dir, 'mkdir failed' );
                continue;
            }

            $success_callback && $success_callback( $type, $dir, 'Created' );
        }
    }

    /**
     * Make the .env file.
     * 
     * @param string|null $env_example_path  Absolute path to the env.example file, passing null
     * will force us to look up the file in the root directory, the parent directory of the app root or
     * in the runtime directory.
     * 
     * @param bool $overwrite   Overwrite existing file.
     * 
     * @return string|null  Absolute path to the newly created .env file,
     * null if the env.example file was not found.
     * 
     * @throws \RuntimeException If target directory is not writable.
     */
    public function make_dot_env_file( ?string $env_example_path = null, bool $overwrite = false ) : ?string {
        $env_file   = SMLISER_ROOT . '.env';

        if ( file_exists( $env_file ) && ! $overwrite ) {
            return $env_file;
        }

        if ( null === $env_example_path ) {
            $env_example_path   =  SMLISER_ROOT . '.env.example';

            if ( ! file_exists( $env_example_path ) ) {
                $env_example_path   = dirname( \SMLISER_ROOT ) . '/.env.example';
            }

            if ( ! file_exists( $env_example_path ) ) {
                $env_example_path   = SMLISER_RUNTIME_DIR . '.env.example';
            }
        }

        if ( '' === $env_example_path || ! file_exists( $env_example_path ) ) {
            return throw new \RuntimeException(
                "The path \"{$env_example_path}\" to env.example file does not exist."
            );
        }

        EnvFileWriter::create_from_example( $env_example_path, $env_file, $this->fs )
            ->save();

        return $env_file;
       
    }

    /**
     * Make the .htaccess file.
     * 
     * @param string|null $htaccess_example_path Absolute path to the .htaccess.example file, passing null
     * will force us to look up the file in the root directory, the parent directory of the app root or
     * in the runtime directory.
     * 
     * @param bool $overwrite Overwrite existing file.
     * 
     * @return string|null Absolute path to the newly created .htaccess file,
     * null if the .htaccess.example file was not found.
     * 
     * @throws \RuntimeException If target directory is not writable.
     */
    public function make_htaccess_file( ?string $htaccess_example_path = null, bool $overwrite = false ) : ?string {
        $htaccess_file = SMLISER_ROOT . 'public/.htaccess';

        if ( file_exists( $htaccess_file ) && ! $overwrite ) {
            return $htaccess_file;
        }

        if ( null === $htaccess_example_path ) {
            $htaccess_example_path = SMLISER_ROOT . '.htaccess.example';

            if ( ! file_exists( $htaccess_example_path ) ) {
                $htaccess_example_path = dirname( \SMLISER_ROOT ) . '/.htaccess.example';
            }

            if ( ! file_exists( $htaccess_example_path ) ) {
                $htaccess_example_path = SMLISER_RUNTIME_DIR . '.htaccess.example';
            }
        }

        if ( '' === $htaccess_example_path || ! file_exists( $htaccess_example_path ) ) {
            return throw new \RuntimeException(
                "The path \"{$htaccess_example_path}\" to .htaccess.example file does not exist."
            );
        }

        HtaccessWriter::create_from_example( $htaccess_example_path, $htaccess_file, $this->fs )
            ->save();

        return $htaccess_file;
    }

    /**
     * Expose the bundled assets (system/assets) at public/assets.
     *
     * Prefers a relative symlink (public/assets -> ../system/assets), so the
     * installation folder can be moved and updates are visible immediately.
     * Where symlinks are unavailable (symlink() disabled, or not permitted
     * on the platform), the assets are copied instead, with a marker file so
     * later runs can refresh the copy.
     *
     * Safe to run repeatedly:
     *  - a correct symlink is left alone, unless $force is set;
     *  - a symlink to anything else, or a broken one, is replaced;
     *  - a copy made by this method is refreshed;
     *  - any other file or folder at public/assets is kept and reported,
     *    even with $force, so data this method did not create is never removed.
     *
     * $force rebuilds whatever this method manages: an existing correct link
     * is recreated, and the choice between link and copy is made again from
     * what the server allows now.
     *
     * PHP's stat and realpath caches are cleared after every change, so the
     * checks that follow (and later code in the same request) see the
     * current state of public/assets.
     *
     * @param bool $force Rebuild even when the correct link already exists.
     * @return string One of the ASSETS_* constants describing what was done.
     * @throws \RuntimeException When the source folder is missing or the assets cannot be published.
     */
    public function link_public_assets( bool $force = false ) : string {
        $source = $this->assets_source_dir();
        $target = $this->assets_public_dir();

        if ( ! $this->fs->is_dir( $source ) ) {
            throw new \RuntimeException( "The assets folder \"{$source}\" does not exist. Re-upload the application files." );
        }

        $public_dir = dirname( $target );

        if ( ! $this->fs->mkdir( $public_dir ) ) {
            throw new \RuntimeException( "Could not create \"{$public_dir}\". Make sure the application folder is writable by the web server." );
        }

        /*
         * Symlinks are handled natively: the FileSystem API has no symlink
         * operations, and its delete()/rmdir() follow a link to a directory
         * and would empty system/assets instead of removing the link.
         */
        $this->flush_stat_cache();

        if ( is_link( $target ) ) {
            if ( ! $force && $this->points_to( $target, $source ) ) {
                return static::ASSETS_UNCHANGED;
            }

            if ( ! @unlink( $target ) && ! @rmdir( $target ) ) {
                throw new \RuntimeException( "Could not replace the link \"{$target}\"." );
            }

            $this->flush_stat_cache();
        } elseif ( $this->fs->exists( $target ) ) {
            if ( ! $this->fs->is_file( $target . '/' . static::ASSETS_COPY_MARKER ) ) {
                return static::ASSETS_KEPT;
            }

            if ( ! $this->fs->rmdir( $target, true ) ) {
                throw new \RuntimeException( "Could not remove the previous copy of the assets at \"{$target}\"." );
            }

            $this->flush_stat_cache();
        }

        if ( function_exists( 'symlink' ) && @symlink( $this->relative_path( $public_dir, $source ), $target ) ) {
            $this->flush_stat_cache();

            if ( $this->fs->is_dir( $target ) ) {
                return static::ASSETS_LINKED;
            }

            // A link that was created but does not resolve is useless; remove it before copying.
            @unlink( $target );
            $this->flush_stat_cache();
        }

        if ( ! $this->fs->copy( $source, $target, true ) ) {
            throw new \RuntimeException( "Could not copy \"{$source}\" to \"{$target}\". Make sure the public folder is writable by the web server." );
        }

        if ( ! $this->fs->put_contents( $target . '/' . static::ASSETS_COPY_MARKER, "Copied from {$source} by the installer. Run the installer's asset command again after an update.\n" ) ) {
            throw new \RuntimeException( "Could not write \"{$target}/" . static::ASSETS_COPY_MARKER . '".' );
        }

        $this->flush_stat_cache();

        return static::ASSETS_COPIED;
    }

    /**
     * Clear PHP's stat and realpath caches.
     *
     * is_dir(), is_link(), file_exists() and realpath() results are cached
     * per request; after creating or removing links and folders they can
     * describe a state that no longer exists.
     *
     * @return void
     */
    protected function flush_stat_cache() : void {
        clearstatcache( true );
    }

    /**
     * Whether public/assets serves the bundled assets.
     *
     * @return bool
     */
    public function public_assets_available() : bool {
        return $this->fs->is_dir( $this->assets_public_dir() );
    }

    /**
     * Absolute path to the bundled assets folder (system/assets).
     *
     * @return string
     */
    public function assets_source_dir() : string {
        return rtrim( \SMLISER_RUNTIME_DIR, '/\\' ) . '/assets';
    }

    /**
     * Absolute path where the assets are published (public/assets).
     *
     * @return string
     */
    public function assets_public_dir() : string {
        return rtrim( \SMLISER_ROOT, '/\\' ) . '/public/assets';
    }

    /**
     * Whether a symlink resolves to the given directory.
     *
     * @param string $link   Symlink path.
     * @param string $target Expected target directory.
     * @return bool
     */
    protected function points_to( string $link, string $target ) : bool {
        $resolved = realpath( $link );

        return false !== $resolved && realpath( $target ) === $resolved;
    }

    /**
     * Express a path relative to a directory, for portable symlinks.
     *
     * Falls back to the absolute path when no relative path exists (e.g.
     * different drives on Windows).
     *
     * @param string $from_dir Directory the link lives in.
     * @param string $to       Path the link points to.
     * @return string
     */
    protected function relative_path( string $from_dir, string $to ) : string {
        $from = realpath( $from_dir );
        $to   = realpath( $to ) ?: $to;

        if ( false === $from ) {
            return $to;
        }

        $from_parts = explode( '/', trim( str_replace( '\\', '/', $from ), '/' ) );
        $to_parts   = explode( '/', trim( str_replace( '\\', '/', $to ), '/' ) );

        // Different roots (e.g. C: and D:) cannot be related.
        if ( ( $from_parts[0] ?? '' ) !== ( $to_parts[0] ?? '' ) ) {
            return $to;
        }

        while ( array() !== $from_parts && array() !== $to_parts && $from_parts[0] === $to_parts[0] ) {
            array_shift( $from_parts );
            array_shift( $to_parts );
        }

        $relative = str_repeat( '../', count( $from_parts ) ) . implode( '/', $to_parts );

        return '' === $relative ? '.' : rtrim( $relative, '/' );
    }

    /**
     * Read the database configuration from the .env file as it is now.
     *
     * Parses the file directly, so values written during this request are
     * seen, and $_ENV, $_SERVER and the process environment are not modified.
     *
     * @return DBConfigDTO
     * @throws DatabaseException         ('database_not_configured') When the .env file is missing,
     *                                   or SMLISER_DB_DRIVER or SMLISER_DB_NAME is empty.
     * @throws \InvalidArgumentException When a value is invalid (e.g. an unsupported driver).
     * @throws \RuntimeException         When the .env file contains an unclosed multiline value.
     */
    public function read_database_config() : DBConfigDTO {
        $env_file = \SMLISER_ROOT . '.env';

        if ( ! $this->fs->is_file( $env_file ) ) {
            throw new DatabaseException(
                'database_not_configured',
                "The .env file \"{$env_file}\" does not exist."
            );
        }

        $env    = ( new DotEnv( \SMLISER_ROOT ) )->parse( '.env' );
        $config = \smliser_db_config_from_env( $env );

        if ( null === $config ) {
            $keys    = \smliser_db_env_keys();
            $missing = '' === trim( (string) ( $env[ $keys['driver'] ] ?? '' ) ) ? $keys['driver'] : $keys['dbname'];

            throw new DatabaseException(
                'database_not_configured',
                "{$missing} is not set in the .env file."
            );
        }

        return $config;
    }

    /**
     * Write a database configuration into the existing .env file.
     *
     * Updates only the database keys, preserving every other line and comment.
     * A null field leaves its key unchanged; an empty string clears it.
     * Does not modify $_ENV; read it back with read_database_config().
     *
     * @param DBConfigDTO $config Database configuration to store.
     * @return string Absolute path to the .env file.
     * @throws \RuntimeException When the .env file does not exist or cannot be written.
     */
    public function write_database_config( DBConfigDTO $config ) : string {
        $env_file = \SMLISER_ROOT . '.env';

        if ( ! $this->fs->is_file( $env_file ) ) {
            throw new \RuntimeException(
                "The .env file \"{$env_file}\" does not exist. Create it with make_dot_env_file() first."
            );
        }

        $values = array();

        foreach ( \smliser_db_env_keys() as $field => $env_key ) {
            // The encryption key only applies to sqlite.
            if ( 'encryption_key' === $field && 'sqlite' !== $config->driver ) {
                continue;
            }

            $values[ $env_key ] = $config->{$field};
        }

        $values = array_map(
            array( $this, 'env_string' ),
            array_filter( $values, static fn( mixed $value ) : bool => null !== $value )
        );

        if ( array() === $values ) {
            return $env_file;
        }

        if ( ! ( new EnvFileWriter( $env_file, $this->fs ) )->set_multiple( $values )->save() ) {
            throw new \RuntimeException( "Failed to write the database configuration to \"{$env_file}\"." );
        }

        return $env_file;
    }

    /**
     * Generate the application secrets that are still empty in the .env file.
     *
     * Fills SMLISER_SECRET and SMLISER_SALT with distinct, cryptographically
     * secure random values. Keys that already hold a value are never changed,
     * since replacing a secret invalidates existing sessions and anything
     * derived from it. Does not modify $_ENV.
     *
     * @return string[] The keys that were generated; empty when all were already set.
     * @throws \RuntimeException When the .env file does not exist or cannot be written.
     */
    public function generate_app_secrets() : array {
        $env_file = \SMLISER_ROOT . '.env';

        if ( ! $this->fs->is_file( $env_file ) ) {
            throw new \RuntimeException(
                "The .env file \"{$env_file}\" does not exist. Create it with make_dot_env_file() first."
            );
        }

        $env       = ( new DotEnv( \SMLISER_ROOT ) )->parse( '.env' );
        $in_use    = array();
        $generated = array();

        foreach ( static::APP_SECRET_KEYS as $key ) {
            $value = $env[ $key ] ?? null;

            if ( is_string( $value ) && '' !== $value ) {
                $in_use[] = $value;
            }
        }

        foreach ( static::APP_SECRET_KEYS as $key ) {
            $value = $env[ $key ] ?? null;

            if ( is_string( $value ) && '' !== $value ) {
                continue;
            }

            // No two secrets may share a value.
            do {
                $secret = base64_encode( random_bytes( static::APP_SECRET_BYTES ) );
            } while ( in_array( $secret, $in_use, true ) );

            $in_use[]          = $secret;
            $generated[ $key ] = $secret;
        }

        if ( array() === $generated ) {
            return array();
        }

        if ( ! ( new EnvFileWriter( $env_file, $this->fs ) )->set_multiple( $generated )->save() ) {
            throw new \RuntimeException( "Failed to write the application secrets to \"{$env_file}\"." );
        }

        return array_keys( $generated );
    }

    /**
     * Read the application URL from the .env file as it is now.
     *
     * @return string|null The URL, or null when the .env file is missing or the value is empty.
     */
    public function read_app_url() : ?string {
        if ( ! $this->fs->is_file( \SMLISER_ROOT . '.env' ) ) {
            return null;
        }

        $value = ( new DotEnv( \SMLISER_ROOT ) )->parse( '.env' )[ static::APP_URL_KEY ] ?? '';
        $value = is_string( $value ) ? trim( $value ) : '';

        return '' === $value ? null : $value;
    }

    /**
     * Normalize and save the application URL in the existing .env file.
     *
     * Does not modify $_ENV; read it back with read_app_url().
     *
     * @param string|URL $url The public URL of this installation.
     * @return string The URL as saved.
     * @throws \InvalidArgumentException When the URL is not a valid http(s) URL.
     * @throws \RuntimeException         When the .env file does not exist or cannot be written.
     */
    public function write_app_url( string|URL $url ) : string {
        $env_file = \SMLISER_ROOT . '.env';

        if ( ! $this->fs->is_file( $env_file ) ) {
            throw new \RuntimeException(
                "The .env file \"{$env_file}\" does not exist. Create it with make_dot_env_file() first."
            );
        }

        $url = static::normalize_app_url( $url );

        if ( ! ( new EnvFileWriter( $env_file, $this->fs ) )->set( static::APP_URL_KEY, $url )->save() ) {
            throw new \RuntimeException( "Failed to write the application URL to \"{$env_file}\"." );
        }

        return $url;
    }

    /**
     * Normalize an application URL to the form SMLISER_APP_URL expects.
     *
     * Keeps the scheme, host, port and path; drops credentials, the query,
     * the fragment and the trailing slash. A URL without a scheme gets
     * https:// (http:// for localhost and 127.0.0.1, as URL does).
     *
     * @param string|URL $url The URL to normalize.
     * @return string
     * @throws \InvalidArgumentException When the URL is not a valid http(s) URL.
     */
    public static function normalize_app_url( string|URL $url ) : string {
        $original = trim( is_string( $url ) ? $url : $url->url() );
        $raw      = $original;

        if ( '' !== $raw && ! str_contains( $raw, '://' ) && ! preg_match( '#^(localhost|127\.0\.0\.1)#i', $raw ) ) {
            $raw = 'https://' . $raw;
        }

        $parsed = URL::from( $raw );
        $origin = $parsed->get_origin();

        if ( null === $origin || ! in_array( strtolower( (string) $parsed->get_scheme() ), array( 'http', 'https' ), true ) ) {
            throw new \InvalidArgumentException( 'The site address must be a web address such as https://licenses.example.com.' );
        }

        $normalized = strtolower( $origin ) . rtrim( (string) $parsed->get_path(), '/' );

        if ( ! URL::from( $normalized )->is_valid() ) {
            throw new \InvalidArgumentException( "\"{$original}\" is not a valid web address." );
        }

        return $normalized;
    }

    /**
     * Convert a non-null configuration value to its .env string form.
     *
     * @param mixed $value
     * @return string
     */
    protected function env_string( mixed $value ) : string {
        return match ( true ) {
            true === $value  => 'true',
            false === $value => 'false',
            default          => (string) $value,
        };
    }

    /**
     * Get all required directories.
     * 
     * @return array<string,string>
     */
    public function get_required_directories() : array {
        return $this->required_directories;
    }

    /**
     * Create the database tables.
     * 
     * @param callable(string $table_name, string $message)|null $success_callback
     * @param callable(string $table_name, string $message)|null $failure_callback
     * @return void
     */
    public function create_tables(
        ?callable $success_callback = null,
        ?callable $failure_callback = null
        ) : void{
        $this->assert_database_connection();

        $schema     = SchemaRegistry::instance();
        $inspector  = new Inspector( $this->db );

        foreach ( $schema->get_all_tables() as $table ) {
            if ( $inspector->table_exists( $table->get_name() ) ) {
                $failure_callback && $failure_callback( $table->get_name(), 'Exists' );
                continue;
            }

            if ( ! $this->create_table( $table ) ) {
                $failure_callback && $failure_callback( $table->get_name(), $this->db->get_last_error() );
                continue;
            }

            $success_callback && $success_callback( $table->get_name(), 'Created' );
        }
    }

    /**
     * Create a single database table from a column definition array.
     *
     * @param Table $table
     * @return bool
     */
    protected function create_table( Table $table ): bool {
        $charset_collate = $this->db->get_charset_collate();

        $query  = \smliserQueryBuilder( $this->db->get_driver() )
            ->create_table( $table->get_name() )
            ->add_columns( $table->get_columns() )
            ->add_constraints( $table->get_constraints() );
        $sql    = $query->build() . '' . $charset_collate;
        
        usleep( 10000 );

        return $this->db->exec( $sql );        
    }

    /**
     * Install default roles.
     * 
     * @param callable(string $role_name, string $message)|null $success_callback
     * @param callable(string $role_name, string $message)|null $failure_callback,
     * @param bool $force
     * @return void
     */
    public function install_default_roles(
        callable|null $success_callback = null,
        callable|null $failure_callback = null,
        bool $force = false
        ) : void {
        $this->assert_database_connection();

        $default_roles = DefaultRoles::all();

        foreach ( $default_roles as $slug => $roledata ) {
            if ( $this->role_exists( $slug ) && ! $force ) {
                $failure_callback && $failure_callback( $roledata['label'], 'Role Exists' );
                continue;
            }
            
            $role = new Role();
            $role->set_capabilities( $roledata['capabilities'] );
            $role->set_label( $roledata['label'] );
            $role->set_is_canonical( $roledata['is_canonical'] );
            $role->set_slug( $slug );

            try {
                if ( $role->save() ) {
                    $success_callback && $success_callback( $role->get_label(), '✔ Installed' );
                } else {
                    $failure_callback && $failure_callback( $role->get_label(), "⚠ {$this->db->get_last_error()}" );
                }
            } catch ( \Throwable $e ) {
                $failure_callback && $failure_callback( $role->get_label(), $e->getMessage() );
            }
        }
    }

    /**
     * Create the site administrator.
     * 
     * @return User
     * @throws DatabaseException
     * @throws \InvalidArgumentException
     */
    public function create_admin( string $name, string $email, string $password ) : User {
        $this->assert_database_connection();

        $default_role   = DefaultRoles::get( 'system_admin' );
        
        $role           = $this->find_role( $default_role['slug'] ) ?? new Role();

        $role->set_label( $default_role['label'])
            ->set_slug( $default_role['slug'] )
            ->set_capabilities( $default_role['capabilities'] )
            ->set_is_canonical( $default_role['is_canonical'] );
        
        $admin  = User::get_by_email( $email );

        if ( ! $admin ) {
            $admin  = ( new User() )
                ->set_display_name( $name )
                ->set_email( $email )
                ->set_password_hash( password_hash( $password, PASSWORD_ARGON2ID ) )
                ->set_created_at( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )
                ->set_id(0)
                ->set_status( User::STATUS_ACTIVE );
            
            if ( ! $admin->save() ) {
                throw new DatabaseException( 'insert_error', $this->db->get_last_error() );
            }
        }

        $owner = ContextServiceProvider::get_default_owner( $admin );
        if ( ! $owner ) {
            $owner = new Owner;
        }
        
        if ( ! $owner->exists() ) {
            $owner->set_name( $admin->get_display_name() )
                ->set_status( Owner::STATUS_ACTIVE )
                ->set_subject_id( $admin->get_id() )
                ->set_type( Owner::TYPE_INDIVIDUAL );
            $owner->save();
        }
        
        $owner_subject = ContextServiceProvider::get_owner_subject( $owner );

        ContextServiceProvider::save_actor_role( $admin, $role, $owner_subject );

        $this->job_queue->dispatch( JobDTO::make(
            job_class: SignupEmailJob::class,
            payload: [
                'user_id'   => $admin->get_id(),
                'for_admin' => false,
                'recipient' => $admin->get_email(),
            ]
        ));

        return $admin;
    }

    /**
     * Test the given credentials against a database engine.
     *
     * The adapter registry builds the engine's adapter for this
     * configuration (not the application's), then it is connected. The
     * returned adapter is connected but not active; pass it to
     * use_connection() to make the application use it.
     *
     * @param DBConfigDTO $config     Database configuration to test.
     * @param string|null $adapter_id Optional adapter ID; the engine default is used when null.
     * @return DatabaseAdapterInterface The connected adapter.
     * @throws DatabaseException When no suitable adapter is registered for the engine, or the connection fails.
     */
    public function test_db_connection( DBConfigDTO $config, ?string $adapter_id = null ) : DatabaseAdapterInterface {
        $adapter = $this->adapters->create( $config, $adapter_id );

        if ( ! $adapter->connect() ) {
            throw new DatabaseException( 'database_connect_error', $adapter->get_last_error() );
        }

        return $adapter;
    }

    /**
     * Make the application use a verified database connection.
     *
     * Swaps the adapter on the shared Database instance, so every service
     * already holding it (models, job queue, this installer) uses the new
     * connection from its next query onward.
     *
     * @param DatabaseAdapterInterface $adapter A connected adapter, typically from test_db_connection().
     * @return void
     * @throws DatabaseException When the adapter is not connected.
     */
    public function use_connection( DatabaseAdapterInterface $adapter ) : void {
        if ( ! $adapter->is_connected() ) {
            throw new DatabaseException(
                'database_connect_error',
                'Only a connected database adapter can be activated.'
            );
        }

        $this->db->set_adapter( $adapter );
    }

    /**
     * Whether a real database connection is active.
     *
     * False while the shared Database holds the NullDBAdapter placeholder.
     *
     * @return bool
     */
    public function has_database_connection() : bool {
        return ! $this->db->has_null_adapter();
    }

    /**
     * List what still prevents this application from counting as installed.
     *
     * Checks the .env file and its site address, the required directories, the
     * public assets, the database connection, every registered table, the default roles, and that
     * at least one user account exists. A
     * database that cannot be reached throws instead of being reported as
     * "not installed", so an outage is never mistaken for a fresh install.
     *
     * @return string[] Unmet requirements; empty when the installation is complete.
     * @throws DatabaseException When the configured database cannot be reached or queried.
     */
    public function installation_issues() : array {
        $issues = array();

        // Checked on disk: $_ENV can outlive a deleted .env in long-lived workers.
        if ( ! $this->fs->is_file( \SMLISER_ROOT . '.env' ) ) {
            $issues[] = sprintf( 'Missing .env file: %s', \SMLISER_ROOT . '.env' );
        } elseif ( null === $this->read_app_url() ) {
            $issues[] = sprintf( 'The site address (%s) is not set in the .env file.', static::APP_URL_KEY );
        }

        foreach ( $this->required_directories as $name => $dir ) {
            if ( ! $this->fs->is_dir( $dir ) ) {
                $issues[] = sprintf( 'Missing directory: %s (%s)', $name, $dir );
            }
        }

        if ( ! $this->public_assets_available() ) {
            $issues[] = sprintf( 'The public assets are not published: %s', $this->assets_public_dir() );
        }

        if ( ! $this->has_database_connection() ) {
            $issues[] = 'No database connection is configured.';
            return $issues;
        }

        if ( ! $this->db->is_connected() && ! $this->db->connect() ) {
            throw new DatabaseException( 'database_connect_error', $this->db->get_last_error() );
        }

        $inspector      = new Inspector( $this->db );
        $users_table    = false;
        $missing_tables = false;

        foreach ( SchemaRegistry::instance()->get_all_tables() as $table ) {
            if ( ! $inspector->table_exists( $table->get_name() ) ) {
                $issues[]       = sprintf( 'Missing table: %s', $table->get_name() );
                $missing_tables = true;
            } elseif ( \SMLISER_USERS_TABLE === $table->get_name() ) {
                $users_table = true;
            }
        }

        // Roles are rows in the tables above; checked only once every table exists.
        if ( ! $missing_tables ) {
            foreach ( array_keys( DefaultRoles::all() ) as $slug ) {
                if ( ! $this->role_exists( $slug ) ) {
                    $issues[] = sprintf( 'Missing default role: %s', $slug );
                }
            }
        }

        if ( $users_table ) {
            $sql   = \smliserQueryBuilder( $this->db->get_driver() )
                ->select( 'COUNT(*)' )
                ->from( \SMLISER_USERS_TABLE );
            $count = $this->db->get_var( $sql->build(), $sql->get_bindings() );

            if ( null === $count && $this->db->get_last_error() ) {
                throw new DatabaseException( 'database_query_error', $this->db->get_last_error() );
            }

            if ( 0 === (int) $count ) {
                $issues[] = 'No user account exists.';
            }
        }

        return $issues;
    }

    /**
     * Whether the installation state records a completed installation.
     *
     * Reads the state file only; use installation_issues() to verify the
     * installation itself.
     *
     * @return bool
     */
    public function is_installed() : bool {
        return $this->state->is_installed();
    }

    /**
     * Mark an installation as started by writing the installation flag.
     *
     * From then on the web shows visitors the installation notice and only
     * the installer owner gets through. An existing flag of any reason is
     * left alone, so a running maintenance is never downgraded.
     *
     * @return void
     * @throws \RuntimeException When the flag cannot be written.
     */
    public function begin_installation() : void {
        if ( null === $this->flag->read() ) {
            $this->flag->write( MaintenanceFlag::REASON_INSTALLATION );
        }
    }

    /**
     * Record the installation as complete in the installation state file.
     *
     * Stores the current application and schema versions, then removes the
     * installation flag. Does not verify the installation; check
     * installation_issues() first.
     *
     * @return void
     * @throws \RuntimeException When the state file cannot be written.
     */
    public function mark_installed() : void {
        $this->state->mark_installed(
            array(
                'app'    => \SMLISER_VER,
                'schema' => \SMLISER_DB_VER,
            )
        );

        if ( $this->flag->is_installation() ) {
            $this->flag->clear();
        }
    }

    /**
     * Load a role by slug, tolerating an unavailable cache.
     *
     * Role::get_by_slug() goes through the cache, which is not set up during
     * a fresh installation, so a failure there is treated as "not loaded".
     *
     * @param string $slug Role slug.
     * @return Role|null
     */
    protected function find_role( string $slug ) : ?Role {
        static $logged = false;

        try {
            return Role::get_by_slug( $slug ) ?: null;
        } catch ( \Throwable $e ) {
            // Expected on every installer request until the cache exists; log once per request.
            if ( ! $logged ) {
                $logged = true;
                \smliser_log_error( sprintf( '[AppInstaller] Role lookup failed, falling back to a direct query: %s', $e->getMessage() ) );
            }

            return null;
        }
    }

    /**
     * Whether a role exists, using a direct query when the model lookup fails.
     *
     * The direct query bypasses the cache, so the answer is reliable on a
     * fresh installation; without it, a cache failure would read as
     * "missing" and the roles would be installed again on every run.
     *
     * @param string $slug Role slug.
     * @return bool
     */
    protected function role_exists( string $slug ) : bool {
        return null !== $this->find_role( $slug ) || in_array( $slug, $this->installed_role_slugs(), true );
    }

    /**
     * Slugs of the roles stored in the database, read directly (no cache).
     *
     * An empty result is not treated as an error: an empty table and a stale
     * error from an earlier query cannot be told apart reliably. A real query
     * failure surfaces when the missing roles are then installed.
     *
     * @return string[]
     */
    protected function installed_role_slugs() : array {
        $sql   = \smliserQueryBuilder( $this->db->get_driver() )
            ->select( 'slug' )
            ->from( \SMLISER_ROLES_TABLE );
        return array_map( 'strval', $this->db->get_col( $sql->build(), $sql->get_bindings() ) );
    }

    /**
     * Refuse database-backed steps while only the placeholder adapter is active.
     *
     * @return void
     * @throws DatabaseException When no database connection is active.
     */
    protected function assert_database_connection() : void {
        if ( ! $this->has_database_connection() ) {
            throw new DatabaseException(
                'database_not_configured',
                'No database connection is configured. Configure and test the database connection first.'
            );
        }
    }
}