<?php
/**
 * Autoloader for Smart License Server.
 *
 * The core app's classes load through Composer. This class extends that
 * same Composer class loader with supplementary namespaces (the extensions
 * of the application) and loads the app's function files.
 * 
 * @package SmartLicenseServer
 * @since   0.2.0
 */

namespace SmartLicenseServer;

use Composer\Autoload\ClassLoader;
use const DIRECTORY_SEPARATOR;

class Autoloader {

	/**
	 * Composer's class loader for this installation's vendor folder.
	 * 
	 * @var ClassLoader|null
	 */
	private static ?ClassLoader $composer = null;

	/**
	 * Function directories to autoload
	 * 
	 * @var array
	 */
	private static array $function_dirs = [
		__DIR__ . DIRECTORY_SEPARATOR . 'Utils/functions/',
	];

	/**
	 * Loaded function files
	 * 
	 * @var array
	 */
	private static array $loaded_function_files = [];
	
	/**
	 * Load Composer's autoloader and the registered function files.
	 */
	public static function boot() : void {
		static::require_vendor();
		static::autoload_functions();
	}

	/**
	 * Add a PSR-4 namespace to Composer's class loader.
	 * 
	 * Classes of the namespace load from the base directory like the core's
	 * own: the file of `Acme\Invoices\Sub\Foo` for the namespace
	 * `Acme\Invoices\` is `<base_dir>/Sub/Foo.php`. Composer looks a
	 * namespace up after the ones registered before it, so the core's
	 * mappings always win. Adding a namespace and directory again changes
	 * nothing.
	 * 
	 * @param string $namespace The namespace prefix, ending with a backslash.
	 * @param string $base_dir  The directory the namespace maps to.
	 * @throws \InvalidArgumentException When the namespace does not end with a backslash.
	 * @throws \LogicException When Composer's loader is not available, or is
	 *                         classmap-authoritative (it would never look up
	 *                         a namespace added at runtime).
	 */
	public static function add_namespace( string $namespace, string $base_dir ) : void {
		if ( ! str_ends_with( $namespace, '\\' ) ) {
			throw new \InvalidArgumentException( sprintf( 'The namespace "%s" must end with a backslash.', $namespace ) );
		}

		$composer = static::$composer;

		if ( null === $composer ) {
			throw new \LogicException( 'Composer\'s class loader is not available, so namespaces cannot be added.' );
		}

		if ( $composer->isClassMapAuthoritative() ) {
			throw new \LogicException( 'Composer\'s class loader is classmap-authoritative and never looks up a namespace added at runtime.' );
		}

		$base_dir = rtrim( $base_dir, '/\\' );

		if ( ! in_array( $base_dir, $composer->getPrefixesPsr4()[ $namespace ] ?? [], true ) ) {
			$composer->addPsr4( $namespace, $base_dir );
		}
	}

	/**
	 * Add a function directory to autoload
	 * 
	 * @param string $dir The directory path
	 */
	public static function add_function_dir( $dir ) : void {
		$dir = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		if ( ! in_array( $dir, static::$function_dirs, true ) ) {
			static::$function_dirs[] = $dir;

			// Immediately load functions from this directory.
			static::load_function_dir( $dir );
		}
	}

	/**
	 * Autoload all function files from registered function directories.
	 */
	private static function autoload_functions() : void {
		foreach ( static::$function_dirs as $dir ) {
			static::load_function_dir( $dir );
		}
	}

	/**
	 * Require composer autoloader and keep its class loader.
	 */
	private static function require_vendor() : void {
		$vendor = __DIR__ . '/../vendor';
		$loader = require_once $vendor . '/autoload.php';

		// require_once returns the loader only the first time the file is included.
		if ( ! $loader instanceof ClassLoader ) {
			$loader = ClassLoader::getRegisteredLoaders()[ realpath( $vendor ) ] ?? null;
		}

		static::$composer = $loader instanceof ClassLoader ? $loader : null;
	}

	/**
	 * Load function files from a directory, ensuring each file is only loaded once.
	 */
	private static function load_function_dir( string $dir ) : void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( glob( $dir . '*.php' ) as $file ) {
			if ( isset( static::$loaded_function_files[ $file ] ) ) {
				continue;
			}

			require_once $file;

			static::$loaded_function_files[ $file ] = true;
		}
	}
}

Autoloader::boot();