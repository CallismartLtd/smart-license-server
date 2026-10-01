<?php
/**
 * Database related utility functions.
 */

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Query\SQLBuilder;

/**
 * Get the query builder instance.
 * 
 * @param string $driver The DB driver.
 * @return SQLBuilder Instance of the SQLBuilder class.
 */
function smliserQueryBuilder( string $driver ) : SQLBuilder{
    return new SQLBuilder( $driver );
}

/**
 * Get the database schema inpection instance
 */
function smliserDBSchemaInspection( Database $db ) : Inspector {
    return new Inspector( $db );
}

/**
 * Database configuration functions file.
 *
 * Single source for mapping SMLISER_DB_* environment variables to a
 * DBConfigDTO, shared by the application environment and the installer.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */
 
/**
 * Get the environment variable names that hold the database configuration.
 *
 * Keyed by DBConfigDTO field. `encryption_key` only applies to the sqlite driver.
 *
 * @return array<string, string> DBConfigDTO field => environment variable name.
 */
function smliser_db_env_keys() : array {
	return array(
		'driver'         => 'SMLISER_DB_DRIVER',
		'host'           => 'SMLISER_DB_HOST',
		'port'           => 'SMLISER_DB_PORT',
		'dbname'         => 'SMLISER_DB_NAME',
		'username'       => 'SMLISER_DB_USER',
		'password'       => 'SMLISER_DB_PASSWORD',
		'charset'        => 'SMLISER_DB_CHARSET',
		'prefix'         => 'SMLISER_DB_PREFIX',
		'path'           => 'SMLISER_DB_PATH',
		'encryption_key' => 'SMLISER_SQLITE_ENCRYPTION_KEY',
	);
}
 
/**
 * Build the database configuration from environment variables.
 *
 * Unset or empty variables are passed to the DTO as null, so the DTO and
 * the adapters apply their own defaults (e.g. port stays null, not 0).
 *
 * @param array<string, mixed> $env Environment variables, e.g. $_ENV or DotEnv::parse() output.
 * @return \Callismart\DBPrism\DBConfigDTO|null Null when SMLISER_DB_DRIVER or SMLISER_DB_NAME
 *                                              is empty, meaning no database is configured.
 * @throws \InvalidArgumentException When a value is invalid (e.g. an unsupported driver).
 */
function smliser_db_config_from_env( array $env ) : ?\Callismart\DBPrism\DBConfigDTO {
	$keys  = smliser_db_env_keys();
	$value = static function ( string $field ) use ( $env, $keys ) : mixed {
		$raw = $env[ $keys[ $field ] ] ?? null;
 
		return ( null === $raw || '' === trim( (string) $raw ) ) ? null : $raw;
	};
 
	if ( null === $value( 'driver' ) || null === $value( 'dbname' ) ) {
		return null;
	}
 
	$fields = array();
 
	foreach ( array_keys( $keys ) as $field ) {
		if ( 'encryption_key' !== $field ) {
			$fields[ $field ] = $value( $field );
		}
	}
 
	$config = new \Callismart\DBPrism\DBConfigDTO( $fields );
 
	if ( 'sqlite' === $config->driver && null !== $value( 'encryption_key' ) ) {
		$config->encryption_key = $value( 'encryption_key' );
	}
 
	return $config;
}