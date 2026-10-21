<?php
/**
 * Router for PHP's built-in web server, for local development only.
 *
 * Run from the application folder:
 *
 *     php -S 127.0.0.1:8000 -t public server/server.php
 *
 * It does what the nginx, Caddy and Apache configurations in this folder do:
 * existing files under public/ are served as they are, every other request
 * goes to public/index.php, hidden files are never served, and no PHP file
 * other than index.php runs.
 *
 * The built-in server only logs a "[status]: METHOD /uri" line for files it
 * serves itself. Every request this router answers is logged here, in the
 * same format and to the same stream, so the log covers all requests.
 *
 * Never use PHP's built-in server in production.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

$public = realpath( dirname( __DIR__ ) . '/public' );

if ( false === $public ) {
	http_response_code( 500 );
	echo 'The public folder was not found next to the server folder.';
	return true;
}

// Cleared just before handing a file back to the server, which logs those itself.
$log_request = true;

register_shutdown_function(
	static function () use ( &$log_request ) : void {
		if ( ! $log_request ) {
			return;
		}

		$address = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

		if ( str_contains( $address, ':' ) ) {
			$address = '[' . $address . ']';
		}

		file_put_contents(
			'php://stderr',
			sprintf(
				'[%s] %s:%s [%d]: %s %s' . PHP_EOL,
				date( 'D M d H:i:s Y' ),
				$address,
				(string) ( $_SERVER['REMOTE_PORT'] ?? '' ),
				(int) http_response_code(),
				(string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ),
				(string) ( $_SERVER['REQUEST_URI'] ?? '/' )
			)
		);
	}
);

$uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
$path = rawurldecode( (string) parse_url( $uri, PHP_URL_PATH ) );

// Hidden files and folders (.htaccess, .env copies, VCS folders), except ACME challenges.
if ( 1 === preg_match( '#/\.(?!well-known/)#', $path ) ) {
	http_response_code( 404 );
	return true;
}

// The front controller in /index.php/<path> form: the rest of the path is PATH_INFO.
$path_info = '';

if ( '/index.php' === $path || str_starts_with( $path, '/index.php/' ) ) {
	$path_info = substr( $path, strlen( '/index.php' ) );
} elseif ( '/' !== $path ) {
	$file = realpath( $public . $path );

	// Files may only come from public/ or the folders its asset links point to
	// (public/assets, public/themes/<slug>, public/extensions/<slug>); never above them.
	$roots = [ $public, realpath( $public . '/assets' ) ];

	foreach ( [ 'themes', 'extensions' ] as $group ) {
		foreach ( glob( $public . '/' . $group . '/*', GLOB_ONLYDIR ) ?: [] as $dir ) {
			$roots[] = realpath( $dir );
		}
	}

	$roots  = array_filter( $roots );
	$inside = false;

	foreach ( $roots as $root ) {
		$inside = $inside || ( false !== $file && str_starts_with( $file, $root . DIRECTORY_SEPARATOR ) );
	}

	// Existing files inside public/ (including through the asset links) are served directly.
	if ( $inside && is_file( $file ) ) {
		if ( 'php' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			http_response_code( 404 );
			return true;
		}

		$log_request = false;
		return false;
	}
}

// Everything else is handled by the application, as if public/index.php was requested.
$_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php' . $path_info;
$_SERVER['DOCUMENT_ROOT']   = $public;

if ( '' !== $path_info ) {
	$_SERVER['PATH_INFO'] = $path_info;
} else {
	unset( $_SERVER['PATH_INFO'] );
}

chdir( $public );

require $public . '/index.php';
