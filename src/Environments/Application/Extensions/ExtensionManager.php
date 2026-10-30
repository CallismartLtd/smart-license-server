<?php
/**
 * Extension manager class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Extensions
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Extensions;

use SmartLicenseServer\Environments\Application\Modules\ModuleManager;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Finds the extensions of this installation and the ones that are enabled.
 *
 * Philosophy:
 *
 *  - The installation owns its extensions. Installed extensions live in
 *    storage/extensions/<slug>/, which app updates never touch.
 *  - Booting needs no directory scan and no database. The enabled
 *    extensions are recorded in a file (storage/extensions.json:
 *    {"enabled": ["<slug>", ...]}); boot reads that list and loads only
 *    those. Scanning the folder is for the admin's list of installed
 *    extensions (installed()), never for a request.
 *  - An extension that cannot be used never stops the app. resolve() leaves
 *    it out, with the reason, and the rest still load.
 *  - Extensions load in dependency order, then in the order they were
 *    enabled.
 *
 * Installing, updating and removing extensions is the installer's job; this
 * class finds them and records which ones are enabled.
 */
final class ExtensionManager implements ModuleManager {

	/**
	 * Loaded extensions, by slug.
	 *
	 * @var array<string, Extension>
	 */
	private array $loaded = [];

	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs            Filesystem API.
	 * @param string     $installed_dir Absolute path of the installed extensions folder.
	 * @param string     $enabled_file  Absolute path of the file recording the enabled extensions.
	 * @param string     $app_version   The running app version.
	 */
	public function __construct(
		private FileSystem $fs,
		private string $installed_dir,
		private string $enabled_file,
		private string $app_version
	) {
		$this->installed_dir = rtrim( $installed_dir, '/\\' );
	}

	/**
	 * The extensions of this installation.
	 *
	 * @param FileSystem $fs
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self(
			$fs,
			rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/extensions',
			rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/extensions.json',
			\SMLISER_VER
		);
	}

	/*
	|-----------
	| RESOLVING
	|-----------
	*/

	/**
	 * An installed extension by slug.
	 *
	 * @param string $slug
	 * @return Extension
	 * @throws ExtensionException When the extension is not installed or is invalid.
	 */
	public function find( string $slug ) : Extension {
		if ( isset( $this->loaded[ $slug ] ) ) {
			return $this->loaded[ $slug ];
		}

		if ( 1 !== preg_match( Extension::SLUG_PATTERN, $slug ) || ! $this->fs->is_dir( $this->installed_dir . '/' . $slug ) ) {
			throw new ExtensionException( sprintf( 'The extension "%s" is not installed.', $slug ) );
		}

		return $this->loaded[ $slug ] = Extension::from_directory( $this->fs, $this->installed_dir . '/' . $slug );
	}

