<?php
/**
 * Extension class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Extensions
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Extensions;

use SmartLicenseServer\Environments\Application\Modules\Module;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * An installed extension: one folder with a manifest and its PHP source.
 *
 * Folder layout:
 *   extension.json   Manifest (see below).
 *   src/             PHP source, PSR-4 mapped to the manifest's "namespace".
 *                    The folder can be renamed with "source".
 *   assets/          Optional. Public assets (CSS, JS, images), published in
 *                    public/extensions/<slug>. No PHP in it.
 *
 * extension.json:
 *   {
 *     "slug":        "acme-invoices",           Required; equals the folder name.
 *     "name":        "Acme Invoices",           Required.
 *     "version":     "1.0.0",                   Required.
 *     "entry":       "Acme\\Invoices\\Plugin",  Required; the class implementing
 *                                               ExtensionInterface. It must be
 *                                               inside "namespace", and its file
 *                                               must exist under "source".
 *     "namespace":   "Acme\\Invoices\\",        Required; the PSR-4 prefix of the
 *                                               source. Ends with a backslash and
 *                                               cannot be inside SmartLicenseServer\.
 *     "source":      "src",                     Optional; folder of the source,
 *                                               relative to the extension folder.
 *                                               Defaults to "src".
 *     "requires":    "0.5.0",                   Optional; lowest app version supported.
 *     "depends":     [ "acme-core" ],           Optional; slugs of extensions that must
 *                                               be enabled, and load first.
 *     "description": "...",                     Optional.
 *     "author":      "..."                      Optional.
 *   }
 *
 * Backslashes are doubled in JSON ("Acme\\Invoices\\").
 */
final class Extension implements Module {

	/**
	 * Manifest file name.
	 */
	public const MANIFEST = 'extension.json';

	/**
	 * An extension slug: lowercase letters, digits and hyphens.
	 */
	public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

	/**
	 * A PSR-4 namespace prefix: identifiers joined and ended by backslashes.
	 */
	public const NAMESPACE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\\\\$/';

	/**
	 * A fully qualified class name, without a leading backslash.
	 */
	public const CLASS_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/';

	/**
	 * A source folder: relative, made of letters, digits, underscores and hyphens.
	 */
	public const SOURCE_PATTERN = '/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/';

	/**
	 * The namespace of the app itself; extensions cannot use it.
	 */
	public const RESERVED_NAMESPACE = 'SmartLicenseServer\\';

	/**
	 * Source folder used when the manifest has no "source".
	 */
	public const DEFAULT_SOURCE = 'src';

