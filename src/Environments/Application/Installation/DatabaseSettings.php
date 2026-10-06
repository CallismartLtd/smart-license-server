<?php
/**
 * Database settings class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use Callismart\DBPrism\DBConfigDTO;

/**
 * Turns the database settings a person types into a configuration, for both installers.
 *
 * The web installer (WebInstaller) and the command-line installer
 * (`installer run`) ask the same questions, apply the same defaults and
 * explain connection failures the same way through this class, so the two
 * cannot drift apart.
 *
 * @package SmartLicenseServer\Environments\Application\Installation
 * @since 0.2.0
 */
final class DatabaseSettings {

	/**
	 * Supported database drivers: display label, short description, default port and default charset.
	 *
	 * The charset values follow each engine's naming: MySQL "utf8mb4" (full
	 * Unicode; its "utf8" lacks emoji), PostgreSQL "UTF8". SQLite has none.
	 */
	public const DRIVERS = array(
		'mysql'  => array(
			'label'   => 'MySQL / MariaDB',
			'help'    => 'Offered by most hosting plans.',
			'port'    => 3306,
			'charset' => 'utf8mb4',
		),
		'pgsql'  => array(
			'label'   => 'PostgreSQL',
			'help'    => 'Choose it if your host gave you a PostgreSQL database.',
			'port'    => 5432,
			'charset' => 'UTF8',
		),
		'sqlite' => array(
			'label'   => 'SQLite',
			'help'    => 'A single file; no database server needed. Good for small sites and testing.',
			'port'    => null,
			'charset' => null,
		),
	);

	/**
	 * Host used when the server address is left empty.
	 */
	public const DEFAULT_HOST = 'localhost';

	/**
	 * Fields accepted by normalize(), named as in DBConfigDTO.
	 */
	public const FIELDS = array( 'driver', 'host', 'port', 'dbname', 'username', 'password', 'prefix', 'charset', 'path', 'encryption_key' );

	/**
	 * Fields used exactly as typed: never trimmed, never shown back.
	 */
	public const SECRET_FIELDS = array( 'password', 'encryption_key' );

	/**
	 * Static class.
	 */
	private function __construct() {}

