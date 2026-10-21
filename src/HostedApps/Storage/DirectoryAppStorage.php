<?php
/**
 * Directory app storage adapter class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps\Storage
 */

namespace SmartLicenseServer\HostedApps\Storage;

use InvalidArgumentException;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;

/**
 * Keeps hosted apps in a local directory, through the FileSystem API.
 *
 * Layout under the root (the same layout the Repository class has always
 * used, so existing installations need no migration):
 *
 *     {root}/{type dir}/{slug}/{slug}.zip      package
 *     {root}/{type dir}/{slug}/app.json        manifest
 *     {root}/{type dir}/{slug}/artifacts/{name} artifacts
 *     {root}/{type dir}/{slug}/assets/{name}    assets
 *     {root}/{type dir}/{slug}/{name}           documents
 *     {root}/.trash/{type dir}/{slug}/          trashed app, plus
 *                                               TRASH_METADATA_FILE
 *
 * Each served type maps to one directory name, given at construction (see
 * CORE_TYPE_DIRS). A document may not be named "app.json", "{slug}.zip",
 * "artifacts" or "assets", so the kinds never collide.
 *
 * Links: the boundary is each app's own directory as the operating system
 * resolves it. The app directory may itself be a symbolic link (an app kept
 * on another disk); a file or directory inside it that resolves outside it,
 * or a broken link, makes the file INVALID_PATH. The FileSystem API has no
 * link or path-resolution methods, so these checks use PHP's is_link() and
 * realpath() directly.
 *
 * Replacing: a stored file is replaced in three moves: the new file is
 * staged next to the target under a dot-name, the old file is moved aside,
 * the staged file takes its place. Any failure moves everything back, so
 * the old file and the caller's local file both survive. Staging names
 * start with "." and never appear in any listing.
 */
class DirectoryAppStorage implements AppStorageAdapterInterface {

	/**
	 * Directory names of the core app types, matching the
	 * SMLISER_*_REPO_DIR constants.
	 *
	 * @var array<string, string>
	 */
	public const CORE_TYPE_DIRS = [
		'wpplugin' => 'wp-plugins',
		'wptheme'  => 'wp-themes',
		'software' => 'software',
	];

	/**
	 * Name of the directory holding trashed apps, under the root.
	 *
	 * @var string
	 */
	public const TRASH_DIR = '.trash';

	/**
	 * Name of the file recording when an app was trashed.
	 *
	 * @var string
	 */
	public const TRASH_METADATA_FILE = '.smliser_meta';

	/**
	 * File name of the manifest.
	 *
	 * @var string
	 */
	public const MANIFEST_FILE = 'app.json';

	/**
	 * Directory name of the artifacts.
	 *
	 * @var string
	 */
	public const ARTIFACTS_DIR = 'artifacts';

	/**
	 * Directory name of the assets.
	 *
	 * @var string
	 */
	public const ASSETS_DIR = 'assets';

	/**
	 * Media type used when none can be detected.
	 *
	 * @var string
	 */
	protected const UNKNOWN_MIME = 'application/octet-stream';

	/**
	 * Absolute root directory, with a trailing "/".
	 *
	 * @var string
	 */
	protected readonly string $root;

	/**
	 * Directory name of each served type.
	 *
	 * @var array<string, string>
	 */
	protected readonly array $type_dirs;

	/**
	 * Construct the adapter.
	 *
	 * Neither the root nor the type directories have to exist yet; they
	 * are created when the first app of a type is stored.
	 *
	 * @param FileSystem $fs The app's FileSystem API.
	 * @param string $root Absolute directory holding the type directories,
	 *                     normally SMLISER_REPO_DIR.
	 * @param array<string, string> $type_dirs Directory name of each type
	 *                                         this adapter serves, e.g.
	 *                                         CORE_TYPE_DIRS.
	 * @throws InvalidArgumentException When the root is empty, no type is
	 *                                  given, a type or directory name is
	 *                                  invalid, or two types share a
	 *                                  directory.
	 */
	public function __construct(
		protected readonly FileSystem $fs,
		string $root,
		array $type_dirs,
	) {
		if ( '' === trim( $root, " /\\" ) ) {
			throw new InvalidArgumentException( 'The root directory must not be empty.' );
		}

		if ( [] === $type_dirs ) {
			throw new InvalidArgumentException( 'At least one app type must be served.' );
		}

		foreach ( $type_dirs as $type => $dir ) {
			if ( ! AppFile::is_identifier( (string) $type ) || ! AppFile::is_segment( $dir ) ) {
				throw new InvalidArgumentException( sprintf( 'Invalid type directory mapping "%s" => "%s".', $type, $dir ) );
			}
		}

		if ( count( array_unique( $type_dirs ) ) !== count( $type_dirs ) ) {
			throw new InvalidArgumentException( 'Two app types must not share a directory.' );
		}

		ksort( $type_dirs, \SORT_STRING );

		$this->root      = rtrim( $root, '/\\' ) . '/';
		$this->type_dirs = $type_dirs;
	}