	/**
	 * The enabled extensions that can load, in the order they load, and the ones that cannot.
	 *
	 * An enabled extension is left out (skipped) when it is not installed or
	 * invalid, needs a newer app version, shares a namespace with an
	 * extension that comes first in the enabled list, depends on an extension
	 * that is not enabled or was left out, or is part of a dependency cycle.
	 *
	 * @return array{load: Extension[], skipped: array<string, string>} The extensions in load order
	 *                                                                  (dependencies first), and why each
	 *                                                                  left-out slug was left out.
	 */
	public function resolve() : array {
		$skipped    = [];
		$candidates = [];
		$namespaces = [];

		foreach ( $this->enabled_slugs() as $slug ) {
			try {
				$extension = $this->find( $slug );

				if ( ! $extension->supports( $this->app_version ) ) {
					throw new ExtensionException( sprintf( 'The extension "%s" requires app version %s or later; this is %s.', $slug, (string) $extension->requires, $this->app_version ) );
				}

				$key = strtolower( $extension->namespace );

				if ( isset( $namespaces[ $key ] ) ) {
					throw new ExtensionException( sprintf( 'The namespace "%s" of the extension "%s" is already used by "%s".', $extension->namespace, $slug, $namespaces[ $key ] ) );
				}

				$namespaces[ $key ]  = $slug;
				$candidates[ $slug ] = $extension;
			} catch ( ExtensionException $e ) {
				$skipped[ $slug ] = $e->getMessage();
			}
		}

		// Leave out whatever needs an extension that is not there, until nothing more changes.
		do {
			$changed = false;

			foreach ( $candidates as $slug => $extension ) {
				foreach ( $extension->depends as $dependency ) {
					if ( ! isset( $candidates[ $dependency ] ) ) {
						$skipped[ $slug ] = sprintf( 'The extension "%s" requires "%s", which is not enabled or cannot be loaded.', $slug, $dependency );
						unset( $candidates[ $slug ] );
						$changed = true;
						continue 2;
					}
				}
			}
		} while ( $changed );

		// Dependencies first; otherwise the enabled order.
		$load      = [];
		$placed    = [];
		$remaining = $candidates;

		while ( [] !== $remaining ) {
			$progress = false;

			foreach ( $remaining as $slug => $extension ) {
				if ( [] !== array_diff( $extension->depends, array_keys( $placed ) ) ) {
					continue;
				}

				$load[]          = $extension;
				$placed[ $slug ] = true;
				unset( $remaining[ $slug ] );
				$progress = true;
				break;
			}

			if ( ! $progress ) {
				foreach ( array_keys( $remaining ) as $slug ) {
					$skipped[ $slug ] = sprintf( 'The extension "%s" is part of, or depends on, a dependency cycle.', $slug );
				}

				break;
			}
		}

		return [
			'load'    => $load,
			'skipped' => $skipped,
		];
	}

	/*
	|------------
	| INSTALLED
	|------------
	*/

	/**
	 * Every installed extension folder, valid or not.
	 *
	 * Folders whose name starts with a dot (an installer's working folder)
	 * are not extensions and are skipped. This scans the folder: use it for
	 * the admin's list, never while booting.
	 *
	 * @return array<string, Extension|ExtensionException> Folder name => the extension, or why it cannot be loaded; sorted by name.
	 */
	public function installed() : array {
		$extensions = [];
		$entries    = $this->fs->is_dir( $this->installed_dir ) ? $this->fs->list_dir( $this->installed_dir ) : [];

		foreach ( is_array( $entries ) ? $entries : [] as $entry ) {
			$name = basename( $entry['path'] );

			if ( ! $entry['is_dir'] || str_starts_with( $name, '.' ) ) {
				continue;
			}

			try {
				$extensions[ $name ] = $this->find( $name );
			} catch ( ExtensionException $e ) {
				$extensions[ $name ] = $e;
			}
		}

		ksort( $extensions );

		return $extensions;
	}

	/**
	 * Whether an extension folder is installed (valid or not).
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function is_installed( string $slug ) : bool {
		return 1 === preg_match( Extension::SLUG_PATTERN, $slug ) && $this->fs->is_dir( $this->installed_dir . '/' . $slug );
	}

	/**
	 * Forget a loaded extension, after its folder was replaced or removed.
	 *
	 * @param string $slug
	 * @return void
	 */
	public function forget( string $slug ) : void {
		unset( $this->loaded[ $slug ] );
	}

	/*
	|------------
	| ENABLING
	|------------
	*/

