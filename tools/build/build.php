<?php
/**
 * Smart License Server build script.
 *
 * Usage:
 *   php tools/build/build.php <target> [options]
 *   php tools/build/build.php <target> --link [options]
 *   php tools/build/build.php --generate-key=<path>
 *
 * Targets:
 *   standalone          Standalone PHP application.
 *
 * Every build writes the application folder and the release artifacts:
 *   <out>/                                   The built application, with server/ (nginx,
 *                                            Caddy, Apache configs) and manifest.json.
 *   <out>/../release/<name>-<version>-<target>.zip
 *                    <name>-<version>-<target>.tar.gz
 *                    <name>-<version>-<target>-setup.php   Single-file installer.
 *                    <name>-<version>-<target>.sha256      Checksums of the above.
 *                    <name>-<version>-<target>.sha256.sig  Signature of the checksums (signed builds).
 * <name> comes from composer.json, <version> from SMLISER_VER under src/.
 *
 * With --link, nothing is copied or released. The output folder is the release layout
 * with this checkout standing in for the copy: entry points and server files are
 * generated, and src/, templates/, assets/, themes/ and vendor/ are linked, so an edit
 * is live on the next request. Running it again only repairs links; .env, storage/ and
 * the installation are kept. Installs vendor/ with Composer when it is missing. Use a
 * normal build to test what ships.
 *
 * Options:
 *   --link              Link the application to this checkout instead of copying it, for
 *                       development. Cannot be combined with --production, --sign-key,
 *                       --allow-unsigned, --no-minify, --npm or --node.
 *   --out=<dir>         Output directory. Default: <repo>/dist/<target>, or <repo>/dist/dev
 *                       with --link.
 *   --composer=<path>   Composer binary. Default: composer
 *   --npm=<path>        npm binary, used once to install esbuild. Default: npm
 *   --node=<path>       Node.js binary, used to run esbuild. Default: node
 *   --no-minify         Skip writing minified *.min.js / *.min.css assets.
 *   --production        Build with the production update server URL.
 *   --sign-key=<path>   Ed25519 key that signs the .sha256 checksums. Default: the path in
 *                       SMLISER_SIGNING_KEY. Required: the build stops unless its public
 *                       key is listed in ReleaseSignature::PUBLIC_KEYS, so every release
 *                       can verify the next one.
 *   --allow-unsigned    Build without a signing key, for local testing only. PUBLIC_KEYS
 *                       must still be filled in; installations refuse the build.
 *   --generate-key=<path>
 *                       Create a signing key (no build). Keep it outside the repository,
 *                       and put the printed public key in ReleaseSignature::PUBLIC_KEYS.
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
use SmartLicenseServer\Build\LinkedBuilder;
use SmartLicenseServer\Build\ProjectInfo;
use SmartLicenseServer\Build\ReleaseSigner;
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
	static function ( string $class ) : void {
		$prefix = 'SmartLicenseServer\\Build\\';

		if ( str_starts_with( $class, $prefix ) ) {
			$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

			if ( is_file( $file ) ) {
				require_once $file;
			}
		}
	}
);

/*
|-------------------
| Registered targets
|-------------------
*/
$targets = [
	'standalone' => StandaloneTarget::class,
];

/*
|------------
| Entry point
|------------
*/
$console = new BuildConsole();

try {
	// getopt() stops at the first positional argument, so parse by hand to
	// allow options on either side of the target name.
	$allowed = [ 'out' => true, 'composer' => true, 'npm' => true, 'node' => true, 'no-minify' => false, 'production' => false, 'help' => false, 'sign-key' => true, 'generate-key' => true, 'allow-unsigned' => false, 'link' => false ];
	$options = [];
	$name    = null;

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

	if ( isset( $options['generate-key'] ) ) {
		$public = ReleaseSigner::generate_key( (string) $options['generate-key'] );

		$console->success( sprintf( 'Signing key written to %s. Keep it secret and out of the repository.', $options['generate-key'] ) );
		$console->write( 'Public key, for ReleaseSignature::PUBLIC_KEYS:' );
		$console->write( $public );
		exit( 0 );
	}

	if ( isset( $options['help'] ) || null === $name ) {
		preg_match( '#/\*\*(.*?)\*/#s', (string) file_get_contents( __FILE__ ), $match );
		$console->write( trim( preg_replace( '/^\s*\* ?/m', '', $match[1] ?? '' ) ) );
		exit( isset( $options['help'] ) ? 0 : 1 );
	}

	if ( ! isset( $targets[ $name ] ) ) {
		throw new BuildException( "Unknown target \"{$name}\". Available: " . implode( ', ', array_keys( $targets ) ) );
	}

	$repo_root = dirname( __DIR__, 2 );
	$linked    = isset( $options['link'] );
	$out_dir   = (string) ( $options['out'] ?? $repo_root . '/dist/' . ( $linked ? 'dev' : $name ) );

	if ( $linked ) {
		foreach ( [ 'production', 'sign-key', 'allow-unsigned', 'no-minify', 'npm', 'node' ] as $release_only ) {
			if ( isset( $options[ $release_only ] ) ) {
				throw new BuildException( "--{$release_only} belongs to a release build and cannot be combined with --link." );
			}
		}
	}

	if ( ! str_starts_with( $out_dir, '/' ) && 1 !== preg_match( '#^[A-Za-z]:[/\\\\]#', $out_dir ) ) {
		$out_dir = getcwd() . '/' . $out_dir;
	}

	$out_dir = rtrim( $out_dir, '/\\' );

	// Resolve "..", so messages and the release directory show a clean path.
	$parent = realpath( dirname( $out_dir ) );

	if ( false !== $parent ) {
		$out_dir = $parent . '/' . basename( $out_dir );
	}

	$context = new BuildContext(
		$repo_root,
		$out_dir,
		dirname( $out_dir ) . '/release',
		ProjectInfo::from_repo( $repo_root ),
		(string) ( $options['composer'] ?? 'composer' ),
		(string) ( $options['npm'] ?? 'npm' ),
		! isset( $options['no-minify'] ),
		(string) ( $options['node'] ?? 'node' ),
		( $options['sign-key'] ?? getenv( 'SMLISER_SIGNING_KEY' ) ) ?: null,
		isset( $options['allow-unsigned'] ),
		isset( $options['production'] )
	);

	$target = new $targets[ $name ]();

	if ( $linked ) {
		( new LinkedBuilder( $context, $console ) )->run( $target );
		exit( 0 );
	}

	( new Builder( $context, $console ) )->run( $target );
	exit( 0 );
} catch ( BuildException $e ) {
	$console->error( $e->getMessage() );
	exit( 1 );
}
