<?php
/**
 * Module installer class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Modules
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Modules;

use RuntimeException;
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
 * Installs, updates and removes the modules (themes, extensions) of one type.
 *
 * The pipeline is the same for every type; a type's installer says what is
 * its own: the exception it throws, its manifest and package rules, the
 * marketplace prefix, and what must hold before a module is removed.
 *
 * Philosophy:
 *
 *  - A package is the whole module. A zip holding one folder named after
 *    the module slug. Installing puts that folder in the type's installed
 *    folder (storage/<type>s/<slug>); updating replaces it whole, so files a
 *    new version dropped disappear with the old folder.
 *  - Packages are verified the way app updates are (PackageVerifier):
 *    <base>.zip beside <base>.sha256 and <base>.sha256.sig, the checksums
 *    signed by a release key. Marketplace packages must be signed. An
 *    uploaded package without a valid signature installs only when the
 *    administrator trusts it explicitly, and the record says so.
 *  - The marketplace is the update server (UpdateServer::HOST). A module
 *    "<slug>" is published there as the hosted app MARKETPLACE_TYPE /
 *    "<MARKETPLACE_PREFIX><slug>", with the artifacts
 *    "<MARKETPLACE_PREFIX><slug>-<version>.zip|.sha256|.sha256.sig".
 *  - Assets are published in public/, where a copy (on servers without
 *    symlinks) is served by the web server as it is. A package with PHP in
 *    assets/, or with server configuration files (.htaccess, .user.ini,
 *    web.config) there, is refused. Links inside the zip, paths leaving its
 *    folder, and packages over MAX_FILES files or MAX_BYTES bytes are
 *    refused too.
 *  - Nothing changes until the new folder is complete. The package is
 *    extracted and checked in <installed folder>/.work; the live folder is
 *    then swapped with renames, and put back if the swap fails.
 *  - Each installed module carries a record of where it came from
 *    (PACKAGE_RECORD, in its folder): the source, the SHA-256, the key that
 *    signed it. Updates from the marketplace are offered only for modules
 *    installed from it.
 */
abstract class ModuleInstaller {

	use UpdateProgressTrait;

	/**
	 * What a module of this type is called in messages.
	 */
	public const NOUN = 'module';

	/**
	 * The key of the installed module in the result of an install or update.
	 */
	public const RESULT_KEY = 'module';

	/**
	 * The key of the modules in the result of a marketplace listing.
	 */
	public const LIST_KEY = 'modules';

	/**
	 * Hosted app type of modules on the marketplace.
	 */
	public const MARKETPLACE_TYPE = '';

	/**
	 * Prefix of a module's hosted app slug on the marketplace.
	 */
	public const MARKETPLACE_PREFIX = '';

	/**
	 * Working folder, inside the installed folder (same filesystem, so swaps are renames).
	 */
	public const WORK_DIR = '.work';

	/**
	 * The record of where an installed module came from, in its folder.
	 */
	public const PACKAGE_RECORD = '.smliser-package.json';

	/**
	 * Sources of an installed module.
	 */
	public const SOURCE_MARKETPLACE = 'marketplace';
	public const SOURCE_UPLOAD      = 'upload';

	/**
	 * Modules per page of the marketplace listing.
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
	 * Handle of the held lock.
	 *
	 * @var resource|null
	 */
	private $lock = null;

	/**
	 * Constructor.
	 *
	 * @param FileSystem      $fs       Filesystem API.
	 * @param ModuleManager   $modules  The installed modules of this type.
	 * @param PublicAssets    $assets   Publishes each module's assets in public/.
	 * @param PackageVerifier $verifier Checks a package against its checksums and signature.
	 * @param UpdateServer    $server   The marketplace.
	 */
	public function __construct(
		protected FileSystem $fs,
		protected ModuleManager $modules,
		protected PublicAssets $assets,
		protected PackageVerifier $verifier,
		protected UpdateServer $server
	) {}