	/*
	|--------
	| Apps
	|--------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function has_app( string $type, string $slug ) : bool {
		$this->check_app( $type, $slug );

		return $this->fs->is_dir( $this->app_dir( $type, $slug ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function trash_app( string $type, string $slug ) : void {
		$this->require_app( $type, $slug );

		$live    = $this->app_dir( $type, $slug );
		$trashed = $this->trashed_dir( $type, $slug );

		if ( $this->occupied( $trashed ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( 'A trashed %s already has the slug "%s"; purge it first.', $type, $slug ), $type, $slug );
		}

		if ( ! $this->fs->mkdir( dirname( $trashed ) ) || ! $this->fs->move( $live, $trashed ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( 'The %s "%s" could not be moved to the trash.', $type, $slug ), $type, $slug );
		}

		// Without its timestamp the app would be missing from list_trashed(),
		// so a failed write puts it back.
		if ( ! $this->fs->put_contents( $trashed . '/' . self::TRASH_METADATA_FILE, (string) time() ) ) {
			$this->fs->move( $trashed, $live );
			$this->fail( StorageException::IO_ERROR, sprintf( 'The trash time of %s "%s" could not be recorded.', $type, $slug ), $type, $slug );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function restore_app( string $type, string $slug ) : void {
		$this->check_app( $type, $slug );

		$live    = $this->app_dir( $type, $slug );
		$trashed = $this->trashed_dir( $type, $slug );

		if ( ! $this->fs->is_dir( $trashed ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( 'No trashed %s has the slug "%s".', $type, $slug ), $type, $slug );
		}

		if ( $this->occupied( $live ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( 'A live %s already has the slug "%s".', $type, $slug ), $type, $slug );
		}

		// The timestamp is removed first so a restored app never carries it;
		// it is written back if the move fails.
		$meta       = $trashed . '/' . self::TRASH_METADATA_FILE;
		$has_meta   = $this->occupied( $meta );
		$trashed_at = $has_meta ? $this->fs->get_contents( $meta ) : false;

		if ( $has_meta && ! $this->fs->delete( $meta ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( 'The trashed %s "%s" could not be restored.', $type, $slug ), $type, $slug );
		}

		if ( ! $this->fs->move( $trashed, $live ) ) {
			if ( false !== $trashed_at ) {
				$this->fs->put_contents( $meta, $trashed_at );
			}

			$this->fail( StorageException::IO_ERROR, sprintf( 'The trashed %s "%s" could not be restored.', $type, $slug ), $type, $slug );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function purge_app( string $type, string $slug ) : void {
		$this->check_app( $type, $slug );

		$dirs = array_filter(
			[ $this->app_dir( $type, $slug ), $this->trashed_dir( $type, $slug ) ],
			fn( string $dir ) : bool => $this->occupied( $dir )
		);

		if ( [] === $dirs ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( 'No live or trashed %s has the slug "%s".', $type, $slug ), $type, $slug );
		}

		foreach ( $dirs as $dir ) {
			if ( ! $this->fs->delete( $dir, true ) ) {
				$this->fail( StorageException::IO_ERROR, sprintf( 'The %s "%s" could not be deleted.', $type, $slug ), $type, $slug );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Moves the app's directory, then renames the package inside it. If
	 * the package cannot be renamed, the directory is moved back.
	 */
	public function rename_app( string $type, string $from, string $to ) : void {
		$this->check_app( $type, $to );

		if ( $from === $to ) {
			$this->fail( StorageException::INVALID_PATH, sprintf( 'The %s already has the slug "%s".', $type, $to ), $type, $from );
		}

		$this->require_app( $type, $from );

		$old_dir = $this->app_dir( $type, $from );
		$new_dir = $this->app_dir( $type, $to );

		if ( $this->occupied( $new_dir ) || $this->occupied( $this->trashed_dir( $type, $to ) ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( 'A live or trashed %s already has the slug "%s".', $type, $to ), $type, $to );
		}

		$has_package = $this->occupied( $old_dir . '/' . $from . '.zip' );

		// A document may not take the package's name, so the new package
		// name must be free inside the app too.
		if ( $has_package && $this->occupied( $old_dir . '/' . $to . '.zip' ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( 'The %s "%s" holds a file named "%s.zip".', $type, $from, $to ), $type, $from );
		}

		if ( ! $this->fs->move( $old_dir, $new_dir ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( 'The %s "%s" could not be renamed to "%s".', $type, $from, $to ), $type, $from );
		}

		if ( $has_package && ! $this->fs->move( $new_dir . '/' . $from . '.zip', $new_dir . '/' . $to . '.zip' ) ) {
			$this->fs->move( $new_dir, $old_dir );
			$this->fail( StorageException::IO_ERROR, sprintf( 'The package of %s "%s" could not be renamed to "%s.zip".', $type, $from, $to ), $type, $from );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_trashed( string $type ) : array {
		$this->check_type( $type );

		$trashed = [];

		foreach ( $this->list_slugs( $this->trash_type_dir( $type ) ) as $slug ) {
			$time = $this->fs->get_contents( $this->trashed_dir( $type, $slug ) . '/' . self::TRASH_METADATA_FILE );

			if ( false === $time || ! ctype_digit( trim( $time ) ) ) {
				continue;
			}

			$trashed[] = [
				'slug'       => $slug,
				'trashed_at' => (int) trim( $time ),
			];
		}

		return $trashed;
	}

	/*
	|-----------
	| Package
	|-----------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function put_package( string $type, string $slug, string $local_path ) : void {
		$file = AppFile::package( $type, $slug );
		$this->check_type( $type );
		$this->check_local_file( $file, $local_path );

		$app_dir = $this->app_dir( $type, $slug );
		$created = false;

		if ( ! $this->fs->is_dir( $app_dir ) ) {
			if ( $this->occupied( $app_dir ) || ! $this->fs->mkdir( $app_dir ) ) {
				$this->fail( StorageException::IO_ERROR, sprintf( 'The %s "%s" could not be created.', $type, $slug ), $type, $slug );
			}

			$created = true;
		}

		try {
			$this->store_local_file( $file, $local_path, true );
		} catch ( StorageException $e ) {
			// An app exists only once its first package is stored.
			if ( $created ) {
				$this->fs->delete( $app_dir, true );
			}

			throw $e;
		}
	}

	/*
	|------------
	| Manifest
	|------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function get_manifest( string $type, string $slug ) : ?array {
		$file = AppFile::manifest( $type, $slug );
		$path = $this->resolve( $file );

		if ( ! $this->fs->is_file( $path ) ) {
			return null;
		}

		$json = $this->fs->get_contents( $path );

		if ( false === $json ) {
			$this->fail_file( StorageException::IO_ERROR, 'could not be read', $file );
		}

		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_manifest( string $type, string $slug, array $manifest ) : void {
		$file = AppFile::manifest( $type, $slug );
		$path = $this->resolve( $file );
		$json = \smliser_safe_json_encode( $manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			$this->fail_file( StorageException::IO_ERROR, 'could not be encoded as JSON', $file );
		}

		if ( ! $this->fs->put_contents( $path, $json ) ) {
			$this->fail_file( StorageException::IO_ERROR, 'could not be written', $file );
		}
	}

	/*
	|-------------
	| Artifacts
	|-------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function list_artifacts( string $type, string $slug ) : array {
		return $this->list_files( $type, $slug, self::ARTIFACTS_DIR );
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_artifact( string $type, string $slug, string $name, string $local_path, bool $overwrite = false ) : void {
		$file = AppFile::artifact( $type, $slug, $name );
		$this->resolve( $file );
		$this->check_local_file( $file, $local_path );
		$this->store_local_file( $file, $local_path, $overwrite );
	}

	/**
	 * {@inheritDoc}
	 */
	public function rename_artifact( string $type, string $slug, string $from, string $to ) : void {
		$from_file = AppFile::artifact( $type, $slug, $from );
		$to_file   = AppFile::artifact( $type, $slug, $to );
		$from_path = $this->resolve_existing( $from_file );
		$to_path   = $this->resolve( $to_file );

		if ( $from === $to ) {
			return;
		}

		if ( $this->occupied( $to_path ) ) {
			$this->fail_file( StorageException::ALREADY_EXISTS, 'already exists', $to_file );
		}

		if ( ! $this->fs->move( $from_path, $to_path ) ) {
			$this->fail_file( StorageException::IO_ERROR, sprintf( 'could not be renamed to "%s"', $to ), $from_file );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_artifact( string $type, string $slug, string $name ) : void {
		$this->delete_file( AppFile::artifact( $type, $slug, $name ) );
	}

	/*
	|----------
	| Assets
	|----------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function list_assets( string $type, string $slug ) : array {
		return $this->list_files( $type, $slug, self::ASSETS_DIR );
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_asset( string $type, string $slug, string $name, string $local_path ) : void {
		$file = AppFile::asset( $type, $slug, $name );
		$this->resolve( $file );
		$this->check_local_file( $file, $local_path );
		$this->store_local_file( $file, $local_path, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_asset( string $type, string $slug, string $name ) : void {
		$this->delete_file( AppFile::asset( $type, $slug, $name ) );
	}

	/*
	|-------------
	| Documents
	|-------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function get_document( string $type, string $slug, string $name ) : ?string {
		$file = AppFile::document( $type, $slug, $name );
		$path = $this->resolve( $file );

		if ( ! $this->fs->is_file( $path ) ) {
			return null;
		}

		$contents = $this->fs->get_contents( $path );

		if ( false === $contents ) {
			$this->fail_file( StorageException::IO_ERROR, 'could not be read', $file );
		}

		return $contents;
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_document( string $type, string $slug, string $name, string $contents ) : void {
		$file = AppFile::document( $type, $slug, $name );
		$path = $this->resolve( $file );

		if ( $this->fs->is_dir( $path ) || ! $this->fs->put_contents( $path, $contents ) ) {
			$this->fail_file( StorageException::IO_ERROR, 'could not be written', $file );
		}
	}

	/*
	|-----------
	| Serving
	|-----------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function stat( AppFile $file ) : array {
		return $this->describe( $this->resolve_existing( $file ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function read_stream( AppFile $file, int $offset = 0, ?int $length = null ) : mixed {
		if ( $offset < 0 || ( null !== $length && $length < 0 ) ) {
			$this->fail_file( StorageException::INVALID_PATH, 'cannot be read from a negative offset or length', $file );
		}

		$stream = $this->fs->read_stream( $this->resolve_existing( $file ) );

		if ( false === $stream ) {
			$this->fail_file( StorageException::IO_ERROR, 'could not be opened', $file );
		}

		if ( $offset > 0 && 0 !== fseek( $stream, $offset ) ) {
			fclose( $stream );
			$this->fail_file( StorageException::IO_ERROR, 'could not be read from the requested offset', $file );
		}

		return $stream;
	}

	/**
	 * {@inheritDoc}
	 */
	public function local_path( AppFile $file ) : ?string {
		$path = $this->resolve_existing( $file );

		return (string) realpath( $path );
	}

	/**
	 * {@inheritDoc}
	 *
	 * A local directory has no URLs of its own, so this is always null once
	 * the file is found.
	 */
	public function temporary_url( AppFile $file, int $ttl ) : ?string {
		if ( $ttl < 1 ) {
			$this->fail_file( StorageException::INVALID_PATH, 'needs a URL lifetime of at least one second', $file );
		}

		$this->resolve_existing( $file );

		return null;
	}

	/*
	|--------------------------
	| Repository browser
	|--------------------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function browse( string $type = '', string $slug = '' ) : array {
		if ( '' === $type ) {
			if ( '' !== $slug ) {
				$this->fail( StorageException::INVALID_PATH, 'Browsing an app needs its type.', '', $slug );
			}

			$entries = [];

			foreach ( array_keys( $this->type_dirs ) as $served ) {
				$entries[] = $this->dir_entry( $served, $served, $this->type_dir( $served ) );
			}

			return $entries;
		}

		if ( '' === $slug ) {
			$this->check_type( $type );

			$entries = [];

			foreach ( $this->list_slugs( $this->type_dir( $type ) ) as $app ) {
				$entries[] = $this->dir_entry( $app, $app, $this->app_dir( $type, $app ) );
			}

			return $entries;
		}

		$this->require_app( $type, $slug );

		$app_dir  = $this->app_dir( $type, $slug );
		$app_real = (string) realpath( $app_dir );
		$entries  = [];

		$this->collect_files( $app_dir, '', $app_real, $entries );

		foreach ( $entries as &$entry ) {
			$entry['kind'] = $this->kind_of( $slug, $entry['path'] );
		}
		unset( $entry );

		usort( $entries, static fn( array $a, array $b ) : int => strcmp( $a['path'], $b['path'] ) );

		return $entries;
	}

	/*
	|-----------
	| Helpers
	|-----------
	*/

	/**
	 * Throw a StorageException.
	 *
	 * @param string $code A StorageException constant.
	 * @param string $message Human-readable message.
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name File name.
	 * @return never
	 * @throws StorageException Always.
	 */
	protected function fail( string $code, string $message, string $type = '', string $slug = '', string $name = '' ) : never {
		throw new StorageException( $code, $message, $type, $slug, $name );
	}

	/**
	 * Throw a StorageException about one file.
	 *
	 * @param string $code A StorageException constant.
	 * @param string $what What went wrong, completing "The {kind} ... ".
	 * @param AppFile $file The file.
	 * @return never
	 * @throws StorageException Always.
	 */
	protected function fail_file( string $code, string $what, AppFile $file ) : never {
		$label = '' === $file->name ? $file->kind : sprintf( '%s "%s"', $file->kind, $file->name );

		$this->fail( $code, sprintf( 'The %s of %s "%s" %s.', $label, $file->type, $file->slug, $what ), $file->type, $file->slug, $file->name );
	}

	/**
	 * Throw INVALID_PATH unless the type is valid and served here.
	 *
	 * @param string $type App type.
	 * @return void
	 * @throws StorageException INVALID_PATH.
	 */
	protected function check_type( string $type ) : void {
		AppFile::check_type( $type );

		if ( ! isset( $this->type_dirs[ $type ] ) ) {
			$this->fail( StorageException::INVALID_PATH, sprintf( 'This storage does not serve the app type "%s".', $type ), $type );
		}
	}

	/**
	 * Throw INVALID_PATH unless the type is served and the slug is valid.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH.
	 */
	protected function check_app( string $type, string $slug ) : void {
		AppFile::check_identity( $type, $slug );
		$this->check_type( $type );
	}

	/**
	 * Throw NOT_FOUND unless a live app exists.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND.
	 */
	protected function require_app( string $type, string $slug ) : void {
		if ( ! $this->has_app( $type, $slug ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( 'No live %s has the slug "%s".', $type, $slug ), $type, $slug );
		}
	}

	/**
	 * Get a type's directory.
	 *
	 * @param string $type A served type.
	 * @return string Absolute path, without a trailing "/".
	 */
	protected function type_dir( string $type ) : string {
		return $this->root . $this->type_dirs[ $type ];
	}

	/**
	 * Get a live app's directory.
	 *
	 * @param string $type A served type.
	 * @param string $slug A valid slug.
	 * @return string Absolute path, without a trailing "/".
	 */
	protected function app_dir( string $type, string $slug ) : string {
		return $this->type_dir( $type ) . '/' . $slug;
	}

	/**
	 * Get a trashed app's directory.
	 *
	 * @param string $type A served type.
	 * @param string $slug A valid slug.
	 * @return string Absolute path, without a trailing "/".
	 */
	protected function trashed_dir( string $type, string $slug ) : string {
		return $this->trash_type_dir( $type ) . '/' . $slug;
	}

	/**
	 * Get the directory holding a type's trashed apps.
	 *
	 * @param string $type A served type.
	 * @return string Absolute path, without a trailing "/".
	 */
	protected function trash_type_dir( string $type ) : string {
		return $this->root . self::TRASH_DIR . '/' . $this->type_dirs[ $type ];
	}

	/**
	 * Get the path a file is stored at, without checking it.
	 *
	 * @param AppFile $file The file.
	 * @return string Absolute path.
	 */
	protected function path_of( AppFile $file ) : string {
		$app_dir = $this->app_dir( $file->type, $file->slug );

		return match ( $file->kind ) {
			AppFile::PACKAGE  => $app_dir . '/' . $file->slug . '.zip',
			AppFile::MANIFEST => $app_dir . '/' . self::MANIFEST_FILE,
			AppFile::ARTIFACT => $app_dir . '/' . self::ARTIFACTS_DIR . '/' . $file->name,
			AppFile::ASSET    => $app_dir . '/' . self::ASSETS_DIR . '/' . $file->name,
			default           => $app_dir . '/' . $file->name,
		};
	}

	/**
	 * Check a file reference, require its app, and keep it inside the app.
	 *
	 * @param AppFile $file The file.
	 * @return string The file's absolute path; the file may not exist.
	 * @throws StorageException INVALID_PATH; NOT_FOUND for the app.
	 */
	protected function resolve( AppFile $file ) : string {
		$this->check_type( $file->type );

		if ( AppFile::DOCUMENT === $file->kind ) {
			$reserved = [ self::MANIFEST_FILE, $file->slug . '.zip', self::ARTIFACTS_DIR, self::ASSETS_DIR ];

			if ( in_array( $file->name, $reserved, true ) ) {
				$this->fail_file( StorageException::INVALID_PATH, 'uses a name reserved for another kind of file', $file );
			}
		}

		$this->require_app( $file->type, $file->slug );

		$app_dir = $this->app_dir( $file->type, $file->slug );
		$path    = $this->path_of( $file );

		clearstatcache( true );

		$app_real = realpath( $app_dir );
		$probe    = $path;

		// A path that does not exist yet is judged by its nearest existing
		// ancestor; the app directory exists, so the walk always ends. A
		// broken link on the way cannot be judged at all.
		while ( false === realpath( $probe ) && $probe !== $app_dir ) {
			if ( @is_link( $probe ) ) {
				$this->fail_file( StorageException::INVALID_PATH, 'is a broken link', $file );
			}

			$probe = dirname( $probe );
		}

		if ( false === $app_real || ! $this->is_inside( $probe, $app_real ) ) {
			$this->fail_file( StorageException::INVALID_PATH, 'leads outside the app', $file );
		}

		return $path;
	}

	/**
	 * Resolve a file that must exist as a regular file.
	 *
	 * @param AppFile $file The file.
	 * @return string The file's absolute path.
	 * @throws StorageException INVALID_PATH; NOT_FOUND for the app or file.
	 */
	protected function resolve_existing( AppFile $file ) : string {
		$path = $this->resolve( $file );

		if ( ! $this->fs->is_file( $path ) ) {
			$this->fail_file( StorageException::NOT_FOUND, 'does not exist', $file );
		}

		return $path;
	}

	/**
	 * Throw unless a local path is an existing regular file.
	 *
	 * @param AppFile $file The file it is meant for, for the error.
	 * @param string $local_path Absolute local path.
	 * @return void
	 * @throws StorageException NOT_FOUND; WRONG_TYPE.
	 */
	protected function check_local_file( AppFile $file, string $local_path ) : void {
		if ( '' === $local_path || ! $this->fs->exists( $local_path ) ) {
			$this->fail_file( StorageException::NOT_FOUND, 'cannot be stored: the local file does not exist', $file );
		}

		if ( @is_link( $local_path ) || ! $this->fs->is_file( $local_path ) ) {
			$this->fail_file( StorageException::WRONG_TYPE, 'cannot be stored: the local path is not a regular file', $file );
		}
	}

	/**
	 * Move a local file into place, keeping the old file until it is done.
	 *
	 * @param AppFile $file The resolved file to store.
	 * @param string $local_path Checked local file.
	 * @param bool $overwrite Whether an existing file is replaced.
	 * @return void
	 * @throws StorageException ALREADY_EXISTS; IO_ERROR.
	 */
	protected function store_local_file( AppFile $file, string $local_path, bool $overwrite ) : void {
		$target   = $this->path_of( $file );
		$replaces = $this->occupied( $target );

		if ( $replaces && ! $overwrite ) {
			$this->fail_file( StorageException::ALREADY_EXISTS, 'already exists', $file );
		}

		if ( $replaces && ! $this->fs->is_file( $target ) ) {
			$this->fail_file( StorageException::IO_ERROR, 'cannot replace something that is not a file', $file );
		}

		$dir    = dirname( $target );
		$suffix = bin2hex( random_bytes( 8 ) );
		$staged = $dir . '/.incoming-' . $suffix;
		$backup = $dir . '/.replaced-' . $suffix;

		if ( ! $this->fs->mkdir( $dir ) || ! $this->fs->rename( $local_path, $staged ) ) {
			$this->undo_stage( $staged, $local_path );
			$this->fail_file( StorageException::IO_ERROR, 'could not be stored', $file );
		}

		if ( $replaces && ! $this->fs->rename( $target, $backup ) ) {
			$this->undo_stage( $staged, $local_path );
			$this->fail_file( StorageException::IO_ERROR, 'could not be replaced', $file );
		}

		if ( ! $this->fs->rename( $staged, $target ) ) {
			if ( $replaces ) {
				$this->fs->rename( $backup, $target );
			}

			$this->undo_stage( $staged, $local_path );
			$this->fail_file( StorageException::IO_ERROR, 'could not be stored', $file );
		}

		// Uploads arrive with restrictive temporary-file permissions.
		$this->fs->chmod( $target );

		if ( $replaces ) {
			$this->fs->delete( $backup );
		}
	}

	/**
	 * Return a staged file to the caller's local path after a failure.
	 *
	 * @param string $staged Staging path.
	 * @param string $local_path The caller's local path.
	 * @return void
	 */
	protected function undo_stage( string $staged, string $local_path ) : void {
		if ( ! $this->occupied( $staged ) ) {
			return;
		}

		if ( $this->fs->exists( $local_path ) ) {
			$this->fs->delete( $staged );
			return;
		}

		$this->fs->rename( $staged, $local_path );
	}

	/**
	 * Delete one named file.
	 *
	 * @param AppFile $file The file.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND; IO_ERROR.
	 */
	protected function delete_file( AppFile $file ) : void {
		$path = $this->resolve_existing( $file );

		if ( ! $this->fs->delete( $path ) ) {
			$this->fail_file( StorageException::IO_ERROR, 'could not be deleted', $file );
		}
	}

	/**
	 * List the regular files of one of an app's subdirectories.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $subdir ARTIFACTS_DIR or ASSETS_DIR.
	 * @return list<array{name: string, size: int, mtime: int, mime_type: string}>
	 * @throws StorageException INVALID_PATH; NOT_FOUND; IO_ERROR.
	 */
	protected function list_files( string $type, string $slug, string $subdir ) : array {
		$this->check_app( $type, $slug );
		$this->require_app( $type, $slug );

		$dir = $this->app_dir( $type, $slug ) . '/' . $subdir;

		if ( ! $this->fs->is_dir( $dir ) ) {
			return [];
		}

		$app_real = (string) realpath( $this->app_dir( $type, $slug ) );

		if ( ! $this->is_inside( $dir, $app_real ) ) {
			$this->fail( StorageException::INVALID_PATH, sprintf( 'The %s directory of %s "%s" leads outside the app.', $subdir, $type, $slug ), $type, $slug );
		}

		$entries = $this->fs->list_dir( $dir );

		if ( false === $entries ) {
			$this->fail( StorageException::IO_ERROR, sprintf( 'The %s of %s "%s" could not be listed.', $subdir, $type, $slug ), $type, $slug );
		}

		$files = [];

		foreach ( $entries as $entry ) {
			if ( ! $entry['is_file'] || ! $this->is_listable( $entry['path'], $app_real ) ) {
				continue;
			}

			$files[] = $this->describe( $entry['path'] );
		}

		return $files;
	}

	/**
	 * Tell whether a directory entry may appear in a listing.
	 *
	 * @param string $path Absolute path of the entry.
	 * @param string $app_real The app directory's realpath().
	 * @return bool True when its name is a valid file name and it stays
	 *              inside the app.
	 */
	protected function is_listable( string $path, string $app_real ) : bool {
		return AppFile::is_segment( $this->name_of( $path ) ) && $this->is_inside( $path, $app_real );
	}

	/**
	 * Describe an existing regular file.
	 *
	 * @param string $path Absolute path.
	 * @return array{name: string, size: int, mtime: int, mime_type: string}
	 * @throws StorageException IO_ERROR when it cannot be read.
	 */
	protected function describe( string $path ) : array {
		$stat = $this->fs->stat( $path );

		if ( false === $stat ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be read.', $this->name_of( $path ) ) );
		}

		return [
			'name'      => $this->name_of( $path ),
			'size'      => $stat['size'],
			'mtime'     => $stat['mtime'],
			'mime_type' => FileSystemHelper::get_mime_type( $path ) ?? self::UNKNOWN_MIME,
		];
	}

	/**
	 * Build a browse() entry for a directory.
	 *
	 * @param string $name Entry name.
	 * @param string $path Entry path.
	 * @param string $dir Absolute directory, which may not exist.
	 * @return array{name: string, path: string, is_dir: bool, size: int, mtime: int, kind: string|null}
	 */
	protected function dir_entry( string $name, string $path, string $dir ) : array {
		$stat = $this->fs->stat( $dir );

		return [
			'name'   => $name,
			'path'   => $path,
			'is_dir' => true,
			'size'   => 0,
			'mtime'  => false === $stat ? 0 : $stat['mtime'],
			'kind'   => null,
		];
	}

	/**
	 * Tell which app file a path inside an app directory is.
	 *
	 * The reverse of the layout resolve() applies.
	 *
	 * @param string $slug App slug.
	 * @param string $path Path relative to the app directory.
	 * @return string|null An AppFile kind, or null when the path is not one
	 *                     of the app's files.
	 */
	protected function kind_of( string $slug, string $path ) : ?string {
		if ( $slug . '.zip' === $path ) {
			return AppFile::PACKAGE;
		}

		if ( self::MANIFEST_FILE === $path ) {
			return AppFile::MANIFEST;
		}

		$parts = explode( '/', $path );

		if ( 1 === count( $parts ) ) {
			return AppFile::is_segment( $path ) ? AppFile::DOCUMENT : null;
		}

		if ( 2 === count( $parts ) && AppFile::is_segment( $parts[1] ) ) {
			return match ( $parts[0] ) {
				self::ARTIFACTS_DIR => AppFile::ARTIFACT,
				self::ASSETS_DIR    => AppFile::ASSET,
				default             => null,
			};
		}

		return null;
	}

	/**
	 * Collect an app's files at any depth, without following links into
	 * directories.
	 *
	 * @param string $dir Absolute directory to walk.
	 * @param string $prefix Relative path of $dir inside the app ("" at the top).
	 * @param string $app_real The app directory's realpath().
	 * @param list<array{name: string, path: string, is_dir: bool, size: int, mtime: int, kind: string|null}> $entries Collected entries.
	 * @return void
	 * @throws StorageException IO_ERROR when a directory cannot be read.
	 */
	protected function collect_files( string $dir, string $prefix, string $app_real, array &$entries ) : void {
		$listed = $this->fs->list_dir( $dir );

		if ( false === $listed ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be listed.', '' === $prefix ? 'The app' : $prefix ) );
		}

		foreach ( $listed as $entry ) {
			if ( ! $this->is_listable( $entry['path'], $app_real ) ) {
				continue;
			}

			$name = $this->name_of( $entry['path'] );
			$path = '' === $prefix ? $name : $prefix . '/' . $name;

			if ( $entry['is_dir'] ) {
				if ( ! @is_link( $entry['path'] ) ) {
					$this->collect_files( $entry['path'], $path, $app_real, $entries );
				}

				continue;
			}

			if ( $entry['is_file'] ) {
				$entries[] = [
					'name'   => $name,
					'path'   => $path,
					'is_dir' => false,
					'size'   => $entry['size'],
					'mtime'  => $entry['mtime'],
					'kind'   => null,
				];
			}
		}
	}

	/**
	 * Get the last segment of a path.
	 *
	 * Unlike basename(), it does not depend on the locale, so multibyte
	 * names are never cut.
	 *
	 * @param string $path A path without a trailing separator.
	 * @return string The last segment.
	 */
	protected function name_of( string $path ) : string {
		$slash = strrpos( str_replace( '\\', '/', $path ), '/' );

		return false === $slash ? $path : substr( $path, $slash + 1 );
	}

	/**
	 * Tell whether anything occupies a path, a dangling link included.
	 *
	 * @param string $path Absolute path.
	 * @return bool True when the path holds a file, directory or link.
	 */
	protected function occupied( string $path ) : bool {
		return @is_link( $path ) || $this->fs->exists( $path );
	}

	/**
	 * Tell whether a path, as the operating system resolves it, is inside a directory.
	 *
	 * @param string $path Absolute path.
	 * @param string $dir_real The directory's realpath().
	 * @return bool True when the path is the directory or inside it.
	 */
	protected function is_inside( string $path, string $dir_real ) : bool {
		$real = realpath( $path );

		return false !== $real
			&& ( $real === $dir_real || str_starts_with( $real, $dir_real . \DIRECTORY_SEPARATOR ) );
	}

	/**
	 * List the directory names in a directory that are valid slugs.
	 *
	 * @param string $dir Absolute directory.
	 * @return list<string> Slugs in byte order; empty when $dir does not exist.
	 * @throws StorageException IO_ERROR when $dir exists but cannot be read.
	 */
	protected function list_slugs( string $dir ) : array {
		if ( ! $this->fs->is_dir( $dir ) ) {
			return [];
		}

		$entries = $this->fs->list_dir( $dir );

		if ( false === $entries ) {
			$this->fail( StorageException::IO_ERROR, 'An app directory could not be read.' );
		}

		$slugs = [];

		foreach ( $entries as $entry ) {
			$name = $this->name_of( $entry['path'] );

			if ( $entry['is_dir'] && AppFile::is_segment( $name ) ) {
				$slugs[] = $name;
			}
		}

		return $slugs;
	}
}
