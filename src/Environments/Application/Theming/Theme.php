<?php
/**
 * Theme class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * A site theme: one folder with a manifest, templates and assets.
 *
 * Not to be confused with a hosted WordPress theme in the repository
 * (FileSystem\WPThemeRepository, the "theme" app type): a site theme is how
 * this installation's own public pages look.
 *
 * Folder layout:
 *   theme.json        Manifest (see below).
 *   templates/        layout.php, pages/*.php, partials/*.php.
 *   assets/           Static files, published at public/themes/<slug>/.
 *                     assets/screenshot.png (or .jpg, .webp), when present,
 *                     is the theme's picture on the admin Modules > Themes page.
 *
 * theme.json:
 *   {
 *     "slug":        "acme",          Required; equals the folder name.
 *     "name":        "Acme",          Required.
 *     "version":     "1.0.0",         Required.
 *     "requires":    "0.5.0",         Optional; lowest app version supported.
 *     "parent":      "default",       Optional; templates and assets this
 *                                     theme lacks come from the parent. The
 *                                     parent cannot have a parent itself.
 *     "description": "...",           Optional.
 *     "author":      "..."            Optional.
 *   }
 */
final class Theme {

	/**
	 * Manifest file name.
	 */
	public const MANIFEST = 'theme.json';

	/**
	 * A theme slug: lowercase letters, digits and hyphens.
	 */
	public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

	/**
	 * Constructor.
	 *
	 * @param string      $slug        Theme slug.
	 * @param string      $name        Display name.
	 * @param string      $version     Theme version.
	 * @param string|null $requires    Lowest app version supported; null for any.
	 * @param string|null $parent      Parent theme slug; null for none.
	 * @param string      $description Description.
	 * @param string      $author      Author.
	 * @param string      $path        Absolute path of the theme folder, without a trailing slash.
	 */
	private function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $version,
		public readonly ?string $requires,
		public readonly ?string $parent,
		public readonly string $description,
		public readonly string $author,
		public readonly string $path
	) {}

	/**
	 * Load a theme from its folder.
	 *
	 * @param FileSystem $fs
	 * @param string     $path Absolute path of the theme folder.
	 * @return self
	 * @throws ThemeException When the folder is not a valid theme.
	 */
	public static function from_directory( FileSystem $fs, string $path ) : self {
		$path   = rtrim( $path, '/\\' );
		$folder = basename( $path );
		$file   = $path . '/' . self::MANIFEST;

		if ( ! $fs->is_file( $file ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" has no %s.', $folder, self::MANIFEST ) );
		}

		$json     = $fs->get_contents( $file );
		$manifest = false === $json ? null : json_decode( $json, true );

		if ( ! is_array( $manifest ) ) {
			throw new ThemeException( sprintf( 'The %s of the theme "%s" is not valid JSON.', self::MANIFEST, $folder ) );
		}

		foreach ( [ 'slug', 'name', 'version' ] as $key ) {
			if ( ! isset( $manifest[ $key ] ) || ! is_string( $manifest[ $key ] ) || '' === trim( $manifest[ $key ] ) ) {
				throw new ThemeException( sprintf( 'The %s of the theme "%s" needs a "%s".', self::MANIFEST, $folder, $key ) );
			}
		}

		foreach ( [ 'requires', 'parent', 'description', 'author' ] as $key ) {
			if ( isset( $manifest[ $key ] ) && ! is_string( $manifest[ $key ] ) ) {
				throw new ThemeException( sprintf( 'The "%s" of the theme "%s" must be a string.', $key, $folder ) );
			}
		}

		$slug   = $manifest['slug'];
		$parent = isset( $manifest['parent'] ) && '' !== $manifest['parent'] ? $manifest['parent'] : null;

		if ( 1 !== preg_match( self::SLUG_PATTERN, $slug ) ) {
			throw new ThemeException( sprintf( 'The theme slug "%s" is invalid: use lowercase letters, digits and hyphens.', $slug ) );
		}

		if ( $slug !== $folder ) {
			throw new ThemeException( sprintf( 'The theme slug "%s" does not match its folder "%s".', $slug, $folder ) );
		}

		if ( null !== $parent && ( $parent === $slug || 1 !== preg_match( self::SLUG_PATTERN, $parent ) ) ) {
			throw new ThemeException( sprintf( 'The parent "%s" of the theme "%s" is invalid.', $parent, $slug ) );
		}

		if ( ! $fs->is_dir( $path . '/templates' ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" has no templates folder.', $slug ) );
		}

		return new self(
			slug: $slug,
			name: trim( $manifest['name'] ),
			version: trim( $manifest['version'] ),
			requires: isset( $manifest['requires'] ) && '' !== trim( $manifest['requires'] ) ? trim( $manifest['requires'] ) : null,
			parent: $parent,
			description: $manifest['description'] ?? '',
			author: $manifest['author'] ?? '',
			path: $path
		);
	}

	/**
	 * Whether this theme supports an app version.
	 *
	 * @param string $app_version
	 * @return bool
	 */
	public function supports( string $app_version ) : bool {
		return null === $this->requires || version_compare( $app_version, $this->requires, '>=' );
	}

	/**
	 * Absolute path of the templates folder.
	 *
	 * @return string
	 */
	public function templates_dir() : string {
		return $this->path . '/templates';
	}

	/**
	 * Absolute path of the assets folder (may not exist).
	 *
	 * @return string
	 */
	public function assets_dir() : string {
		return $this->path . '/assets';
	}

	/**
	 * The name of this theme's assets inside public/ (PublicAssets).
	 *
	 * @return string
	 */
	public function public_name() : string {
		return 'themes/' . $this->slug;
	}
}
