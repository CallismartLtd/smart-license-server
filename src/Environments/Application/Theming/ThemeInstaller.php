<?php
/**
 * Theme installer class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Environments\Application\Assets\PublicAssets;
use SmartLicenseServer\Environments\Application\Update\PackageVerifier;
use SmartLicenseServer\Environments\Application\Update\UpdateException;
use SmartLicenseServer\Environments\Application\Update\UpdateProgressTrait;
use SmartLicenseServer\Environments\Application\Update\UpdateServer;
use SmartLicenseServer\FileSystem\FileSystem;
use Throwable;
use ZipArchive;

/**
 * Installs, updates and removes the site themes of this installation.
 *
 * Philosophy:
 *
 *  - A theme package is the whole theme. A zip holding one folder named
 *    after the theme slug (theme.json, templates/, assets/). Installing
 *    puts that folder in storage/themes/<slug>; updating replaces it whole,
 *    so files a new version dropped disappear with the old folder.
 *  - Packages are verified the way app updates are (PackageVerifier):
 *    <base>.zip beside <base>.sha256 and <base>.sha256.sig, the checksums
 *    signed by a release key. Marketplace packages must be signed. An
 *    uploaded package without a valid signature installs only when the
 *    administrator trusts it explicitly, and the record says so.
 *  - The marketplace is the update server (UpdateServer::HOST). A theme
 *    "<slug>" is published there as the hosted app MARKETPLACE_TYPE /
 *    "smliser-theme-<slug>", with the artifacts
 *    "smliser-theme-<slug>-<version>.zip|.sha256|.sha256.sig".
 *  - PHP runs only from templates/. A package with PHP anywhere else, or
 *    with server configuration files (.htaccess, .user.ini, web.config)
 *    in assets/, is refused: assets/ is published in public/, where a copy
 *    (on servers without symlinks) is served by the web server as it is.
 *    Links inside the zip, paths leaving its folder, and packages over
 *    MAX_FILES files or MAX_BYTES bytes are refused too.
 *  - Nothing changes until the new folder is complete. The package is
 *    extracted and checked in storage/themes/.work; the live folder is then
 *    swapped with renames, and put back if the swap fails.
 *  - Each installed theme carries a record of where it came from
 *    (PACKAGE_RECORD, in its folder): the source, the SHA-256, the key that
 *    signed it. Updates from the marketplace are offered only for themes
 *    installed from it.
 *
 * The default theme ships with the app and is never installed, replaced or
 * removed here. Which theme is active is ThemeManager's record.
 */
final class ThemeInstaller {

	use UpdateProgressTrait;

	/**
	 * Hosted app type of site themes on the marketplace.
	 */
	public const MARKETPLACE_TYPE = 'software';

	/**
	 * Prefix of a site theme's hosted app slug on the marketplace.
	 */
	public const MARKETPLACE_PREFIX = 'smliser-theme-';

	/**
	 * Working folder, inside the installed themes folder (same filesystem, so swaps are renames).
	 */
	public const WORK_DIR = '.work';

	/**
	 * The record of where an installed theme came from, in its folder.
	 */
	public const PACKAGE_RECORD = '.smliser-package.json';

	/**
	 * Sources of an installed theme.
	 */
	public const SOURCE_MARKETPLACE = 'marketplace';
	public const SOURCE_UPLOAD      = 'upload';

	/**
	 * Themes per page of the marketplace listing.
	 */
	public const MARKETPLACE_PER_PAGE = 24;

	/**
	 * Largest package accepted: number of entries and uncompressed bytes.
	 */
	public const MAX_FILES = 5000;
	public const MAX_BYTES = 104857600;

	/**
	 * File name parts that make a file PHP to some server configuration.
	 */
	private const PHP_EXTENSIONS = [ 'php', 'phtml', 'phar', 'pht', 'phps', 'php3', 'php4', 'php5', 'php7', 'php8' ];

	/**
	 * Server configuration files refused in assets/.
	 */
	private const SERVER_FILES = [ '.htaccess', '.htpasswd', '.user.ini', 'web.config', PublicAssets::COPY_MARKER ];

	/**
	 * Handle of the held theme lock.
	 *
	 * @var resource|null
	 */
	private $lock = null;