	/**
	 * Constructor.
	 *
	 * @param string       $slug        Extension slug.
	 * @param string       $name        Display name.
	 * @param string       $version     Extension version.
	 * @param string       $entry       Fully qualified entry class.
	 * @param string       $namespace   PSR-4 namespace prefix, with a trailing backslash.
	 * @param string       $source      Source folder, relative to the extension folder.
	 * @param string|null  $requires    Lowest app version supported; null for any.
	 * @param string[]     $depends     Slugs of the extensions this one needs.
	 * @param string       $description Description.
	 * @param string       $author      Author.
	 * @param string       $path        Absolute path of the extension folder, without a trailing slash.
	 */
	private function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $version,
		public readonly string $entry,
		public readonly string $namespace,
		public readonly string $source,
		public readonly ?string $requires,
		public readonly array $depends,
		public readonly string $description,
		public readonly string $author,
		public readonly string $path
	) {}

	/**
	 * Load an extension from its folder.
	 *
	 * Reads and checks the manifest and that the entry class file exists.
	 * Never loads any of the extension's PHP.
	 *
	 * @param FileSystem $fs
	 * @param string     $path Absolute path of the extension folder.
	 * @return self
	 * @throws ExtensionException When the folder is not a valid extension.
	 */
	public static function from_directory( FileSystem $fs, string $path ) : self {
		$path   = rtrim( $path, '/\\' );
		$folder = basename( $path );
		$file   = $path . '/' . self::MANIFEST;

		if ( ! $fs->is_file( $file ) ) {
			throw new ExtensionException( sprintf( 'The extension "%s" has no %s.', $folder, self::MANIFEST ) );
		}

		$json     = $fs->get_contents( $file );
		$manifest = false === $json ? null : json_decode( $json, true );

		if ( ! is_array( $manifest ) ) {
			throw new ExtensionException( sprintf( 'The %s of the extension "%s" is not valid JSON.', self::MANIFEST, $folder ) );
		}

		foreach ( [ 'slug', 'name', 'version', 'entry', 'namespace' ] as $key ) {
			if ( ! isset( $manifest[ $key ] ) || ! is_string( $manifest[ $key ] ) || '' === trim( $manifest[ $key ] ) ) {
				throw new ExtensionException( sprintf( 'The %s of the extension "%s" needs a "%s".', self::MANIFEST, $folder, $key ) );
			}
		}

		foreach ( [ 'source', 'requires', 'description', 'author' ] as $key ) {
			if ( isset( $manifest[ $key ] ) && ! is_string( $manifest[ $key ] ) ) {
				throw new ExtensionException( sprintf( 'The "%s" of the extension "%s" must be a string.', $key, $folder ) );
			}
		}

		$slug      = $manifest['slug'];
		$namespace = $manifest['namespace'];
		$entry     = $manifest['entry'];
		$source    = isset( $manifest['source'] ) && '' !== $manifest['source'] ? $manifest['source'] : self::DEFAULT_SOURCE;

		if ( 1 !== preg_match( self::SLUG_PATTERN, $slug ) ) {
			throw new ExtensionException( sprintf( 'The extension slug "%s" is invalid: use lowercase letters, digits and hyphens.', $slug ) );
		}

		if ( $slug !== $folder ) {
			throw new ExtensionException( sprintf( 'The extension slug "%s" does not match its folder "%s".', $slug, $folder ) );
		}

		$depends = [];

		if ( isset( $manifest['depends'] ) ) {
			if ( ! is_array( $manifest['depends'] ) ) {
				throw new ExtensionException( sprintf( 'The "depends" of the extension "%s" must be a list of extension slugs.', $slug ) );
			}

			foreach ( $manifest['depends'] as $dependency ) {
				if ( ! is_string( $dependency ) || 1 !== preg_match( self::SLUG_PATTERN, $dependency ) || $dependency === $slug ) {
					throw new ExtensionException( sprintf( 'The "depends" of the extension "%s" has an invalid slug.', $slug ) );
				}

				$depends[] = $dependency;
			}

			$depends = array_values( array_unique( $depends ) );
		}

		if ( 1 !== preg_match( self::NAMESPACE_PATTERN, $namespace ) ) {
			throw new ExtensionException( sprintf( 'The namespace "%s" of the extension "%s" is invalid: it must end with a backslash.', $namespace, $slug ) );
		}

		if ( 0 === stripos( $namespace, self::RESERVED_NAMESPACE ) ) {
			throw new ExtensionException( sprintf( 'The namespace "%s" of the extension "%s" is reserved by the app.', $namespace, $slug ) );
		}

		if ( 1 !== preg_match( self::CLASS_PATTERN, $entry ) || 0 !== strncmp( $entry, $namespace, strlen( $namespace ) ) ) {
			throw new ExtensionException( sprintf( 'The entry class "%s" of the extension "%s" must be a class inside the namespace "%s".', $entry, $slug, $namespace ) );
		}

		if ( 1 !== preg_match( self::SOURCE_PATTERN, $source ) ) {
			throw new ExtensionException( sprintf( 'The source folder "%s" of the extension "%s" is invalid: use a relative folder.', $source, $slug ) );
		}

		if ( 'assets' === explode( '/', $source )[0] ) {
			throw new ExtensionException( sprintf( 'The source folder of the extension "%s" cannot be assets/: assets are published in public/ and must not hold PHP.', $slug ) );
		}

		if ( ! $fs->is_dir( $path . '/' . $source ) ) {
			throw new ExtensionException( sprintf( 'The extension "%s" has no "%s" source folder.', $slug, $source ) );
		}

		$entry_file = $path . '/' . $source . '/' . str_replace( '\\', '/', substr( $entry, strlen( $namespace ) ) ) . '.php';

		if ( ! $fs->is_file( $entry_file ) ) {
			throw new ExtensionException( sprintf( 'The entry class "%s" of the extension "%s" was not found at %s.', $entry, $slug, $entry_file ) );
		}

		return new self(
			slug: $slug,
			name: trim( $manifest['name'] ),
			version: trim( $manifest['version'] ),
			entry: $entry,
			namespace: $namespace,
			source: $source,
			requires: isset( $manifest['requires'] ) && '' !== trim( $manifest['requires'] ) ? trim( $manifest['requires'] ) : null,
			depends: $depends,
			description: $manifest['description'] ?? '',
			author: $manifest['author'] ?? '',
			path: $path
		);
	}

	/**
	 * Whether this extension supports an app version.
	 *
	 * @param string $app_version
	 * @return bool
	 */
	public function supports( string $app_version ) : bool {
		return null === $this->requires || version_compare( $app_version, $this->requires, '>=' );
	}

	/**
	 * Absolute path of the source folder, without a trailing slash.
	 *
	 * @return string
	 */
	public function source_dir() : string {
		return $this->path . '/' . $this->source;
	}

	/**
	 * Absolute path of the assets folder (it may not exist).
	 *
	 * @return string
	 */
	public function assets_dir() : string {
		return $this->path . '/assets';
	}

	/**
	 * The name of this extension's assets inside public/ (PublicAssets).
	 *
	 * @return string
	 */
	public function public_name() : string {
		return 'extensions/' . $this->slug;
	}
}