	/*
	|----------------------
	| TYPE: WHAT DIFFERS
	|----------------------
	*/

	/**
	 * The exception of this type.
	 *
	 * @param string         $message
	 * @param Throwable|null $previous
	 * @return RuntimeException
	 */
	abstract protected function fail( string $message, ?Throwable $previous = null ) : RuntimeException;

	/**
	 * The pattern of a slug of this type.
	 *
	 * @return string A regular expression.
	 */
	abstract protected function slug_pattern() : string;

	/**
	 * Load a module of this type from an extracted folder: its manifest, nothing more.
	 *
	 * @param string $path The extracted module folder.
	 * @return Module
	 * @throws RuntimeException When the folder is not a valid module (the exception of the type).
	 */
	abstract protected function load_staged( string $path ) : Module;

	/**
	 * The name of a module's assets inside public/, from its slug alone.
	 *
	 * @param string $slug
	 * @return string
	 */
	abstract protected function public_name_of( string $slug ) : string;

	/**
	 * What to do when assets could not be published, as a sentence start in lowercase.
	 *
	 * @return string E.g. "run `site-theme publish`".
	 */
	abstract protected function publish_action() : string;

	/**
	 * Why a slug cannot be installed, replaced, updated or removed here.
	 *
	 * @param string $slug
	 * @return string|null The message; null when the slug is not reserved.
	 */
	protected function reserved_message( string $slug ) : ?string {
		return null;
	}

	/**
	 * Checks of a staged module that only its type makes.
	 *
	 * @param Module $module The staged module.
	 * @return void
	 * @throws RuntimeException When the module is refused.
	 */
	protected function check_staged_extra( Module $module ) : void {}

	/**
	 * Why a PHP file outside assets/ is refused.
	 *
	 * PHP in assets/ is always refused. A type that runs PHP only from some
	 * folders refuses it elsewhere here.
	 *
	 * @param string $path The file's path, relative to the module folder.
	 * @return string|null The message; null when PHP is allowed there.
	 */
	protected function php_refusal( string $path ) : ?string {
		return null;
	}

	/**
	 * What must hold before an installed module is removed.
	 *
	 * @param string $slug
	 * @return void
	 * @throws RuntimeException When the module cannot be removed now.
	 */
	protected function assert_removable( string $slug ) : void {}

	/*
	|------------
	| INSTALLING
	|------------
	*/