	/**
	 * Constructor.
	 *
	 * @param FileSystem      $fs       Filesystem API.
	 * @param ThemeManager    $themes   The installed themes and the active one.
	 * @param PublicAssets    $assets   Publishes each theme's assets in public/.
	 * @param PackageVerifier $verifier Checks a package against its checksums and signature.
	 * @param UpdateServer    $server   The marketplace.
	 */
	public function __construct(
		private FileSystem $fs,
		private ThemeManager $themes,
		private PublicAssets $assets,
		private PackageVerifier $verifier,
		private UpdateServer $server
	) {}

	/*
	|------------
	| INSTALLING
	|------------
	*/

	/**
	 * Install (or, with $replace, reinstall or update) a theme from a local package.
	 *
	 * @param string        $zip            Path to the package zip.
	 * @param string|null   $checksums      Contents of its .sha256 file; null when there is none.
	 * @param string|null   $signature      Contents of its .sha256.sig file; null when there is none.
	 * @param bool          $trust_unsigned Install without valid checksums or signature.
	 * @param bool          $replace        Replace an installed theme of the same slug (same or newer version).
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{theme: Theme, previous: ?string, record: array<string, mixed>, warnings: string[]}
	 *         The installed theme, the version it replaced (null for a new install), its record, and warnings.
	 * @throws ThemeException When the package is refused or cannot be installed; nothing was changed.
	 */
	public function install_zip( string $zip, ?string $checksums, ?string $signature, bool $trust_unsigned = false, bool $replace = false, ?callable $progress = null ) : array {
		try {
			$verified = $this->verifier->verify( $zip, $checksums, $signature, $trust_unsigned, $progress );
		} catch ( UpdateException $e ) {
			throw new ThemeException( $e->getMessage(), 0, $e );
		}

		return $this->install_verified(
			$zip,
			[
				'source'    => self::SOURCE_UPLOAD,
				'origin'    => basename( $zip ),
				'sha256'    => $verified['sha256'],
				'signed_by' => $verified['signed_by'],
				'trusted'   => null === $verified['signed_by'],
			],
			$replace,
			null,
			$verified['warnings'],
			$progress
		);
	}

	/**
	 * Install a theme from uploaded files (the admin Themes page).
	 *
	 * The package is moved into the working folder under its uploaded name,
	 * since its checksums file lists it by that name.
	 *
	 * @param UploadedFile      $package        The package zip.
	 * @param UploadedFile|null $checksums      Its .sha256 file, if uploaded.
	 * @param UploadedFile|null $signature      Its .sha256.sig file, if uploaded.
	 * @param bool              $trust_unsigned Install without valid checksums or signature.
	 * @param bool              $replace        Replace an installed theme of the same slug.
	 * @return array{theme: Theme, previous: ?string, record: array<string, mixed>, warnings: string[]}
	 * @throws ThemeException When the upload is unusable or the package is refused.
	 */
	public function install_upload( UploadedFile $package, ?UploadedFile $checksums, ?UploadedFile $signature, bool $trust_unsigned = false, bool $replace = false ) : array {
		if ( ! $package->is_uploaded_file() ) {
			throw new ThemeException( $package->get_error_message() );
		}

		$name = (string) $package->get_client_name();

		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*\.zip$/i', $name ) ) {
			throw new ThemeException( 'Upload the theme package as a .zip file whose name uses letters, digits, dots, hyphens and underscores.' );
		}

		$dir = $this->work_path( 'upload-' . bin2hex( random_bytes( 6 ) ) );

