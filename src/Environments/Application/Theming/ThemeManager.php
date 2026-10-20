<?php
/**
 * Theme manager class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Finds the themes of this installation and the one that renders pages.
 *
 * Philosophy:
 *
 *  - There is always a theme. The default theme ships with the app
 *    (system/themes/default: read-only, replaced with every app update)
 *    and renders whenever no other theme is active, or the active one
 *    cannot be used.
 *  - The installation owns its themes. Installed themes live in
 *    storage/themes/<slug>/, which app updates never touch.
 *  - Rendering needs no database. The active theme is recorded in a file
 *    (storage/theme.json: {"active": "<slug>"}), so even the 404 and error
 *    pages render while the database is down.
 *  - A theme is a chain: the theme, its parent if it has one, then the
 *    default theme. A template or asset is taken from the first theme in
 *    the chain that has it.
 *
 * Installing, updating and removing themes is ThemeInstaller's job; this
 * class finds them and records which one is active.
 */
final class ThemeManager {

	/**
	 * Slug of the built-in default theme; reserved.
	 */
	public const DEFAULT_SLUG = 'default';

	/**
	 * Loaded themes, by slug.
	 *
	 * @var array<string, Theme>
	 */
	private array $loaded = [];

	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs            Filesystem API.
	 * @param string     $default_dir   Absolute path of the default theme folder.
	 * @param string     $installed_dir Absolute path of the installed themes folder.
	 * @param string     $active_file   Absolute path of the file recording the active theme.
	 * @param string     $app_version   The running app version.
	 */
	public function __construct(
		private FileSystem $fs,
		private string $default_dir,
		private string $installed_dir,
		private string $active_file,
		private string $app_version
	) {
		$this->default_dir   = rtrim( $default_dir, '/\\' );
		$this->installed_dir = rtrim( $installed_dir, '/\\' );
	}

	/**
	 * The themes of this installation.
	 *
	 * @param FileSystem $fs
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self(
			$fs,
			rtrim( \SMLISER_RUNTIME_DIR, '/\\' ) . '/themes/' . self::DEFAULT_SLUG,
			rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/themes',
			rtrim( \SMLISER_STORAGE_DIR, '/\\' ) . '/theme.json',
			\SMLISER_VER
		);
	}

	/*
	|-----------
	| RESOLVING
	|-----------
	*/

	/**
	 * The built-in default theme.
	 *
	 * @return Theme
	 * @throws ThemeException When the default theme is missing or broken (a damaged installation).
	 */
	public function default_theme() : Theme {
		return $this->loaded[ self::DEFAULT_SLUG ] ??= Theme::from_directory( $this->fs, $this->default_dir );
	}