	/**
	 * The slugs recorded as enabled, in the order they were enabled.
	 *
	 * @return string[]
	 */
	public function enabled_slugs() : array {
		if ( ! $this->fs->is_file( $this->enabled_file ) ) {
			return [];
		}

		$json   = $this->fs->get_contents( $this->enabled_file );
		$record = false === $json ? null : json_decode( $json, true );
		$list   = is_array( $record ) && isset( $record['enabled'] ) && is_array( $record['enabled'] ) ? $record['enabled'] : [];
		$slugs  = [];

		foreach ( $list as $slug ) {
			if ( is_string( $slug ) && 1 === preg_match( Extension::SLUG_PATTERN, $slug ) ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Whether an extension is recorded as enabled.
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function is_enabled( string $slug ) : bool {
		return in_array( $slug, $this->enabled_slugs(), true );
	}

	/**
	 * The enabled extensions that need an extension.
	 *
	 * An enabled extension whose manifest cannot be read is not counted.
	 *
	 * @param string $slug The extension that is needed.
	 * @return string[] Slugs of the enabled extensions that list it in "depends".
	 */
	public function dependents( string $slug ) : array {
		$dependents = [];

		foreach ( $this->enabled_slugs() as $enabled ) {
			if ( $enabled === $slug ) {
				continue;
			}

			try {
				if ( in_array( $slug, $this->find( $enabled )->depends, true ) ) {
					$dependents[] = $enabled;
				}
			} catch ( ExtensionException ) {
				continue;
			}
		}

		return $dependents;
	}

	/**
	 * Enable an extension: it loads from the next request on.
	 *
	 * The extension must load, support this app version, have every
	 * extension it depends on enabled already, and not share a namespace
	 * with an enabled extension. Enabling an enabled extension changes
	 * nothing.
	 *
	 * @param string $slug
	 * @return Extension The enabled extension.
	 * @throws ExtensionException When the extension cannot be enabled, or the record cannot be written.
	 */
	public function enable( string $slug ) : Extension {
		$this->forget( $slug );

		$extension = $this->find( $slug );

		if ( ! $extension->supports( $this->app_version ) ) {
			throw new ExtensionException( sprintf( 'The extension "%s" requires app version %s or later; this is %s.', $slug, (string) $extension->requires, $this->app_version ) );
		}

		$enabled = $this->enabled_slugs();

		foreach ( $extension->depends as $dependency ) {
			if ( ! in_array( $dependency, $enabled, true ) ) {
				throw new ExtensionException( sprintf( 'Enable the extension "%s" first: "%s" requires it.', $dependency, $slug ) );
			}
		}

		foreach ( $enabled as $other ) {
			if ( $other === $slug ) {
				continue;
			}

			try {
				$other_extension = $this->find( $other );
			} catch ( ExtensionException ) {
				continue;
			}

			if ( 0 === strcasecmp( $other_extension->namespace, $extension->namespace ) ) {
				throw new ExtensionException( sprintf( 'The namespace "%s" of the extension "%s" is already used by the enabled extension "%s".', $extension->namespace, $slug, $other ) );
			}
		}

		if ( ! in_array( $slug, $enabled, true ) ) {
			$enabled[] = $slug;
			$this->write_enabled( $enabled );
		}

		return $extension;
	}

	/**
	 * Disable an extension: it no longer loads from the next request on.
	 *
	 * Disabling an extension that is not enabled changes nothing.
	 *
	 * @param string $slug
	 * @return void
	 * @throws ExtensionException When an enabled extension depends on it, or the record cannot be written.
	 */
	public function disable( string $slug ) : void {
		$enabled = $this->enabled_slugs();

		if ( ! in_array( $slug, $enabled, true ) ) {
			return;
		}

		$dependents = $this->dependents( $slug );

		if ( [] !== $dependents ) {
			throw new ExtensionException( sprintf( 'The extension "%s" is required by %s; disable that first.', $slug, implode( ', ', $dependents ) ) );
		}

		$this->write_enabled( array_values( array_diff( $enabled, [ $slug ] ) ) );
	}

	/**
	 * Write the enabled list.
	 *
	 * @param string[] $slugs
	 * @return void
	 * @throws ExtensionException When the file cannot be written.
	 */
	private function write_enabled( array $slugs ) : void {
		$record = (string) json_encode( [ 'enabled' => array_values( $slugs ) ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! $this->fs->put_contents( $this->enabled_file, $record . "\n" ) ) {
			throw new ExtensionException( sprintf( 'Could not write %s; check the permissions of the storage folder.', $this->enabled_file ) );
		}
	}

	/*
	|-------
	| PATHS
	|-------
	*/

	/**
	 * Absolute path of the installed extensions folder.
	 *
	 * @return string
	 */
	public function installed_dir() : string {
		return $this->installed_dir;
	}

	/**
	 * Absolute path of the file recording the enabled extensions.
	 *
	 * @return string
	 */
	public function enabled_file() : string {
		return $this->enabled_file;
	}
}
