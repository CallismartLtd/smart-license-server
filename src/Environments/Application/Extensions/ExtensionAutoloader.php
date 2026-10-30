<?php
/**
 * Extension autoloader class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Extensions
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Extensions;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Loads the classes of enabled extensions (PSR-4).
 *
 * The app's own classes come from Composer; SmartLicenseServer\Autoloader's
 * spl registration is switched off, so its add_namespace() cannot serve
 * extensions. This class registers one autoloader of its own, the first time
 * an extension is added, and maps each extension's namespace to its source
 * folder.
 */
final class ExtensionAutoloader {

	/**
	 * Source folders by namespace prefix (with trailing backslash); folders end with a slash.
	 *
	 * @var array<string, string>
	 */
	private array $prefixes = [];

	/**
	 * Whether the autoloader is registered with PHP.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs Filesystem API.
	 */
	public function __construct( private FileSystem $fs ) {}

	/**
	 * Load the classes of an extension from now on.
	 *
	 * @param Extension $extension
	 * @return void
	 */
	public function add( Extension $extension ) : void {
		$this->prefixes[ $extension->namespace ] = $extension->source_dir() . '/';

		if ( ! $this->registered ) {
			spl_autoload_register( [ $this, 'load' ] );
			$this->registered = true;
		}
	}

	/**
	 * Load a class of an added extension; does nothing for any other class.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public function load( string $class ) : void {
		foreach ( $this->prefixes as $prefix => $dir ) {
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				continue;
			}

			$file = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

			if ( $this->fs->is_file( $file ) ) {
				require_once $file;

				return;
			}
		}
	}
}