	/**
	 * A theme by slug: the default theme, or an installed one.
	 *
	 * @param string $slug
	 * @return Theme
	 * @throws ThemeException When the theme is not installed or is invalid.
	 */
	public function find( string $slug ) : Theme {
		if ( self::DEFAULT_SLUG === $slug ) {
			return $this->default_theme();
		}

		if ( isset( $this->loaded[ $slug ] ) ) {
			return $this->loaded[ $slug ];
		}

		if ( 1 !== preg_match( Theme::SLUG_PATTERN, $slug ) || ! $this->fs->is_dir( $this->installed_dir . '/' . $slug ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" is not installed.', $slug ) );
		}

		return $this->loaded[ $slug ] = Theme::from_directory( $this->fs, $this->installed_dir . '/' . $slug );
	}

	/**
	 * The slug recorded as active; the default theme's when none is.
	 *
	 * @return string
	 */
	public function active_slug() : string {
		if ( ! $this->fs->is_file( $this->active_file ) ) {
			return self::DEFAULT_SLUG;
		}

		$json   = $this->fs->get_contents( $this->active_file );
		$record = false === $json ? null : json_decode( $json, true );
		$slug   = is_array( $record ) ? ( $record['active'] ?? null ) : null;

		return is_string( $slug ) && '' !== $slug ? $slug : self::DEFAULT_SLUG;
	}

	/**
	 * The theme that renders pages.
	 *
	 * The recorded theme, when it is installed, valid, supports this app
	 * version and has a valid chain; otherwise the default theme, and the
	 * reason is logged.
	 *
	 * @return Theme
	 * @throws ThemeException When the default theme itself is broken.
	 */
	public function active() : Theme {
		$slug = $this->active_slug();

		if ( self::DEFAULT_SLUG === $slug ) {
			return $this->default_theme();
		}

		try {
			$theme = $this->find( $slug );

			if ( ! $theme->supports( $this->app_version ) ) {
				throw new ThemeException( sprintf( 'The theme "%s" requires app version %s or later; this is %s.', $slug, $theme->requires, $this->app_version ) );
			}

			$this->chain( $theme );

			return $theme;
		} catch ( ThemeException $e ) {
			\smliser_log_error( sprintf( '[Theme] Using the default theme: %s', $e->getMessage() ) );

			return $this->default_theme();
		}
	}

	/**
	 * The chain a theme renders with: the theme, its parent, the default theme.
	 *
	 * @param Theme $theme
	 * @return Theme[] Most specific first; the default theme last.
	 * @throws ThemeException When the parent is missing, invalid or has a parent itself.
	 */
	public function chain( Theme $theme ) : array {
		$chain = [ $theme ];

		if ( null !== $theme->parent && self::DEFAULT_SLUG !== $theme->parent ) {
			$parent = $this->find( $theme->parent );

			if ( null !== $parent->parent ) {
				throw new ThemeException( sprintf( 'The parent "%s" of the theme "%s" has a parent itself; only one level is supported.', $parent->slug, $theme->slug ) );
			}

			$chain[] = $parent;
		}

		if ( self::DEFAULT_SLUG !== $theme->slug ) {
			$chain[] = $this->default_theme();
		}

		return $chain;
	}

	/*
	|------------
	| INSTALLED
	|------------
	*/

	/**
	 * Every installed theme folder, valid or not.
	 *
	 * Folders whose name starts with a dot (ThemeInstaller's working folder)
	 * are not themes and are skipped.
	 *
	 * @return array<string, Theme|ThemeException> Folder name => the theme, or why it cannot be loaded; sorted by name.
	 */
	public function installed() : array {
		$themes  = [];
		$entries = $this->fs->is_dir( $this->installed_dir ) ? $this->fs->list_dir( $this->installed_dir ) : [];

		foreach ( is_array( $entries ) ? $entries : [] as $entry ) {
			$name = basename( $entry['path'] );

			if ( ! $entry['is_dir'] || str_starts_with( $name, '.' ) ) {
				continue;
			}

			try {
				$themes[ $name ] = $this->find( $name );
			} catch ( ThemeException $e ) {
				$themes[ $name ] = $e;
			}
		}

		ksort( $themes );

		return $themes;
	}

	/**
	 * Whether a theme folder is installed (valid or not).
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function is_installed( string $slug ) : bool {
		return self::DEFAULT_SLUG !== $slug
			&& 1 === preg_match( Theme::SLUG_PATTERN, $slug )
			&& $this->fs->is_dir( $this->installed_dir . '/' . $slug );
	}

	/**
	 * Installed themes whose parent is a theme.
	 *
	 * @param string $slug Parent slug.
	 * @return string[] Child slugs.
	 */
	public function children( string $slug ) : array {
		$children = [];

		foreach ( $this->installed() as $name => $theme ) {
			if ( $theme instanceof Theme && $slug === $theme->parent ) {
				$children[] = $name;
			}
		}

		return $children;
	}

	/**
	 * Forget a loaded theme, after its folder was replaced or removed.
	 *
	 * @param string $slug
	 * @return void
	 */
	public function forget( string $slug ) : void {
		unset( $this->loaded[ $slug ] );
	}

	/*
	|------------
	| ACTIVATING
	|------------
	*/

	/**
	 * Make a theme the one that renders pages.
	 *
	 * The theme must load, support this app version and have a valid chain;
	 * the default theme is activated by forgetting the record.
	 *
	 * @param string $slug
	 * @return Theme The active theme.
	 * @throws ThemeException When the theme cannot be used, or the record cannot be written.
	 */
	public function activate( string $slug ) : Theme {
		if ( self::DEFAULT_SLUG === $slug ) {
			if ( $this->fs->is_file( $this->active_file ) && ! $this->fs->delete( $this->active_file ) ) {
				throw new ThemeException( sprintf( 'Could not write %s; check the permissions of the storage folder.', $this->active_file ) );
			}

			return $this->default_theme();
		}

		$this->forget( $slug );

		$theme = $this->find( $slug );

		if ( ! $theme->supports( $this->app_version ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" requires app version %s or later; this is %s.', $slug, $theme->requires, $this->app_version ) );
		}

		$this->chain( $theme );

		$record = (string) json_encode( [ 'active' => $slug, 'activated_at' => gmdate( DATE_ATOM ) ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! $this->fs->put_contents( $this->active_file, $record . "\n" ) ) {
			throw new ThemeException( sprintf( 'Could not write %s; check the permissions of the storage folder.', $this->active_file ) );
		}

		return $theme;
	}

	/*
	|-------
	| PATHS
	|-------
	*/

	/**
	 * Absolute path of the installed themes folder.
	 *
	 * @return string
	 */
	public function installed_dir() : string {
		return $this->installed_dir;
	}

	/**
	 * Absolute path of the file recording the active theme.
	 *
	 * @return string
	 */
	public function active_file() : string {
		return $this->active_file;
	}
}