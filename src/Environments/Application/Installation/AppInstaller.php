<?php
/**
 * App installer class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use SmartLicenseServer\Schema\TableName;
use Callismart\DBPrism\Adapters\Contracts\DatabaseAdapterInterface;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use Callismart\DBPrism\Exceptions\MigrationException;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Migrations\Migrator;
use Callismart\DBPrism\Utils\Table;
use SmartLicenseServer\Background\Jobs\Accounts\SignupEmailJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Core\DotEnv;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environments\Application\Assets\PublicAssets;
use SmartLicenseServer\Environments\Application\Boot\InstallationState;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;
use SmartLicenseServer\Environments\Application\Release\ReleaseManifest;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Schema\DatabaseAdapterRegistry;
use SmartLicenseServer\Schema\MigrationRegistry;
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
 * - starts the migration ledger and creates all the registered tables.
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
	protected const APP_SECRET_KEYS = [
		'SMLISER_SECRET',
		'SMLISER_SALT',
	];

	/**
	 * Random bytes per generated secret (base64-encodes to 64 characters).
	 */
	protected const APP_SECRET_BYTES = 48;

	/**
	 * .env key holding the application's public URL.
	 */
	public const APP_URL_KEY = 'SMLISER_APP_URL';

	/**
	 * Results of link_public_assets() and refresh_public_assets().
	 */
	public const ASSETS_LINKED    = PublicAssets::LINKED;    // A symlink was created.
	public const ASSETS_COPIED    = PublicAssets::COPIED;    // Symlinks are unavailable; the assets were copied.
	public const ASSETS_UNCHANGED = PublicAssets::UNCHANGED; // The correct symlink already existed.
	public const ASSETS_KEPT      = PublicAssets::KEPT;      // A folder not made by the installer exists; left alone.

	/**
	 * Marker file identifying a copy of the assets.
	 */
	public const ASSETS_COPY_MARKER = PublicAssets::COPY_MARKER;

	/**
	 * Folders of system/ published in public/: public name => path inside system/.
	 *
	 * The core assets, and the default theme's assets (every theme's assets
	 * are published at themes/<slug>).
	 */
	public const BUNDLED_ASSETS = [
		'assets'         => 'assets',
		'themes/default' => 'themes/default/assets',
	];

	/**
	 * Minimum administrator password length, for every installer.
	 */
	public const MIN_ADMIN_PASSWORD_LENGTH = 8;

	/**
	 * The required directories keyed by readable names.
	 * 
	 * @var array<string, string>
	 */
	protected array $required_directories = [
		'Application Root'             => \SMLISER_ROOT,
		'Runtime Directory'            => \SMLISER_RUNTIME_DIR,
		'Storage Directory'            => \SMLISER_STORAGE_DIR,
		'Repository Root'              => \SMLISER_REPO_DIR,
		'WordPress Plugins Repository' => \SMLISER_WP_PLUGINS_REPO_DIR,
		'WordPress Themes Repository'  => \SMLISER_WP_THEMES_REPO_DIR,
		'Software Repository'          => \SMLISER_SOFTWARE_REPO_DIR,
		'Cache Directory'              => \SMLISER_CACHE_DIR,
		'Temporary Directory'          => \SMLISER_TMP_DIR,
		'Uploads Directory'            => \SMLISER_UPLOADS_DIR,
		'Private Directory'            => \SMLISER_PRIVATE_DIR,
		'Logs Directory'               => \SMLISER_LOGS_DIR,
	];

	/**
	 * Class constructor.
	 *
	 * The Database may hold a NullDBAdapter placeholder before installation.
	 * Database-backed steps refuse to run until a verified connection has
	 * been activated with use_connection().
	 *
	 * @param Database                $db            The shared application database.
	 * @param DatabaseAdapterRegistry $adapters      Database adapter registry.
	 * @param FileSystem              $fs            Filesystem API.
	 * @param JobQueue                $job_queue     Background job queue.
	 * @param InstallationState       $state         The installation state file.
	 * @param MaintenanceFlag         $flag          The maintenance flag file.
	 * @param SchemaRegistry          $schema        The schema registry; holds the table prefix.
	 * @param PublicAssets            $public_assets Publishes static folders in public/.
	 */
	public function __construct(
		protected Database $db,
		protected DatabaseAdapterRegistry $adapters,
		protected FileSystem $fs,
		protected JobQueue $job_queue,
		protected InstallationState $state,
		protected MaintenanceFlag $flag,
		protected SchemaRegistry $schema,
		protected PublicAssets $public_assets
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
	) : array {
		$results = [
			'passed'          => true,
			'errors'          => [],
			'warnings'        => [],
			'recommendations' => [],
		];

		$report = function( string $check, bool $is_ok, string $message, string $level = 'CRITICAL' ) use ( &$results, $success_callback, $failure_callback ) : void {
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
		$env_file = SMLISER_ROOT . '.env';

		if ( file_exists( $env_file ) && ! $overwrite ) {
			return $env_file;
		}

		if ( null === $env_example_path ) {
			$env_example_path = SMLISER_ROOT . '.env.example';

			if ( ! file_exists( $env_example_path ) ) {
				$env_example_path = dirname( \SMLISER_ROOT ) . '/.env.example';
			}

			if ( ! file_exists( $env_example_path ) ) {
				$env_example_path = SMLISER_RUNTIME_DIR . '.env.example';
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
	 * Publish the bundled assets (see BUNDLED_ASSETS) in public/.
	 *
	 * See PublicAssets::publish() for what is linked, copied, replaced or
	 * kept.
	 *
	 * @param bool $force Rebuild even when the correct links already exist.
	 * @return array<string, string> Public name => one of the ASSETS_* constants.
	 * @throws \RuntimeException When a source folder is missing or the assets cannot be published.
	 */
	public function link_public_assets( bool $force = false ) : array {
		$results = [];

		foreach ( $this->bundled_assets() as $name => $source ) {
			if ( ! $this->fs->is_dir( $source ) ) {
				throw new \RuntimeException( "The assets folder \"{$source}\" does not exist. Re-upload the application files." );
			}

			$results[ $name ] = $this->public_assets->publish( $source, $name, $force );
		}

		return $results;
	}

	/**
	 * Republish the bundled assets after system/ was replaced (update, rollback).
	 *
	 * A link already shows the new files; a copy is rebuilt.
	 *
	 * @return array<string, string> Public name => one of the ASSETS_* constants.
	 * @throws \RuntimeException When a source folder is missing or the assets cannot be published.
	 */
	public function refresh_public_assets() : array {
		$results = [];

		foreach ( $this->bundled_assets() as $name => $source ) {
			$results[ $name ] = $this->public_assets->refresh( $source, $name );
		}

		return $results;
	}

	/**
	 * Bundled assets that are not reachable in public/.
	 *
	 * @return array<string, string> Public name => absolute path where it should be.
	 */
	public function missing_public_assets() : array {
		$missing = [];

		foreach ( array_keys( static::BUNDLED_ASSETS ) as $name ) {
			if ( ! $this->public_assets->is_published( $name ) ) {
				$missing[ $name ] = $this->public_assets->path( $name );
			}
		}

		return $missing;
	}

	/**
	 * Whether every bundled asset folder is reachable in public/.
	 *
	 * @return bool
	 */
	public function public_assets_available() : bool {
		return [] === $this->missing_public_assets();
	}

	/**
	 * Absolute paths of the bundled asset folders.
	 *
	 * @return array<string, string> Public name => absolute path inside system/.
	 */
	public function bundled_assets() : array {
		$runtime = rtrim( \SMLISER_RUNTIME_DIR, '/\\' );

		return array_map( static fn( string $relative ) : string => $runtime . '/' . $relative, static::BUNDLED_ASSETS );
	}

	/**
	 * Absolute path where a public name is published.
	 *
	 * @param string $name e.g. "assets" or "themes/default".
	 * @return string
	 */
	public function public_asset_path( string $name ) : string {
		return $this->public_assets->path( $name );
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

		$values = [];

		foreach ( \smliser_db_env_keys() as $field => $env_key ) {
			// The encryption key only applies to sqlite.
			if ( 'encryption_key' === $field && 'sqlite' !== $config->driver ) {
				continue;
			}

			$values[ $env_key ] = $config->{$field};
		}

		$values = array_map(
			[ $this, 'env_string' ],
			array_filter( $values, static fn( mixed $value ) : bool => null !== $value )
		);

		if ( [] === $values ) {
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
		$in_use    = [];
		$generated = [];

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

		if ( [] === $generated ) {
			return [];
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

		if ( null === $origin || ! in_array( strtolower( (string) $parsed->get_scheme() ), [ 'http', 'https' ], true ) ) {
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
	 * Starts the migration ledger first (see start_migration_ledger()), so
	 * the database records which schema its tables were created at. Tables
	 * that exist are left as they are.
	 *
	 * @param callable(string $table_name, string $message)|null $success_callback
	 * @param callable(string $table_name, string $message)|null $failure_callback
	 * @return void
	 * @throws DatabaseException When no database connection is active, or the migration ledger cannot be started.
	 */
	public function create_tables(
		?callable $success_callback = null,
		?callable $failure_callback = null
	) : void {
		$this->assert_database_connection();

		$inspector = new Inspector( $this->db );
		$tables    = $this->schema->get_all_tables();
		$existing  = [];

		foreach ( $tables as $table ) {
			$existing[ $table->get_name() ] = $inspector->table_exists( $table->get_name() );
		}

		$this->start_migration_ledger( in_array( true, $existing, true ) );

		foreach ( $tables as $table ) {
			if ( $existing[ $table->get_name() ] ) {
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
	 * Build the Migrator for the application's database.
	 *
	 * The migrations come from MigrationRegistry. The ledger tables take the
	 * table prefix: {prefix}migrations and {prefix}migrations_lock. A new
	 * Migrator is built on each call, so a prefix changed by use_connection()
	 * applies at once.
	 *
	 * @return Migrator
	 * @throws \LogicException When the table prefix has not been set.
	 */
	public function migrator() : Migrator {
		return new Migrator( $this->db, MigrationRegistry::all(), $this->schema->prefix() );
	}

	/**
	 * Start the migration ledger, before any table is created.
	 *
	 * The ledger records which migrations a database contains. What a
	 * database without one contains depends on whether it holds tables:
	 *
	 *  - No ledger, no registered table: a fresh database. The tables about
	 *    to be created are the current schema, which already contains every
	 *    registered migration, so all of them are recorded as applied
	 *    without running (Migrator::baseline()).
	 *  - No ledger, registered tables present: a database created before
	 *    the ledger existed, at schema 0.2.0. Every registered migration is
	 *    run on it (Migrator::migrate()).
	 *  - A ledger: the installer ran on this database before, perhaps
	 *    interrupted after this step. Nothing is done.
	 *
	 * Running it before the tables are created is what lets an interrupted
	 * installation resume: the second run finds the ledger and creates the
	 * remaining tables at the current schema.
	 *
	 * @param bool $has_tables Whether any registered table exists.
	 * @return void
	 * @throws DatabaseException When the ledger cannot be read or started, or a migration fails.
	 */
	protected function start_migration_ledger( bool $has_tables ) : void {
		$migrator = $this->migrator();

		try {
			if ( method_exists( $migrator, 'has_ledger' ) && $migrator->has_ledger() ) {
				return;
			}

			$has_tables ? $migrator->migrate() : $migrator->baseline();
		} catch ( MigrationException $e ) {
			throw new DatabaseException( 'migration_error', $e->getMessage() );
		}
	}

	/**
	 * Create a single database table from a column definition array.
	 *
	 * @param Table $table
	 * @return bool
	 */
	protected function create_table( Table $table ) : bool {
		$charset_collate = $this->db->get_charset_collate();

		$query = \smliserQueryBuilder( $this->db->get_driver() )
			->create_table( $table->get_name() )
			->add_columns( $table->get_columns() )
			->add_constraints( $table->get_constraints() );
		$sql = $query->build() . '' . $charset_collate;
		
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
	 * Check the details of a new administrator account.
	 *
	 * The rules every installer applies before create_admin(). Only the
	 * values are checked; whether the email address is already in use is not.
	 *
	 * @param string $name     Display name.
	 * @param string $email    Email address.
	 * @param string $password Password, as typed.
	 * @return array<string, string> Problems keyed by "name", "email" or "password"; empty when all are valid.
	 */
	public function validate_admin( string $name, string $email, string $password ) : array {
		$errors = [];

		if ( '' === trim( $name ) ) {
			$errors['name'] = 'Enter your name.';
		}

		if ( false === filter_var( trim( $email ), FILTER_VALIDATE_EMAIL ) ) {
			$errors['email'] = 'Enter a valid email address, for example you@example.com.';
		}

		if ( mb_strlen( $password ) < static::MIN_ADMIN_PASSWORD_LENGTH ) {
			$errors['password'] = sprintf( 'The password must be at least %d characters long.', static::MIN_ADMIN_PASSWORD_LENGTH );
		}

		return $errors;
	}

	/**
	 * Whether at least one user account exists.
	 *
	 * Queries the users table directly (no cache), so the answer is reliable
	 * during a fresh installation. A missing users table counts as no users.
	 *
	 * @return bool
	 * @throws DatabaseException When no database connection is active, or the query fails.
	 */
	public function has_users() : bool {
		$this->assert_database_connection();

		if ( ! ( new Inspector( $this->db ) )->table_exists( $this->schema->table_name( TableName::USERS ) ) ) {
			return false;
		}

		$sql = \smliserQueryBuilder( $this->db->get_driver() )
			->select( 'COUNT(*)' )
			->from( $this->schema->table_name( TableName::USERS ) );
		$count = $this->db->get_var( $sql->build(), $sql->get_bindings() );

		if ( null === $count && $this->db->get_last_error() ) {
			throw new DatabaseException( 'database_query_error', $this->db->get_last_error() );
		}

		return (int) $count > 0;
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

		$default_role = DefaultRoles::get( 'system_admin' );
		
		$role = $this->find_role( $default_role['slug'] ) ?? new Role();

		$role->set_label( $default_role['label'] )
			->set_slug( $default_role['slug'] )
			->set_capabilities( $default_role['capabilities'] )
			->set_is_canonical( $default_role['is_canonical'] );
		
		$admin = User::get_by_email( $email );

		if ( ! $admin ) {
			$admin = ( new User() )
				->set_display_name( $name )
				->set_email( $email )
				->set_password_hash( password_hash( $password, PASSWORD_ARGON2ID ) )
				->set_created_at( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )
				->set_id( 0 )
				->set_status( User::STATUS_ACTIVE )
				// The installer typed this address in themselves; nothing to prove.
				->set_email_verified_at( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
			
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
		) );

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
	 * connection from its next query onward. The connection's table prefix
	 * is applied to the schema registry at the same time, so table names
	 * resolved afterwards (TableName::X->table(), SchemaRegistry::table_name())
	 * match the database in use. A configuration without a prefix leaves the
	 * current one: the .env value is then unchanged too.
	 *
	 * @param DatabaseAdapterInterface $adapter A connected adapter, typically from test_db_connection().
	 * @return void
	 * @throws DatabaseException         When the adapter is not connected.
	 * @throws \InvalidArgumentException When the configured prefix is not a valid table prefix.
	 */
	public function use_connection( DatabaseAdapterInterface $adapter ) : void {
		if ( ! $adapter->is_connected() ) {
			throw new DatabaseException(
				'database_connect_error',
				'Only a connected database adapter can be activated.'
			);
		}

		$prefix = $adapter->get_config()->prefix;

		if ( null !== $prefix ) {
			$this->schema->set_prefix( (string) $prefix );
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
		$issues = [];

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

		foreach ( $this->missing_public_assets() as $path ) {
			$issues[] = sprintf( 'The public assets are not published: %s', $path );
		}

		if ( ! $this->has_database_connection() ) {
			$issues[] = 'No database connection is configured.';
			return $issues;
		}

		if ( ! $this->db->is_connected() && ! $this->db->connect() ) {
			throw new DatabaseException( 'database_connect_error', $this->db->get_last_error() );
		}

		$inspector      = new Inspector( $this->db );
		$missing_tables = false;

		foreach ( $this->schema->get_all_tables() as $table ) {
			if ( ! $inspector->table_exists( $table->get_name() ) ) {
				$issues[]       = sprintf( 'Missing table: %s', $table->get_name() );
				$missing_tables = true;
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

		if ( ! $this->has_users() ) {
			$issues[] = 'No user account exists.';
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
			[
				'app'    => \SMLISER_VER,
				'schema' => \SMLISER_DB_VER,
			]
		);

		if ( $this->flag->is_installation() ) {
			$this->flag->clear();
		}
	}

	/**
	 * Check the application files against the release manifest.
	 *
	 * A missing manifest is only a warning: source checkouts have none.
	 *
	 * @param callable(int $done, int $total)|null $progress Optional progress callback.
	 * @return array{passed: bool, errors: string[], warnings: string[]}
	 */
	public function verify_release_files( ?callable $progress = null ) : array {
		$result = [ 'passed' => true, 'errors' => [], 'warnings' => [] ];

		try {
			$manifest = ReleaseManifest::load( $this->fs, \SMLISER_ROOT );
		} catch ( \RuntimeException $e ) {
			return [ 'passed' => false, 'errors' => [ $e->getMessage() . ' Upload the release again.' ], 'warnings' => [] ];
		}

		if ( null === $manifest ) {
			$result['warnings'][] = 'There is no manifest.json, so the application files cannot be verified. This is expected for a source checkout.';
			return $result;
		}

		if ( \SMLISER_VER !== $manifest->version ) {
			$result['passed']   = false;
			$result['errors'][] = sprintf( 'manifest.json is for version %s, but the code is version %s: files from two releases are mixed. Upload one release completely.', $manifest->version, \SMLISER_VER );
		}

		$check = $manifest->verify( $this->fs, \SMLISER_ROOT, $progress );

		if ( ! $check->passed() ) {
			$result['passed'] = false;
			array_push( $result['errors'], ...$check->messages() );
			$result['errors'][] = 'The upload is incomplete or damaged. Upload the release again (FTP clients should use binary mode).';
		}

		return $result;
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
		$sql = \smliserQueryBuilder( $this->db->get_driver() )
			->select( 'slug' )
			->from( $this->schema->table_name( TableName::ROLES ) );
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