	/**
	 * Install (or, with $replace, reinstall or update) a module from a local package.
	 *
	 * @param string        $zip            Path to the package zip.
	 * @param string|null   $checksums      Contents of its .sha256 file; null when there is none.
	 * @param string|null   $signature      Contents of its .sha256.sig file; null when there is none.
	 * @param bool          $trust_unsigned Install without valid checksums or signature.
	 * @param bool          $replace        Replace an installed module of the same slug (same or newer version).
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{previous: ?string, record: array<string, mixed>, warnings: string[]}
	 *         The installed module (under RESULT_KEY), the version it replaced (null for a new install),
	 *         its record, and warnings.
	 * @throws RuntimeException When the package is refused or cannot be installed; nothing was changed.
	 */
	public function install_zip( string $zip, ?string $checksums, ?string $signature, bool $trust_unsigned = false, bool $replace = false, ?callable $progress = null ) : array {
		try {
			$verified = $this->verifier->verify( $zip, $checksums, $signature, $trust_unsigned, $progress );
		} catch ( UpdateException $e ) {
			throw $this->fail( $e->getMessage(), $e );
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
	 * Install a module from uploaded files (the admin page).
	 *
	 * The package is moved into the working folder under its uploaded name,
	 * since its checksums file lists it by that name.
	 *
	 * @param UploadedFile      $package        The package zip.
	 * @param UploadedFile|null $checksums      Its .sha256 file, if uploaded.
	 * @param UploadedFile|null $signature      Its .sha256.sig file, if uploaded.
	 * @param bool              $trust_unsigned Install without valid checksums or signature.
	 * @param bool              $replace        Replace an installed module of the same slug.
	 * @return array{previous: ?string, record: array<string, mixed>, warnings: string[]} As install_zip().
	 * @throws RuntimeException When the upload is unusable or the package is refused.
	 */
	public function install_upload( UploadedFile $package, ?UploadedFile $checksums, ?UploadedFile $signature, bool $trust_unsigned = false, bool $replace = false ) : array {
		if ( ! $package->is_uploaded_file() ) {
			throw $this->fail( $package->get_error_message() );
		}

		$name = (string) $package->get_client_name();

		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*\.zip$/i', $name ) ) {
			throw $this->fail( sprintf( 'Upload the %s package as a .zip file whose name uses letters, digits, dots, hyphens and underscores.', static::NOUN ) );
		}

		$dir = $this->work_path( 'upload-' . bin2hex( random_bytes( 6 ) ) );

		try {
			$this->make_dir( $dir );

			$zip = $dir . '/' . $name;

			if ( ! $this->fs->move( (string) $package->get_tmp_path(), $zip, true ) ) {
				throw $this->fail( 'The uploaded package could not be stored; check the permissions of the storage folder.' );
			}

			return $this->install_zip( $zip, $this->uploaded_text( $checksums ), $this->uploaded_text( $signature ), $trust_unsigned, $replace );
		} finally {
			$this->delete_path( $dir );
		}
	}

	/**
	 * Install a module from the marketplace, or update it when it is installed.
	 *
	 * Marketplace packages must be signed.
	 *
	 * @param string        $slug     Module slug.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{previous: ?string, record: array<string, mixed>, warnings: string[]}|null
	 *         As install_zip(); null when the installed version is already the latest.
	 * @throws RuntimeException When the marketplace cannot provide it or the package is refused.
	 */
	public function install_from_marketplace( string $slug, ?callable $progress = null ) : ?array {
		$this->assert_slug( $slug );

		$installed = $this->modules->is_installed( $slug ) ? $this->installed_version( $slug ) : null;

		try {
			$latest = $this->server->latest_of( static::MARKETPLACE_TYPE, static::marketplace_slug( $slug ), $progress );
		} catch ( UpdateException $e ) {
			throw $this->fail( $e->getMessage(), $e );
		}

		if ( null !== $installed && version_compare( $latest['version'], $installed, '<=' ) ) {
			$this->report( $progress, 'check', self::STATUS_OK, sprintf( 'The %s "%s" is up to date (%s).', static::NOUN, $slug, $installed ) );
			return null;
		}

		$dir = $this->work_path( 'download-' . bin2hex( random_bytes( 6 ) ) );

		try {
			$this->make_dir( $dir );

			try {
				$download = $this->server->download_artifact( static::MARKETPLACE_TYPE, static::marketplace_slug( $slug ), static::artifact_base( $slug, $latest['version'] ), $dir, false, $progress );
			} catch ( UpdateException $e ) {
				throw $this->fail( $e->getMessage(), $e );
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
			$this->delete_path( $dir );
		}
	}

	/**
	 * Update an installed module from the marketplace.
	 *
	 * @param string        $slug     Module slug.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{previous: ?string, record: array<string, mixed>, warnings: string[]}|null As install_zip(); null when up to date.
	 * @throws RuntimeException When the module is not installed from the marketplace, or the update fails.
	 */
	public function update( string $slug, ?callable $progress = null ) : ?array {
		$this->assert_slug( $slug );

		if ( ! $this->modules->is_installed( $slug ) ) {
			throw $this->fail( sprintf( 'The %s "%s" is not installed.', static::NOUN, $slug ) );
		}

		if ( self::SOURCE_MARKETPLACE !== ( $this->record( $slug )['source'] ?? null ) ) {
			throw $this->fail( sprintf( 'The %s "%s" was not installed from the marketplace; install a newer package of it to update it.', static::NOUN, $slug ) );
		}

		return $this->install_from_marketplace( $slug, $progress );
	}

	/**
	 * The latest marketplace version of every module installed from the marketplace.
	 *
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array<string, array{installed: ?string, latest: ?string, update: bool, error: ?string}>
	 */
	public function check_updates( ?callable $progress = null ) : array {
		$results = [];

		foreach ( $this->modules->installed() as $slug => $module ) {
			if ( self::SOURCE_MARKETPLACE !== ( $this->record( $slug )['source'] ?? null ) ) {
				continue;
			}

			$installed = $module instanceof Module ? $module->version : null;

			try {
				$latest           = $this->server->latest_of( static::MARKETPLACE_TYPE, static::marketplace_slug( $slug ), $progress )['version'];
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
	 * The modules the marketplace publishes, with what is installed here.
	 *
	 * Read from the update server's repository REST listing: the hosted apps
	 * MARKETPLACE_TYPE whose slug starts with MARKETPLACE_PREFIX. The server
	 * matches the start of a slug, so a search finds the modules whose slug
	 * starts with the term.
	 *
	 * @param string $search Search term (start of a module slug); empty to list.
	 * @param int    $page   Page number, from 1.
	 * @return array{
	 *     pagination: array{total: int, page: int, limit: int, total_pages: int}
	 * } The modules, under LIST_KEY, each as list<array{slug: string, name: string, version: string,
	 *   summary: string, author: string, icon: string, homepage: string, installed: ?string, update: bool}>.
	 * @throws RuntimeException When the marketplace cannot be read.
	 */
	public function marketplace( string $search = '', int $page = 1 ) : array {
		try {
			$result = $this->server->browse( static::MARKETPLACE_TYPE, static::MARKETPLACE_PREFIX . strtolower( trim( $search ) ), $page, self::MARKETPLACE_PER_PAGE );
		} catch ( UpdateException $e ) {
			throw $this->fail( $e->getMessage(), $e );
		}

		$modules = [];

		foreach ( $result['apps'] as $app ) {
			$hosted = (string) ( $app['slug'] ?? '' );
			$slug   = substr( $hosted, strlen( static::MARKETPLACE_PREFIX ) );

			if ( ! str_starts_with( $hosted, static::MARKETPLACE_PREFIX ) || 1 !== preg_match( $this->slug_pattern(), $slug ) || null !== $this->reserved_message( $slug ) ) {
				continue;
			}

			$version   = (string) ( $app['version'] ?? '' );
			$installed = $this->modules->is_installed( $slug ) ? $this->installed_version( $slug ) : null;

			$modules[] = [
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

		return [ static::LIST_KEY => $modules, 'pagination' => $result['pagination'] ];
	}

	/*
	|----------
	| REMOVING
	|----------
	*/

	/**
	 * Remove an installed module and its published assets.
	 *
	 * @param string $slug Module slug.
	 * @return string[] Warnings, e.g. assets left in public/ because they were not published by us.
	 * @throws RuntimeException When the module cannot be removed now (assert_removable()) or at all.
	 */
	protected function uninstall( string $slug ) : array {
		$this->assert_slug( $slug );

		return $this->locked( function () use ( $slug ) : array {
			if ( ! $this->modules->is_installed( $slug ) ) {
				throw $this->fail( sprintf( 'The %s "%s" is not installed.', static::NOUN, $slug ) );
			}

			$this->assert_removable( $slug );

			$warnings = $this->unpublish( $slug );
			$path     = $this->modules->installed_dir() . '/' . $slug;

			if ( ! $this->fs->delete( $path, true ) ) {
				throw $this->fail( sprintf( 'Could not remove %s; check the permissions of the storage folder.', $path ) );
			}

			$this->modules->forget( $slug );

			return $warnings;
		} );
	}

	/*
	|------------
	| PUBLISHING
	|------------
	*/

	/**
	 * Publish the assets of every installed module in public/ again.
	 *
	 * For a server that was moved, or whose symlinks were replaced by copies.
	 *
	 * @param bool $force Rebuild links that are already correct (see PublicAssets::publish()).
	 * @return array<string, string> Module slug => PublicAssets result, or the error.
	 */
	public function publish_all( bool $force = false ) : array {
		$results = [];

		foreach ( $this->modules->installed() as $slug => $module ) {
			if ( ! $module instanceof Module ) {
				$results[ $slug ] = $module->getMessage();
				continue;
			}

			try {
				$results[ $slug ] = $this->fs->is_dir( $module->assets_dir() )
					? $this->assets->publish( $module->assets_dir(), $module->public_name(), $force )
					: $this->assets->unpublish( $module->public_name() );
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
	 * Where an installed module came from.
	 *
	 * @param string $slug Module slug.
	 * @return array{source?: string, origin?: string, sha256?: string, signed_by?: ?string, trusted?: bool, version?: string, installed_at?: string}|null
	 *         Null when there is no record (a module copied in by hand).
	 */
	public function record( string $slug ) : ?array {
		$file = $this->modules->installed_dir() . '/' . $slug . '/' . self::PACKAGE_RECORD;

		if ( 1 !== preg_match( $this->slug_pattern(), $slug ) || ! $this->fs->is_file( $file ) ) {
			return null;
		}

		$json   = $this->fs->get_contents( $file );
		$record = false === $json ? null : json_decode( $json, true );

		return is_array( $record ) ? $record : null;
	}

	/**
	 * How a module was installed, in words, from its record.
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
	 * A module's hosted app slug on the marketplace.
	 *
	 * @param string $slug Module slug.
	 * @return string E.g. "smliser-theme-acme".
	 */
	public static function marketplace_slug( string $slug ) : string {
		return static::MARKETPLACE_PREFIX . $slug;
	}

	/**
	 * A module package's artifact base name.
	 *
	 * @param string $slug    Module slug.
	 * @param string $version Module version.
	 * @return string E.g. "smliser-theme-acme-1.0.0".
	 */
	public static function artifact_base( string $slug, string $version ) : string {
		return static::marketplace_slug( $slug ) . '-' . $version;
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
	 * @param bool                      $replace  Replace an installed module of the same slug.
	 * @param array{slug: string, version: string}|null $expected What the marketplace announced; null for an upload.
	 * @param string[]                  $warnings Warnings from verification.
	 * @param callable|null             $progress Progress callback.
	 * @return array{previous: ?string, record: array<string, mixed>, warnings: string[]} As install_zip().
	 * @throws RuntimeException When the package is refused or cannot be installed; nothing was changed.
	 */
	private function install_verified( string $zip, array $origin, bool $replace, ?array $expected, array $warnings, ?callable $progress ) : array {
		return $this->locked( function () use ( $zip, $origin, $replace, $expected, $warnings, $progress ) : array {
			$staging = $this->work_path( 'staging-' . bin2hex( random_bytes( 6 ) ) );

			try {
				$this->make_dir( $staging );
				$this->report( $progress, 'stage', self::STATUS_INFO, sprintf( 'Extracting %s.', basename( $zip ) ) );

				$folder = $this->extract( $zip, $staging );
				$module = $this->check_staged( $staging . '/' . $folder, $expected );
				$live   = $this->modules->installed_dir() . '/' . $module->slug;

				$previous = null;

				if ( $this->modules->is_installed( $module->slug ) ) {
					$previous = $this->installed_version( $module->slug );

					if ( ! $replace ) {
						throw $this->fail( sprintf( 'The %s "%s" is already installed (%s); update it, or replace it with this package.', static::NOUN, $module->slug, $previous ?? 'unknown version' ) );
					}

					if ( null !== $previous && version_compare( $module->version, $previous, '<' ) ) {
						throw $this->fail( sprintf( 'The package is version %s of "%s", older than the installed %s. Downgrades are not supported.', $module->version, $module->slug, $previous ) );
					}
				}

				$record = $origin + [ 'version' => $module->version, 'installed_at' => gmdate( DATE_ATOM ) ];

				if ( ! $this->fs->put_contents( $module->path . '/' . self::PACKAGE_RECORD, json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ) ) {
					throw $this->fail( 'Could not write the package record; check the permissions of the storage folder.' );
				}

				$this->report( $progress, 'stage', self::STATUS_OK, sprintf( 'The package holds %s %s; every check passed.', $module->name, $module->version ) );

				$this->swap( $module->path, $live, $staging );
				$this->modules->forget( $module->slug );

				$installed = $this->modules->find( $module->slug );
				$warnings  = array_merge( $warnings, $this->publish( $installed ) );

				$this->report( $progress, 'apply', self::STATUS_OK, null === $previous
					? sprintf( 'Installed the %s "%s" %s.', static::NOUN, $installed->slug, $installed->version )
					: sprintf( 'Replaced the %s "%s" %s with %s.', static::NOUN, $installed->slug, $previous, $installed->version ) );

				return [ static::RESULT_KEY => $installed, 'previous' => $previous, 'record' => $record, 'warnings' => $warnings ];
			} finally {
				$this->delete_path( $staging );
			}
		} );
	}

	/**
	 * Extract a package into an empty folder, refusing anything unsafe.
	 *
	 * @param string $zip    Zip path.
	 * @param string $target Empty folder to extract into.
	 * @return string The package's single top-level folder name.
	 * @throws RuntimeException When the zip cannot be read, is too large, or holds unsafe entries.
	 */
	private function extract( string $zip, string $target ) : string {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw $this->fail( sprintf( 'The zip PHP extension is needed to install %ss.', static::NOUN ) );
		}

		$archive = new ZipArchive();

		if ( true !== $archive->open( $zip, ZipArchive::RDONLY ) ) {
			throw $this->fail( sprintf( 'Could not open %s as a zip archive.', basename( $zip ) ) );
		}

		try {
			if ( $archive->numFiles < 1 || $archive->numFiles > static::MAX_FILES ) {
				throw $this->fail( sprintf( 'A %s package holds 1 to %d entries; this one holds %d.', static::NOUN, static::MAX_FILES, $archive->numFiles ) );
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
					|| 1 !== preg_match( $this->slug_pattern(), $top )
					|| ( null !== $folder && $top !== $folder )
				) {
					throw $this->fail( sprintf( 'The package holds an unexpected path (%s): a %s package holds one folder named after the %s. Nothing was installed.', $name, static::NOUN, static::NOUN ) );
				}

				$folder = $top;

				if ( $archive->getExternalAttributesIndex( $i, $opsys, $attributes ) && ZipArchive::OPSYS_UNIX === $opsys && 0120000 === ( ( $attributes >> 16 ) & 0170000 ) ) {
					throw $this->fail( sprintf( 'The package holds a link (%s); links are not allowed in a %s package.', $name, static::NOUN ) );
				}

				$bytes += (int) $stat['size'];

				if ( $bytes > static::MAX_BYTES ) {
					throw $this->fail( sprintf( 'The package unpacks to more than %d MB; a %s package must be smaller.', intdiv( static::MAX_BYTES, 1048576 ), static::NOUN ) );
				}
			}

			if ( ! $archive->extractTo( $target ) ) {
				throw $this->fail( sprintf( 'Could not extract %s; check the free disk space.', basename( $zip ) ) );
			}

			return (string) $folder;
		} finally {
			$archive->close();
		}
	}

	/**
	 * Check an extracted module folder before it is swapped in.
	 *
	 * @param string                                    $path     The extracted module folder.
	 * @param array{slug: string, version: string}|null $expected What the marketplace announced; null for an upload.
	 * @return Module
	 * @throws RuntimeException When the module is invalid or unsafe.
	 */
	private function check_staged( string $path, ?array $expected ) : Module {
		$module   = $this->load_staged( $path );
		$reserved = $this->reserved_message( $module->slug );

		if ( null !== $reserved ) {
			throw $this->fail( $reserved );
		}

		if ( null !== $expected && ( $expected['slug'] !== $module->slug || $expected['version'] !== $module->version ) ) {
			throw $this->fail( sprintf( 'The marketplace announced "%s" %s, but the package holds "%s" %s. Nothing was installed.', $expected['slug'], $expected['version'], $module->slug, $module->version ) );
		}

		if ( ! $module->supports( \SMLISER_VER ) ) {
			throw $this->fail( sprintf( 'The %s "%s" %s requires app version %s or later; this is %s.', static::NOUN, $module->slug, $module->version, (string) $module->requires, \SMLISER_VER ) );
		}

		$this->check_staged_extra( $module );
		$this->check_files( $path, '' );

		return $module;
	}

	/**
	 * Refuse PHP in assets/, server configuration files in assets/, and PHP the type does not allow.
	 *
	 * @param string $root     The extracted module folder.
	 * @param string $relative Folder being checked, relative to $root ('' for the root).
	 * @return void
	 * @throws RuntimeException
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

			$in_assets = str_starts_with( $path, 'assets/' );
			$is_php    = [] !== array_intersect( array_slice( explode( '.', strtolower( $name ) ), 1 ), self::PHP_EXTENSIONS );

			if ( $in_assets && in_array( strtolower( $name ), self::SERVER_FILES, true ) ) {
				throw $this->fail( sprintf( 'The package holds the server configuration file %s in assets/; assets are served as they are, so it is not allowed.', $path ) );
			}

			if ( $in_assets && $is_php ) {
				throw $this->fail( sprintf( 'The package holds the PHP file %s in assets/; assets are served as they are, so PHP is not allowed there.', $path ) );
			}

			if ( ! $in_assets && $is_php && null !== ( $refusal = $this->php_refusal( $path ) ) ) {
				throw $this->fail( $refusal );
			}
		}
	}

	/**
	 * Swap a staged module folder in for the live one, restoring it on failure.
	 *
	 * @param string $staged  The checked module folder.
	 * @param string $live    Where the module lives.
	 * @param string $staging The staging folder (holds the backup during the swap).
	 * @return void
	 * @throws RuntimeException When the swap fails (after restoring the live folder).
	 */
	private function swap( string $staged, string $live, string $staging ) : void {
		$backup = $staging . '/.previous';
		$had    = $this->fs->is_dir( $live );

		if ( $had && ! $this->fs->rename( $live, $backup ) ) {
			throw $this->fail( sprintf( 'Could not move the installed %s out of %s; check the permissions of the storage folder.', static::NOUN, $live ) );
		}

		if ( ! $this->fs->rename( $staged, $live ) ) {
			if ( $had && ! $this->fs->rename( $backup, $live ) ) {
				throw $this->fail( sprintf( 'Could not install the %s, nor put the previous one back: it is in %s. Move it back to %s by hand.', static::NOUN, $backup, $live ) );
			}

			throw $this->fail( sprintf( 'Could not move the %s into %s; check the permissions of the storage folder. The previous files are back.', static::NOUN, $live ) );
		}
	}

	/**
	 * Publish a module's assets, or remove a stale publication when it has none.
	 *
	 * Failing to publish does not undo the install; it is reported.
	 *
	 * @param Module $module
	 * @return string[] Warnings.
	 */
	private function publish( Module $module ) : array {
		try {
			$result = $this->fs->is_dir( $module->assets_dir() )
				? $this->assets->refresh( $module->assets_dir(), $module->public_name() )
				: $this->assets->unpublish( $module->public_name() );
		} catch ( Throwable $e ) {
			return [ sprintf( 'The %s assets could not be published: %s %s once the public folder is writable.', static::NOUN, $e->getMessage(), ucfirst( $this->publish_action() ) ) ];
		}

		return PublicAssets::KEPT === $result
			? [ sprintf( 'public/%s holds files that were not published by this application, so the %s assets were not published there. Remove them and %s.', $module->public_name(), static::NOUN, $this->publish_action() ) ]
			: [];
	}

	/**
	 * Remove a module's published assets.
	 *
	 * @param string $slug
	 * @return string[] Warnings.
	 */
	private function unpublish( string $slug ) : array {
		$name = $this->public_name_of( $slug );

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
	 * The version of an installed module, as its manifest or record says.
	 *
	 * @param string $slug
	 * @return string|null Null when neither can be read.
	 */
	protected function installed_version( string $slug ) : ?string {
		try {
			$this->modules->forget( $slug );
			return $this->modules->find( $slug )->version;
		} catch ( RuntimeException ) {
			$version = $this->record( $slug )['version'] ?? null;
			return is_string( $version ) ? $version : null;
		}
	}

	/**
	 * Refuse a slug that is not a slug of this type, or is reserved.
	 *
	 * @param string $slug
	 * @return void
	 * @throws RuntimeException
	 */
	protected function assert_slug( string $slug ) : void {
		if ( 1 !== preg_match( $this->slug_pattern(), $slug ) ) {
			throw $this->fail( sprintf( '"%s" is not a %s slug: use lowercase letters, digits and hyphens.', $slug, static::NOUN ) );
		}

		$reserved = $this->reserved_message( $slug );

		if ( null !== $reserved ) {
			throw $this->fail( $reserved );
		}
	}

	/**
	 * The contents of an uploaded text file, or null when none was uploaded.
	 *
	 * @param UploadedFile|null $file
	 * @return string|null
	 * @throws RuntimeException When the file was uploaded but cannot be read.
	 */
	private function uploaded_text( ?UploadedFile $file ) : ?string {
		if ( null === $file || ! $file->exists() || UPLOAD_ERR_NO_FILE === $file->get_error_code() ) {
			return null;
		}

		$contents = $file->is_uploaded_file() ? $this->fs->get_contents( (string) $file->get_tmp_path() ) : false;

		if ( ! is_string( $contents ) ) {
			throw $this->fail( $file->get_error_message() );
		}

		return $contents;
	}

	/**
	 * Run a step holding the lock, so one install, update or removal of this type runs at a time.
	 *
	 * @template T
	 * @param callable(): T $step
	 * @return T
	 * @throws RuntimeException When another process holds the lock.
	 */
	protected function locked( callable $step ) : mixed {
		if ( null !== $this->lock ) {
			return $step();
		}

		$this->make_dir( $this->work_path( '' ) );

		$handle = @fopen( $this->work_path( 'lock' ), 'c' );

		if ( false === $handle ) {
			throw $this->fail( sprintf( 'Could not create the %s lock; check the permissions of the storage folder.', static::NOUN ) );
		}

		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			throw $this->fail( sprintf( 'Another %s is being installed, updated or removed; try again in a moment.', static::NOUN ) );
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
		return $this->modules->installed_dir() . '/' . self::WORK_DIR . ( '' === $name ? '' : '/' . $name );
	}

	/**
	 * Create a folder or fail.
	 *
	 * @param string $dir
	 * @return void
	 * @throws RuntimeException
	 */
	private function make_dir( string $dir ) : void {
		if ( ! $this->fs->is_dir( $dir ) && ! $this->fs->mkdir( $dir ) ) {
			throw $this->fail( sprintf( 'Could not create %s; check the permissions of the storage folder.', $dir ) );
		}
	}

	/**
	 * Remove a file or folder if it exists.
	 *
	 * @param string $path
	 * @return void
	 */
	private function delete_path( string $path ) : void {
		if ( $this->fs->exists( $path ) ) {
			$this->fs->delete( $path, true );
		}
	}
}
