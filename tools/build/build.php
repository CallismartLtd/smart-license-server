<?php
/**
 * Smart License Server build script.
 *
 * Usage:
 *   php tools/build/build.php <target> [options]
 *
 * Targets:
 *   standalone          Standalone PHP application.
 *
 * Options:
 *   --core-dir=<name>   Directory name for the copied src/. Default: smliser
 *   --out=<dir>         Output directory. Default: <repo>/dist/<target>
 *   --composer=<path>   Composer binary. Default: composer
 *   --help              Show this help.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

use SmartLicenseServer\Build\BuildConsole;
use SmartLicenseServer\Build\BuildContext;
use SmartLicenseServer\Build\BuildException;
use SmartLicenseServer\Build\Builder;
use SmartLicenseServer\Build\Targets\StandaloneTarget;

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

/*
|---------
| Autoload
|---------
*/
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'SmartLicenseServer\\Build\\';

		if ( str_starts_with( $class, $prefix ) ) {
			$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

			if ( is_file( $file ) ) {
				require_once $file;
			} else {
                var_dump( $file ); exit;
            }
		}
	}
);

/*
|-------------------
| Registered targets
|-------------------
*/
$targets = array(
	'standalone' => StandaloneTarget::class,
);

/*
|------------
| Entry point
|------------
*/
$console = new BuildConsole();

try {
	// getopt() stops at the first positional argument, so parse by hand to
	// allow options on either side of the target name.
	$allowed    = ['core-dir' => true, 'out' => true, 'composer' => true, 'help' => false ];
	$options    = [];
	$name       = null;

	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( ! str_starts_with( $arg, '--' ) ) {
			if ( null !== $name ) {
				throw new BuildException( "Unexpected argument \"{$arg}\"." );
			}
			$name = $arg;
			continue;
		}

		[ $key, $value ] = array_pad( explode( '=', substr( $arg, 2 ), 2 ), 2, null );

		if ( ! array_key_exists( $key, $allowed ) ) {
			throw new BuildException( "Unknown option --{$key}." );
		}

		if ( $allowed[ $key ] && ( null === $value || '' === $value ) ) {
			throw new BuildException( "Option --{$key} requires a value (--{$key}=<value>)." );
		}

		$options[ $key ] = $allowed[ $key ] ? $value : true;
	}

	if ( isset( $options['help'] ) || null === $name ) {
		preg_match( '#/\*\*(.*?)\*/#s', (string) file_get_contents( __FILE__ ), $match );
		$console->write( trim( preg_replace( '/^\s*\* ?/m', '', $match[1] ?? '' ) ) );
		exit( isset( $options['help'] ) ? 0 : 1 );
	}

	if ( ! isset( $targets[ $name ] ) ) {
		throw new BuildException( "Unknown target \"{$name}\". Available: " . implode( ', ', array_keys( $targets ) ) );
	}

	$core_dir = (string) ( $options['core-dir'] ?? 'smliser' );
	if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $core_dir ) ) {
		throw new BuildException( '--core-dir must be a single directory name (letters, digits, "_" or "-").' );
	}

	$repo_root = dirname( __DIR__, 2 );
	$out_dir   = (string) ( $options['out'] ?? $repo_root . '/dist/' . $name );

	if ( ! str_starts_with( $out_dir, '/' ) && 1 !== preg_match( '#^[A-Za-z]:[/\\\\]#', $out_dir ) ) {
		$out_dir = getcwd() . '/' . $out_dir;
	}

	$context = new BuildContext(
		$repo_root,
		rtrim( $out_dir, '/\\' ),
		$core_dir,
		(string) ( $options['composer'] ?? 'composer' )
	);

	( new Builder( $context, $console ) )->run( new $targets[ $name ]() );
	exit( 0 );
} catch ( BuildException $e ) {
	$console->error( $e->getMessage() );
	exit( 1 );
}