		try {
			$this->make_dir( $dir );

			$zip = $dir . '/' . $name;

			if ( ! $this->fs->move( (string) $package->get_tmp_path(), $zip, true ) ) {
				throw new ThemeException( 'The uploaded package could not be stored; check the permissions of the storage folder.' );
			}

			return $this->install_zip( $zip, $this->uploaded_text( $checksums ), $this->uploaded_text( $signature ), $trust_unsigned, $replace );
		} finally {
			$this->remove( $dir );
		}
	}

	/**
	 * Install a theme from the marketplace, or update it when it is installed.
	 *
	 * Marketplace packages must be signed.
	 *
	 * @param string        $slug     Theme slug.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{theme: Theme, previous: ?string, record: array<string, mixed>, warnings: string[]}|null
	 *         Null when the installed version is already the latest.
	 * @throws ThemeException When the marketplace cannot provide it or the package is refused.
	 */
	public function install_from_marketplace( string $slug, ?callable $progress = null ) : ?array {
		$this->assert_slug( $slug );

		$installed = $this->themes->is_installed( $slug ) ? $this->installed_version( $slug ) : null;

		try {
			$latest = $this->server->latest_of( self::MARKETPLACE_TYPE, self::marketplace_slug( $slug ), $progress );
		} catch ( UpdateException $e ) {
			throw new ThemeException( $e->getMessage(), 0, $e );
		}

		if ( null !== $installed && version_compare( $latest['version'], $installed, '<=' ) ) {
			$this->report( $progress, 'check', self::STATUS_OK, sprintf( 'The theme "%s" is up to date (%s).', $slug, $installed ) );
			return null;
		}

		$dir = $this->work_path( 'download-' . bin2hex( random_bytes( 6 ) ) );

		try {
			$this->make_dir( $dir );

			try {
				$download = $this->server->download_artifact( self::MARKETPLACE_TYPE, self::marketplace_slug( $slug ), self::artifact_base( $slug, $latest['version'] ), $dir, false, $progress );
			} catch ( UpdateException $e ) {
				throw new ThemeException( $e->getMessage(), 0, $e );
			}

			return $this->install_verified(
				$download['zip'],
				[
					'source'    => self::SOURCE_MARKETPLACE,
					'origin'    => $download['source'],
					'sha256'    => $download['sha256'],
					'signed_by' => $download['signed_by'],
					'trusted'   => false,
				],
				null !== $installed,
				[ 'slug' => $slug, 'version' => $latest['version'] ],
				$download['warnings'],
				$progress
			);
		} finally {
			$this->remove( $dir );
		}
	}

	/**
	 * Update an installed theme from the marketplace.
	 *
	 * @param string        $slug     Theme slug.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{theme: Theme, previous: ?string, record: array<string, mixed>, warnings: string[]}|null Null when up to date.
	 * @throws ThemeException When the theme is not installed from the marketplace, or the update fails.
	 */
	public function update( string $slug, ?callable $progress = null ) : ?array {
		$this->assert_slug( $slug );

		if ( ! $this->themes->is_installed( $slug ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" is not installed.', $slug ) );
		}

		if ( self::SOURCE_MARKETPLACE !== ( $this->record( $slug )['source'] ?? null ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" was not installed from the marketplace; install a newer package of it to update it.', $slug ) );
		}

		return $this->install_from_marketplace( $slug, $progress );
	}

	/**
	 * The latest marketplace version of every theme installed from the marketplace.
	 *
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array<string, array{installed: ?string, latest: ?string, update: bool, error: ?string}>
	 */
	public function check_updates( ?callable $progress = null ) : array {
		$results = [];

		foreach ( $this->themes->installed() as $slug => $theme ) {
			if ( self::SOURCE_MARKETPLACE !== ( $this->record( $slug )['source'] ?? null ) ) {
				continue;
			}

			$installed = $theme instanceof Theme ? $theme->version : null;

			try {
				$latest            = $this->server->latest_of( self::MARKETPLACE_TYPE, self::marketplace_slug( $slug ), $progress )['version'];
				$results[ $slug ] = [
					'installed' => $installed,
					'latest'    => $latest,
					'update'    => null === $installed || version_compare( $latest, $installed, '>' ),
					'error'     => null,
				];
			} catch ( UpdateException $e ) {
				$results[ $slug ] = [ 'installed' => $installed, 'latest' => null, 'update' => false, 'error' => $e->getMessage() ];
			}
		}

		return $results;
	}

	/**
	 * The themes the marketplace publishes, with what is installed here.
	 *
	 * Read from the update server's repository REST listing: the hosted apps
	 * MARKETPLACE_TYPE whose slug starts with MARKETPLACE_PREFIX. The server
	 * matches the start of a slug, so a search finds the themes whose slug
	 * starts with the term.
	 *
	 * @param string $search Search term (start of a theme slug); empty to list.
	 * @param int    $page   Page number, from 1.
	 * @return array{
	 *     themes: list<array{slug: string, name: string, version: string, summary: string, author: string,
	 *         icon: string, homepage: string, installed: ?string, update: bool}>,
	 *     pagination: array{total: int, page: int, limit: int, total_pages: int}
	 * }
	 * @throws ThemeException When the marketplace cannot be read.
	 */
	public function marketplace( string $search = '', int $page = 1 ) : array {
		try {
			$result = $this->server->browse( self::MARKETPLACE_TYPE, self::MARKETPLACE_PREFIX . strtolower( trim( $search ) ), $page, self::MARKETPLACE_PER_PAGE );
		} catch ( UpdateException $e ) {
			throw new ThemeException( $e->getMessage(), 0, $e );
		}

		$themes = [];

		foreach ( $result['apps'] as $app ) {
			$hosted = (string) ( $app['slug'] ?? '' );
			$slug   = substr( $hosted, strlen( self::MARKETPLACE_PREFIX ) );

			if ( ! str_starts_with( $hosted, self::MARKETPLACE_PREFIX ) || 1 !== preg_match( Theme::SLUG_PATTERN, $slug ) || ThemeManager::DEFAULT_SLUG === $slug ) {
				continue;
			}

			$version   = (string) ( $app['version'] ?? '' );
			$installed = $this->themes->is_installed( $slug ) ? $this->installed_version( $slug ) : null;

			$themes[] = [
				'slug'      => $slug,
				'name'      => (string) ( $app['name'] ?? $slug ),
				'version'   => $version,
				'summary'   => trim( strip_tags( (string) ( $app['short_description'] ?? '' ) ) ),
				'author'    => trim( strip_tags( (string) ( $app['author'] ?? '' ) ) ),
				'icon'      => (string) ( $app['icon'] ?? '' ),
				'homepage'  => (string) ( $app['homepage'] ?? '' ),
				'installed' => $installed,
				'update'    => null !== $installed && '' !== $version && version_compare( $version, $installed, '>' ),
			];
		}

		return [ 'themes' => $themes, 'pagination' => $result['pagination'] ];
	}

	/*
	|----------
	| REMOVING
	|----------
	*/

	/**
	 * Remove an installed theme and its published assets.
	 *
	 * @param string $slug Theme slug.
	 * @return string[] Warnings, e.g. assets left in public/ because they were not published by us.
	 * @throws ThemeException When the theme is the default, active or a parent of another theme, or cannot be removed.
	 */
	public function remove_theme( string $slug ) : array {
		$this->assert_slug( $slug );

		return $this->locked( function () use ( $slug ) : array {
			if ( ! $this->themes->is_installed( $slug ) ) {
				throw new ThemeException( sprintf( 'The theme "%s" is not installed.', $slug ) );
			}

			if ( $this->themes->active_slug() === $slug ) {
				throw new ThemeException( sprintf( 'The theme "%s" is active; activate another theme before removing it.', $slug ) );
			}

			$children = $this->themes->children( $slug );

			if ( [] !== $children ) {
				throw new ThemeException( sprintf( 'The theme "%s" is the parent of %s; remove those first.', $slug, implode( ', ', $children ) ) );
			}

			$warnings = $this->unpublish( $slug );
			$path     = $this->themes->installed_dir() . '/' . $slug;

			if ( ! $this->fs->delete( $path, true ) ) {
				throw new ThemeException( sprintf( 'Could not remove %s; check the permissions of the storage folder.', $path ) );
			}

			$this->themes->forget( $slug );

			return $warnings;
		} );
	}

	/*
	|------------
	| PUBLISHING
	|------------
	*/

	/**
	 * Publish the assets of every installed theme in public/ again.
	 *
	 * For a server that was moved, or whose symlinks were replaced by copies.
	 *
	 * @param bool $force Rebuild links that are already correct (see PublicAssets::publish()).
	 * @return array<string, string> Theme slug => PublicAssets result, or the error.
	 */
	public function publish_all( bool $force = false ) : array {
		$results = [];

		foreach ( $this->themes->installed() as $slug => $theme ) {
			if ( ! $theme instanceof Theme ) {
				$results[ $slug ] = $theme->getMessage();
				continue;
			}

			try {
				$results[ $slug ] = $this->fs->is_dir( $theme->assets_dir() )
					? $this->assets->publish( $theme->assets_dir(), $theme->public_name(), $force )
					: $this->assets->unpublish( $theme->public_name() );
			} catch ( Throwable $e ) {
				$results[ $slug ] = $e->getMessage();
			}
		}

		return $results;
	}

	/*
	|---------
	| RECORDS
	|---------
	*/

	/**
	 * Where an installed theme came from.
	 *
	 * @param string $slug Theme slug.
	 * @return array{source?: string, origin?: string, sha256?: string, signed_by?: ?string, trusted?: bool, version?: string, installed_at?: string}|null
	 *         Null when there is no record (a theme copied in by hand).
	 */
	public function record( string $slug ) : ?array {
		$file = $this->themes->installed_dir() . '/' . $slug . '/' . self::PACKAGE_RECORD;

		if ( 1 !== preg_match( Theme::SLUG_PATTERN, $slug ) || ! $this->fs->is_file( $file ) ) {
			return null;
		}

		$json   = $this->fs->get_contents( $file );
		$record = false === $json ? null : json_decode( $json, true );

		return is_array( $record ) ? $record : null;
	}

	/**
	 * How a theme was installed, in words, from its record.
	 *
	 * @param array<string, mixed>|null $record From record(); null when there is none.
	 * @return string "Marketplace", "Upload (signed)", "Upload (unsigned, trusted)" or "Copied in".
	 */
	public static function describe_source( ?array $record ) : string {
		return match ( true ) {
			null === $record                                           => 'Copied in',
			self::SOURCE_MARKETPLACE === ( $record['source'] ?? null ) => 'Marketplace',
			true === ( $record['trusted'] ?? false )                   => 'Upload (unsigned, trusted)',
			default                                                    => 'Upload (signed)',
		};
	}

	/**
	 * A theme's hosted app slug on the marketplace.
	 *
	 * @param string $slug Theme slug.
	 * @return string E.g. "smliser-theme-acme".
	 */
	public static function marketplace_slug( string $slug ) : string {
		return self::MARKETPLACE_PREFIX . $slug;
	}

	/**
	 * A theme package's artifact base name.
	 *
	 * @param string $slug    Theme slug.
	 * @param string $version Theme version.
	 * @return string E.g. "smliser-theme-acme-1.0.0".
	 */
	public static function artifact_base( string $slug, string $version ) : string {
		return self::marketplace_slug( $slug ) . '-' . $version;
	}

	/*
	|-----------------------
	| INSTALLING, THE STEPS
	|-----------------------
	*/

	/**
	 * Extract, check and swap in a verified package.
	 *
	 * @param string                    $zip      Path to the verified zip.
	 * @param array<string, mixed>      $origin   The record's source fields.
	 * @param bool                      $replace  Replace an installed theme of the same slug.
	 * @param array{slug: string, version: string}|null $expected What the marketplace announced; null for an upload.
	 * @param string[]                  $warnings Warnings from verification.
	 * @param callable|null             $progress Progress callback.
	 * @return array{theme: Theme, previous: ?string, record: array<string, mixed>, warnings: string[]}
	 * @throws ThemeException When the package is refused or cannot be installed; nothing was changed.
	 */
	private function install_verified( string $zip, array $origin, bool $replace, ?array $expected, array $warnings, ?callable $progress ) : array {
		return $this->locked( function () use ( $zip, $origin, $replace, $expected, $warnings, $progress ) : array {
			$staging = $this->work_path( 'staging-' . bin2hex( random_bytes( 6 ) ) );

			try {
				$this->make_dir( $staging );
				$this->report( $progress, 'stage', self::STATUS_INFO, sprintf( 'Extracting %s.', basename( $zip ) ) );

				$folder = $this->extract( $zip, $staging );
				$theme  = $this->check_staged( $staging . '/' . $folder, $expected );
				$live   = $this->themes->installed_dir() . '/' . $theme->slug;

				$previous = null;

				if ( $this->themes->is_installed( $theme->slug ) ) {
					$previous = $this->installed_version( $theme->slug );

					if ( ! $replace ) {
						throw new ThemeException( sprintf( 'The theme "%s" is already installed (%s); update it, or replace it with this package.', $theme->slug, $previous ?? 'unknown version' ) );
					}

					if ( null !== $previous && version_compare( $theme->version, $previous, '<' ) ) {
						throw new ThemeException( sprintf( 'The package is version %s of "%s", older than the installed %s. Downgrades are not supported.', $theme->version, $theme->slug, $previous ) );
					}
				}

				$record = $origin + [ 'version' => $theme->version, 'installed_at' => gmdate( DATE_ATOM ) ];

				if ( ! $this->fs->put_contents( $theme->path . '/' . self::PACKAGE_RECORD, json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ) ) {
					throw new ThemeException( 'Could not write the package record; check the permissions of the storage folder.' );
				}

				$this->report( $progress, 'stage', self::STATUS_OK, sprintf( 'The package holds %s %s; every check passed.', $theme->name, $theme->version ) );

				$this->swap( $theme->path, $live, $staging );
				$this->themes->forget( $theme->slug );

				$installed = $this->themes->find( $theme->slug );
				$warnings  = array_merge( $warnings, $this->publish( $installed ) );

				$this->report( $progress, 'apply', self::STATUS_OK, null === $previous
					? sprintf( 'Installed the theme "%s" %s.', $installed->slug, $installed->version )
					: sprintf( 'Replaced the theme "%s" %s with %s.', $installed->slug, $previous, $installed->version ) );

				return [ 'theme' => $installed, 'previous' => $previous, 'record' => $record, 'warnings' => $warnings ];
			} finally {
				$this->remove( $staging );
			}
		} );
	}

	/**
	 * Extract a package into an empty folder, refusing anything unsafe.
	 *
	 * @param string $zip    Zip path.
	 * @param string $target Empty folder to extract into.
	 * @return string The package's single top-level folder name.
	 * @throws ThemeException When the zip cannot be read, is too large, or holds unsafe entries.
	 */
	private function extract( string $zip, string $target ) : string {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new ThemeException( 'The zip PHP extension is needed to install themes.' );
		}

		$archive = new ZipArchive();

		if ( true !== $archive->open( $zip, ZipArchive::RDONLY ) ) {
			throw new ThemeException( sprintf( 'Could not open %s as a zip archive.', basename( $zip ) ) );
		}

		try {
			if ( $archive->numFiles < 1 || $archive->numFiles > self::MAX_FILES ) {
				throw new ThemeException( sprintf( 'A theme package holds 1 to %d entries; this one holds %d.', self::MAX_FILES, $archive->numFiles ) );
			}

			$folder = null;
			$bytes  = 0;

			for ( $i = 0; $i < $archive->numFiles; $i++ ) {
				$stat = $archive->statIndex( $i );
				$name = is_array( $stat ) ? (string) $stat['name'] : '';
				$top  = explode( '/', $name )[0];

				if (
					'' === $name
					|| str_starts_with( $name, '/' )
					|| str_contains( $name, '\\' )
					|| str_contains( $name, ':' )
					|| in_array( '..', explode( '/', $name ), true )
					|| 1 !== preg_match( Theme::SLUG_PATTERN, $top )
					|| ( null !== $folder && $top !== $folder )
				) {
					throw new ThemeException( sprintf( 'The package holds an unexpected path (%s): a theme package holds one folder named after the theme. Nothing was installed.', $name ) );
				}

				$folder = $top;

				if ( $archive->getExternalAttributesIndex( $i, $opsys, $attributes ) && ZipArchive::OPSYS_UNIX === $opsys && 0120000 === ( ( $attributes >> 16 ) & 0170000 ) ) {
					throw new ThemeException( sprintf( 'The package holds a link (%s); links are not allowed in a theme package.', $name ) );
				}

				$bytes += (int) $stat['size'];

				if ( $bytes > self::MAX_BYTES ) {
					throw new ThemeException( sprintf( 'The package unpacks to more than %d MB; a theme package must be smaller.', intdiv( self::MAX_BYTES, 1048576 ) ) );
				}
			}

			if ( ! $archive->extractTo( $target ) ) {
				throw new ThemeException( sprintf( 'Could not extract %s; check the free disk space.', basename( $zip ) ) );
			}

			return (string) $folder;
		} finally {
			$archive->close();
		}
	}

	/**
	 * Check an extracted theme folder before it is swapped in.
	 *
	 * @param string                                    $path     The extracted theme folder.
	 * @param array{slug: string, version: string}|null $expected What the marketplace announced; null for an upload.
	 * @return Theme
	 * @throws ThemeException When the theme is invalid or unsafe.
	 */
	private function check_staged( string $path, ?array $expected ) : Theme {
		$theme = Theme::from_directory( $this->fs, $path );

		if ( ThemeManager::DEFAULT_SLUG === $theme->slug ) {
			throw new ThemeException( 'The default theme ships with the application and cannot be installed or replaced.' );
		}

		if ( null !== $expected && ( $expected['slug'] !== $theme->slug || $expected['version'] !== $theme->version ) ) {
			throw new ThemeException( sprintf( 'The marketplace announced "%s" %s, but the package holds "%s" %s. Nothing was installed.', $expected['slug'], $expected['version'], $theme->slug, $theme->version ) );
		}

		if ( ! $theme->supports( \SMLISER_VER ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" %s requires app version %s or later; this is %s.', $theme->slug, $theme->version, $theme->requires, \SMLISER_VER ) );
		}

		if ( null !== $theme->parent && ThemeManager::DEFAULT_SLUG !== $theme->parent ) {
			if ( ! $this->themes->is_installed( $theme->parent ) ) {
				throw new ThemeException( sprintf( 'The theme "%s" needs its parent theme "%s"; install the parent first.', $theme->slug, $theme->parent ) );
			}

			if ( null !== $this->themes->find( $theme->parent )->parent ) {
				throw new ThemeException( sprintf( 'The parent "%s" of the theme "%s" has a parent itself; only one level is supported.', $theme->parent, $theme->slug ) );
			}
		}

		$this->check_files( $path, '' );

		return $theme;
	}

	/**
	 * Refuse PHP outside templates/, and server configuration files in assets/.
	 *
	 * @param string $root     The extracted theme folder.
	 * @param string $relative Folder being checked, relative to $root ('' for the root).
	 * @return void
	 * @throws ThemeException
	 */
	private function check_files( string $root, string $relative ) : void {
		$entries = $this->fs->list_dir( '' === $relative ? $root : $root . '/' . $relative );

		foreach ( is_array( $entries ) ? $entries : [] as $entry ) {
			$name = basename( $entry['path'] );
			$path = '' === $relative ? $name : $relative . '/' . $name;

			if ( $entry['is_dir'] ) {
				$this->check_files( $root, $path );
				continue;
			}

			$in_assets    = str_starts_with( $path, 'assets/' );
			$in_templates = str_starts_with( $path, 'templates/' );

			if ( $in_assets && in_array( strtolower( $name ), self::SERVER_FILES, true ) ) {
				throw new ThemeException( sprintf( 'The package holds the server configuration file %s in assets/; assets are served as they are, so it is not allowed.', $path ) );
			}

			if ( ! $in_templates && [] !== array_intersect( array_slice( explode( '.', strtolower( $name ) ), 1 ), self::PHP_EXTENSIONS ) ) {
				throw new ThemeException( sprintf( 'The package holds the PHP file %s outside templates/; PHP runs only from templates/.', $path ) );
			}
		}
	}

	/**
	 * Swap a staged theme folder in for the live one, restoring it on failure.
	 *
	 * @param string $staged  The checked theme folder.
	 * @param string $live    Where the theme lives.
	 * @param string $staging The staging folder (holds the backup during the swap).
	 * @return void
	 * @throws ThemeException When the swap fails (after restoring the live folder).
	 */
	private function swap( string $staged, string $live, string $staging ) : void {
		$backup = $staging . '/.previous';
		$had    = $this->fs->is_dir( $live );

		if ( $had && ! $this->fs->rename( $live, $backup ) ) {
			throw new ThemeException( sprintf( 'Could not move the installed theme out of %s; check the permissions of the storage folder.', $live ) );
		}

		if ( ! $this->fs->rename( $staged, $live ) ) {
			if ( $had && ! $this->fs->rename( $backup, $live ) ) {
				throw new ThemeException( sprintf( 'Could not install the theme, nor put the previous one back: it is in %s. Move it back to %s by hand.', $backup, $live ) );
			}

			throw new ThemeException( sprintf( 'Could not move the theme into %s; check the permissions of the storage folder. The previous files are back.', $live ) );
		}
	}

	/**
	 * Publish a theme's assets, or remove a stale publication when it has none.
	 *
	 * Failing to publish does not undo the install; it is reported.
	 *
	 * @param Theme $theme
	 * @return string[] Warnings.
	 */
	private function publish( Theme $theme ) : array {
		try {
			$result = $this->fs->is_dir( $theme->assets_dir() )
				? $this->assets->refresh( $theme->assets_dir(), $theme->public_name() )
				: $this->assets->unpublish( $theme->public_name() );
		} catch ( Throwable $e ) {
			return [ sprintf( 'The theme assets could not be published: %s Run `site-theme publish` once the public folder is writable.', $e->getMessage() ) ];
		}

		return PublicAssets::KEPT === $result
			? [ sprintf( 'public/%s holds files that were not published by this application, so the theme assets were not published there. Remove them and run `site-theme publish`.', $theme->public_name() ) ]
			: [];
	}

	/**
	 * Remove a theme's published assets.
	 *
	 * @param string $slug
	 * @return string[] Warnings.
	 */
	private function unpublish( string $slug ) : array {
		$name = 'themes/' . $slug;

		try {
			$result = $this->assets->unpublish( $name );
		} catch ( Throwable $e ) {
			return [ sprintf( 'The published assets in public/%s could not be removed: %s', $name, $e->getMessage() ) ];
		}

		return PublicAssets::KEPT === $result
			? [ sprintf( 'public/%s holds files that were not published by this application; they were left in place.', $name ) ]
			: [];
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * The version of an installed theme, as its theme.json or record says.
	 *
	 * @param string $slug
	 * @return string|null Null when neither can be read.
	 */
	private function installed_version( string $slug ) : ?string {
		try {
			$this->themes->forget( $slug );
			return $this->themes->find( $slug )->version;
		} catch ( ThemeException ) {
			$version = $this->record( $slug )['version'] ?? null;
			return is_string( $version ) ? $version : null;
		}
	}

	/**
	 * Refuse a slug that is not a theme slug, or is the default theme's.
	 *
	 * @param string $slug
	 * @return void
	 * @throws ThemeException
	 */
	private function assert_slug( string $slug ) : void {
		if ( 1 !== preg_match( Theme::SLUG_PATTERN, $slug ) ) {
			throw new ThemeException( sprintf( '"%s" is not a theme slug: use lowercase letters, digits and hyphens.', $slug ) );
		}

		if ( ThemeManager::DEFAULT_SLUG === $slug ) {
			throw new ThemeException( 'The default theme ships with the application; it is updated with the application.' );
		}
	}

	/**
	 * The contents of an uploaded text file, or null when none was uploaded.
	 *
	 * @param UploadedFile|null $file
	 * @return string|null
	 * @throws ThemeException When the file was uploaded but cannot be read.
	 */
	private function uploaded_text( ?UploadedFile $file ) : ?string {
		if ( null === $file || ! $file->exists() || UPLOAD_ERR_NO_FILE === $file->get_error_code() ) {
			return null;
		}

		$contents = $file->is_uploaded_file() ? $this->fs->get_contents( (string) $file->get_tmp_path() ) : false;

		if ( ! is_string( $contents ) ) {
			throw new ThemeException( $file->get_error_message() );
		}

		return $contents;
	}

	/**
	 * Run a step holding the theme lock, so one install, update or removal runs at a time.
	 *
	 * @template T
	 * @param callable(): T $step
	 * @return T
	 * @throws ThemeException When another process holds the lock.
	 */
	private function locked( callable $step ) : mixed {
		if ( null !== $this->lock ) {
			return $step();
		}

		$this->make_dir( $this->work_path( '' ) );

		$handle = @fopen( $this->work_path( 'lock' ), 'c' );

		if ( false === $handle ) {
			throw new ThemeException( 'Could not create the theme lock; check the permissions of the storage folder.' );
		}

		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			throw new ThemeException( 'Another theme is being installed, updated or removed; try again in a moment.' );
		}

		$this->lock = $handle;

		try {
			return $step();
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle );
			$this->lock = null;
		}
	}

	/**
	 * A path in the working folder.
	 *
	 * @param string $name Entry name; '' for the folder itself.
	 * @return string
	 */
	private function work_path( string $name ) : string {
		return $this->themes->installed_dir() . '/' . self::WORK_DIR . ( '' === $name ? '' : '/' . $name );
	}

	/**
	 * Create a folder or fail.
	 *
	 * @param string $dir
	 * @return void
	 * @throws ThemeException
	 */
	private function make_dir( string $dir ) : void {
		if ( ! $this->fs->is_dir( $dir ) && ! $this->fs->mkdir( $dir ) ) {
			throw new ThemeException( sprintf( 'Could not create %s; check the permissions of the storage folder.', $dir ) );
		}
	}

	/**
	 * Remove a file or folder if it exists.
	 *
	 * @param string $path
	 * @return void
	 */
	private function remove( string $path ) : void {
		if ( $this->fs->exists( $path ) ) {
			$this->fs->delete( $path, true );
		}
	}
}