	/**
	 * Build a database configuration from the values a person entered.
	 *
	 * Empty values fall back to the standard ones: server address
	 * "localhost", the engine's port and charset, and the storage folder for
	 * SQLite. For SQLite the charset is set to an empty string, so a charset
	 * left in the .env file (e.g. utf8mb4 from .env.example) is cleared when
	 * the configuration is saved.
	 *
	 * @param array<string, mixed> $input Values keyed by FIELDS; missing keys count as empty.
	 * @return DBConfigDTO
	 * @throws \InvalidArgumentException With a message meant for the person who typed the values.
	 *                                   When DBConfigDTO rejects a value, its message is the previous exception.
	 */
	public static function normalize( array $input ) : DBConfigDTO {
		$values = array();

		foreach ( self::FIELDS as $field ) {
			$value = $input[ $field ] ?? '';
			$value = is_scalar( $value ) ? (string) $value : '';

			$values[ $field ] = in_array( $field, self::SECRET_FIELDS, true ) ? $value : trim( $value );
		}

		$driver = strtolower( $values['driver'] );

		if ( ! isset( self::DRIVERS[ $driver ] ) ) {
			throw new \InvalidArgumentException( 'Choose the type of database you are using.' );
		}

		$values['driver'] = $driver;

		if ( '' === $values['dbname'] ) {
			throw new \InvalidArgumentException( 'Enter the database name.' );
		}

		if ( ! preg_match( '/^[A-Za-z0-9_]{0,32}$/', $values['prefix'] ) ) {
			throw new \InvalidArgumentException( 'The table prefix can only contain letters, numbers and "_" (at most 32 characters). Leave it empty to keep the current one.' );
		}

		if ( 'sqlite' === $driver ) {
			$values['host']    = '';
			$values['port']    = '';
			$values['charset'] = '';
			$values['path']    = '' === $values['path'] ? self::default_sqlite_dir() : $values['path'];
		} else {
			$values['host']    = '' === $values['host'] ? self::DEFAULT_HOST : $values['host'];
			$values['port']    = '' === $values['port'] ? (string) self::DRIVERS[ $driver ]['port'] : $values['port'];
			$values['charset'] = '' === $values['charset'] ? (string) self::DRIVERS[ $driver ]['charset'] : $values['charset'];

			if ( ! ctype_digit( $values['port'] ) || (int) $values['port'] < 1 || (int) $values['port'] > 65535 ) {
				throw new \InvalidArgumentException( 'The port must be a number between 1 and 65535. Leave it empty to use the standard port.' );
			}

			if ( ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $values['charset'] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The character set can only contain letters, numbers, "_" and "-". Leave it empty to use %s.',
						self::DRIVERS[ $driver ]['charset']
					)
				);
			}
		}

		$env = array();

		foreach ( \smliser_db_env_keys() as $field => $key ) {
			$env[ $key ] = $values[ $field ] ?? '';
		}

		try {
			$config = \smliser_db_config_from_env( $env );
		} catch ( \InvalidArgumentException $e ) {
			throw new \InvalidArgumentException( 'Some database settings are not valid.', 0, $e );
		}

		if ( null === $config ) {
			throw new \InvalidArgumentException( 'Enter the database name.' );
		}

		if ( 'sqlite' === $driver ) {
			$config->charset = '';
		}

		return $config;
	}

	/**
	 * The editable values of a configuration, as strings keyed by field.
	 *
	 * For pre-filling questions with what is already saved. Secret fields are
	 * left out.
	 *
	 * @param DBConfigDTO $config Configuration.
	 * @return array<string, string>
	 */
	public static function values( DBConfigDTO $config ) : array {
		$values = array();

		foreach ( array_diff( self::FIELDS, self::SECRET_FIELDS ) as $field ) {
			$value            = $config->{$field};
			$values[ $field ] = null === $value ? '' : (string) $value;
		}

		return $values;
	}

	/**
	 * Folder used for an SQLite database when none is given.
	 *
	 * @return string
	 */
	public static function default_sqlite_dir() : string {
		return rtrim( \SMLISER_STORAGE_DIR, '/\\' );
	}

	/**
	 * Translate a database driver error into advice a non-developer can act on.
	 *
	 * @param string $raw    The driver's error message.
	 * @param string $driver The database driver.
	 * @return string
	 */
	public static function explain_error( string $raw, string $driver ) : string {
		$message = strtolower( $raw );
		$has     = static fn ( string ...$needles ) : bool => array_reduce(
			$needles,
			static fn ( bool $found, string $needle ) : bool => $found || str_contains( $message, $needle ),
			false
		);

		return match ( true ) {
			$has( 'access denied', 'password authentication failed', 'authentication failed' )
				=> 'The username or password was rejected. Check them in your hosting control panel, and make sure the user has been given access to this database.',
			$has( 'unknown database', 'does not exist' ) && ! $has( 'role' )
				=> 'That database does not exist. Create it in your hosting control panel first, then check the spelling here (some hosts add your account name in front).',
			$has( 'role' ) && $has( 'does not exist' )
				=> 'That database user does not exist. Check the username.',
			$has( 'getaddrinfo', 'name or service not known', 'unknown mysql server host', 'could not translate host name', 'no such host' )
				=> 'The server address could not be found. Check the server address; it is usually localhost.',
			$has( 'connection refused', "can't connect", 'could not connect to server' )
				=> 'Nothing answered at that server address and port. Check both; leave the port empty to use the standard one.',
			$has( 'timed out', 'timeout' )
				=> 'The database server did not answer in time. Check the server address, or ask your host whether remote connections are allowed.',
			$has( 'could not find driver', 'driver' ) && $has( 'not', 'missing', 'find' )
				=> 'The PHP extension for this database type is not installed on the server. Ask your host to enable it, or choose another database type.',
			'sqlite' === $driver && $has( 'unable to open', 'readonly', 'read-only', 'permission' )
				=> 'The database file could not be created or opened. Make sure the database folder exists and is writable by the web server.',
			default
				=> 'Check the details and try again.',
		};
	}
}