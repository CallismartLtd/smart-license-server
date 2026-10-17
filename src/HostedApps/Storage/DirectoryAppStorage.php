<?php
/**
 * Directory app storage class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps\Storage
 */

namespace SmartLicenseServer\HostedApps\Storage;

use InvalidArgumentException;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Stores one app type's apps in a local directory, through the FileSystem API.
 *
 * Layout:
 *  - live app:    {root}/{slug}/{key}
 *  - trashed app: {trash_dir}/{slug}/{key}, plus a TRASH_METADATA_FILE
 *                 holding the Unix time it was trashed.
 *
 * The boundary is each app's own directory as the operating system resolves
 * it. The app directory may itself be a symbolic link (an admin may keep an
 * app on another disk); inside it, a key that resolves outside it is
 * refused. Reads follow links that stay inside; delete() and rename() act on
 * a link itself and never follow it, so a link that points outside can still
 * be removed. Purging an app whose directory is a link removes the link and
 * leaves the files it points to.
 *
 * The FileSystem API has no link or path-resolution methods, so link checks
 * use PHP's is_link() and realpath() directly.
 */
class DirectoryAppStorage implements AppStorageInterface {

	/**
	 * Name of the file recording when an app was trashed.
	 *
	 * Kept from the previous Repository class, so apps trashed before this
	 * class existed are still listed.
	 *
	 * @var string
	 */
	public const TRASH_METADATA_FILE = '.smliser_meta';

	/**
	 * Pattern a slug must match.
	 *
	 * @var string
	 */
	protected const SLUG_PATTERN = '/^[a-z0-9_]+(?:-[a-z0-9_]+)*$/';

	/**
	 * Longest slug or key segment, in bytes.
	 *
	 * @var int
	 */
	protected const MAX_SEGMENT_BYTES = 255;

	/**
	 * Absolute directory holding the live apps, with a trailing "/".
	 *
	 * @var string
	 */
	protected readonly string $root;

	/**
	 * Absolute directory holding the trashed apps, with a trailing "/".
	 *
	 * @var string
	 */
	protected readonly string $trash_dir;

	/**
	 * Construct the storage.
	 *
	 * Neither directory has to exist yet: create_app() and trash_app()
	 * create them when first needed.
	 *
	 * @param FileSystem $fs The app's FileSystem API.
	 * @param string $app_type The normalized app type this storage serves.
	 * @param string $root Absolute directory for live apps, e.g.
	 *                     SMLISER_PLUGINS_REPO_DIR.
	 * @param string $trash_dir Absolute directory for trashed apps of this
	 *                          type, e.g. SMLISER_TRASH_DIR . 'plugins/'.
	 * @throws InvalidArgumentException When an argument is empty, or both
	 *                                  directories are the same.
	 */
	public function __construct(
		protected readonly FileSystem $fs,
		protected readonly string $app_type,
		string $root,
		string $trash_dir,
	) {
		if ( '' === $app_type || '' === trim( $root, " /\\" ) || '' === trim( $trash_dir, " /\\" ) ) {
			throw new InvalidArgumentException( 'The app type, root and trash directory must not be empty.' );
		}

		$this->root      = rtrim( $root, '/\\' ) . '/';
		$this->trash_dir = rtrim( $trash_dir, '/\\' ) . '/';

		if ( $this->root === $this->trash_dir ) {
			throw new InvalidArgumentException( 'The root and trash directory must be different directories.' );
		}
	}

	/*
	|------------
	| Identity
	|------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function app_type() : string {
		return $this->app_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function location() : ?string {
		return $this->root;
	}

	/*
	|--------
	| Apps
	|--------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function list_apps() : array {
		return $this->list_slugs( $this->root );
	}

	/**
	 * {@inheritDoc}
	 */
	public function has_app( string $slug ) : bool {
		$this->check_slug( $slug );

		return $this->fs->is_dir( $this->app_dir( $slug ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_app( string $slug ) : void {
		$this->check_slug( $slug );

		$dir = $this->app_dir( $slug );

		if ( $this->occupied( $dir ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( 'The slug "%s" is already in use.', $slug ), $slug );
		}

		if ( ! $this->fs->mkdir( $dir ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( 'The app "%s" could not be created.', $slug ), $slug );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function trash_app( string $slug ) : void {
		$this->require_app( $slug );

		$trashed = $this->trashed_dir( $slug );

		if ( $this->occupied( $trashed ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( 'A trashed app already has the slug "%s"; purge it first.', $slug ), $slug );
		}

		if ( ! $this->fs->mkdir( rtrim( $this->trash_dir, '/' ) ) || ! $this->fs->move( $this->app_dir( $slug ), $trashed ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( 'The app "%s" could not be moved to the trash.', $slug ), $slug );
		}

		// Without its timestamp the app would be missing from list_trashed(),
		// so a failed write puts it back.
		if ( ! $this->fs->put_contents( $trashed . '/' . self::TRASH_METADATA_FILE, (string) time() ) ) {
			$this->fs->move( $trashed, $this->app_dir( $slug ) );
			$this->fail( StorageException::IO_ERROR, sprintf( 'The trash time of "%s" could not be recorded.', $slug ), $slug );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function restore_app( string $slug ) : void {
		$this->check_slug( $slug );

		$trashed = $this->trashed_dir( $slug );
		$live    = $this->app_dir( $slug );

		if ( ! $this->fs->is_dir( $trashed ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( 'No trashed app has the slug "%s".', $slug ), $slug );
		}

		if ( $this->occupied( $live ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( 'A live app already has the slug "%s".', $slug ), $slug );
		}

		// The timestamp is removed first so a restored app never carries it;
		// it is written back if the move fails.
		$meta       = $trashed . '/' . self::TRASH_METADATA_FILE;
		$has_meta   = $this->occupied( $meta );
		$trashed_at = $has_meta ? $this->fs->get_contents( $meta ) : false;

		if ( $has_meta && ! $this->fs->delete( $meta ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( 'The trashed app "%s" could not be restored.', $slug ), $slug );
		}

		if ( ! $this->fs->move( $trashed, $live ) ) {
			if ( false !== $trashed_at ) {
				$this->fs->put_contents( $meta, $trashed_at );
			}

			$this->fail( StorageException::IO_ERROR, sprintf( 'The trashed app "%s" could not be restored.', $slug ), $slug );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function purge_app( string $slug ) : void {
		$this->check_slug( $slug );

		$dirs = array_filter(
			[ $this->app_dir( $slug ), $this->trashed_dir( $slug ) ],
			fn( string $dir ) : bool => $this->occupied( $dir )
		);

		if ( [] === $dirs ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( 'No live or trashed app has the slug "%s".', $slug ), $slug );
		}

		foreach ( $dirs as $dir ) {
			if ( ! $this->fs->delete( $dir, true ) ) {
				$this->fail( StorageException::IO_ERROR, sprintf( 'The app "%s" could not be deleted.', $slug ), $slug );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_trashed() : array {
		$trashed = [];

		foreach ( $this->list_slugs( $this->trash_dir ) as $slug ) {
			$time = $this->fs->get_contents( $this->trashed_dir( $slug ) . '/' . self::TRASH_METADATA_FILE );

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
	|---------
	| Files
	|---------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function stat( string $slug, string $file ) : array {
		$path = $this->resolve( $slug, $file, true, true );
		$stat = $this->fs->stat( $path );

		if ( false === $stat ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( '"%s" does not exist.', $file ), $slug, $file );
		}

		$stat['path'] = $file;

		return $stat;
	}

	/**
	 * {@inheritDoc}
	 */
	public function list( string $slug, string $dir = '' ) : array {
		$path = $this->resolve( $slug, $dir, true, true );

		if ( ! $this->fs->exists( $path ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( '"%s" does not exist.', $dir ), $slug, $dir );
		}

		if ( ! $this->fs->is_dir( $path ) ) {
			$this->fail( StorageException::WRONG_TYPE, sprintf( '"%s" is a file, not a directory.', $dir ), $slug, $dir );
		}

		$entries = $this->fs->list_dir( $path );

		if ( false === $entries ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be read.', $dir ), $slug, $dir );
		}

		$app_real   = (string) realpath( $this->app_dir( $slug ) );
		$name_start = strlen( rtrim( $path, '/' ) ) + 1;
		$key_prefix = '' === $dir ? '' : $dir . '/';
		$listed     = [];

		foreach ( $entries as $entry ) {
			$name = substr( $entry['path'], $name_start );

			if ( ! $this->is_valid_segment( $name ) || ! $this->is_inside( $entry['path'], $app_real ) ) {
				continue;
			}

			$entry['path'] = $key_prefix . $name;
			$listed[]      = $entry;
		}

		return $listed;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read( string $slug, string $file ) : string {
		$path     = $this->resolve_existing_file( $slug, $file );
		$contents = $this->fs->get_contents( $path );

		if ( false === $contents ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be read.', $file ), $slug, $file );
		}

		return $contents;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read_stream( string $slug, string $file ) : mixed {
		$path   = $this->resolve_existing_file( $slug, $file );
		$stream = $this->fs->read_stream( $path );

		if ( false === $stream ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be opened.', $file ), $slug, $file );
		}

		return $stream;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( string $slug, string $file, string $contents ) : void {
		$path = $this->resolve_write_target( $slug, $file );

		if ( ! $this->fs->put_contents( $path, $contents ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be written.', $file ), $slug, $file );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function write_stream( string $slug, string $file, mixed $stream ) : void {
		if ( ! is_resource( $stream ) || 'stream' !== get_resource_type( $stream ) ) {
			$this->fail( StorageException::INVALID_PATH, 'A readable stream is required.', $slug, $file );
		}

		$path = $this->resolve_write_target( $slug, $file );

		if ( ! $this->fs->write_stream( $path, $stream ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be written.', $file ), $slug, $file );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function import( string $slug, string $file, string $local_path ) : void {
		$path = $this->resolve_write_target( $slug, $file );

		if ( ! $this->fs->exists( $local_path ) ) {
			$this->fail( StorageException::NOT_FOUND, 'The local file to import does not exist.', $slug, $file );
		}

		if ( @is_link( $local_path ) || ! $this->fs->is_file( $local_path ) ) {
			$this->fail( StorageException::WRONG_TYPE, 'Only a regular local file can be imported.', $slug, $file );
		}

		if ( ! $this->fs->rename( $local_path, $path ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be imported.', $file ), $slug, $file );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function rename( string $slug, string $from, string $to ) : void {
		$this->check_key( $slug, $to, false );
		$from_path = $this->resolve( $slug, $from, false, false );
		$to_path   = $this->resolve( $slug, $to, false, false );

		if ( $from === $to || str_starts_with( $to . '/', $from . '/' ) ) {
			$this->fail( StorageException::INVALID_PATH, sprintf( '"%s" cannot be moved to "%s".', $from, $to ), $slug, $from );
		}

		if ( ! $this->occupied( $from_path ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( '"%s" does not exist.', $from ), $slug, $from );
		}

		if ( $this->occupied( $to_path ) ) {
			$this->fail( StorageException::ALREADY_EXISTS, sprintf( '"%s" already exists.', $to ), $slug, $to );
		}

		$this->assert_parents_are_dirs( $slug, $to );

		if ( ! $this->fs->move( $from_path, $to_path ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be moved to "%s".', $from, $to ), $slug, $from );
		}

		$this->prune( $slug, $this->parent_key( $from ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $slug, string $file, bool $recursive = false ) : void {
		$path = $this->resolve( $slug, $file, false, false );

		if ( ! $this->occupied( $path ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( '"%s" does not exist.', $file ), $slug, $file );
		}

		if ( ! $recursive && ! @is_link( $path ) && $this->fs->is_dir( $path ) ) {
			$entries = $this->fs->list_dir( $path );

			if ( false === $entries ) {
				$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be read.', $file ), $slug, $file );
			}

			if ( [] !== $entries ) {
				$this->fail( StorageException::NOT_EMPTY, sprintf( '"%s" is not empty.', $file ), $slug, $file );
			}
		}

		if ( ! $this->fs->delete( $path, $recursive ) ) {
			$this->fail( StorageException::IO_ERROR, sprintf( '"%s" could not be deleted.', $file ), $slug, $file );
		}

		$this->prune( $slug, $this->parent_key( $file ) );
	}

	/*
	|-----------
	| Helpers
	|-----------
	*/

	/**
	 * Throw a StorageException for this storage.
	 *
	 * @param string $code A StorageException constant.
	 * @param string $message Human-readable message.
	 * @param string $slug The slug the operation was given.
	 * @param string $file The key the operation was given.
	 * @return never
	 * @throws StorageException Always.
	 */
	protected function fail( string $code, string $message, string $slug = '', string $file = '' ) : never {
		throw new StorageException( $code, $message, $this->app_type, $slug, $file );
	}

	/**
	 * Get a live app's directory.
	 *
	 * @param string $slug A valid slug.
	 * @return string Absolute path, without a trailing "/".
	 */
	protected function app_dir( string $slug ) : string {
		return $this->root . $slug;
	}

	/**
	 * Get a trashed app's directory.
	 *
	 * @param string $slug A valid slug.
	 * @return string Absolute path, without a trailing "/".
	 */
	protected function trashed_dir( string $slug ) : string {
		return $this->trash_dir . $slug;
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
	 * Throw INVALID_PATH unless a slug follows the key rules.
	 *
	 * @param string $slug The slug to check.
	 * @return void
	 * @throws StorageException INVALID_PATH.
	 */
	protected function check_slug( string $slug ) : void {
		if ( strlen( $slug ) > self::MAX_SEGMENT_BYTES || 1 !== preg_match( self::SLUG_PATTERN, $slug ) ) {
			$this->fail( StorageException::INVALID_PATH, sprintf( '"%s" is not a valid app slug.', $slug ), $slug );
		}
	}

	/**
	 * Throw INVALID_PATH unless a slug and a key follow the key rules.
	 *
	 * @param string $slug The slug to check.
	 * @param string $file The key to check.
	 * @param bool $allow_root Whether the empty key is accepted.
	 * @return void
	 * @throws StorageException INVALID_PATH.
	 */
	protected function check_key( string $slug, string $file, bool $allow_root ) : void {
		$this->check_slug( $slug );

		if ( '' === $file ) {
			if ( $allow_root ) {
				return;
			}

			$this->fail( StorageException::INVALID_PATH, 'This operation needs a file key; "" means the app itself.', $slug, $file );
		}

		foreach ( explode( '/', $file ) as $segment ) {
			if ( ! $this->is_valid_segment( $segment ) ) {
				$this->fail( StorageException::INVALID_PATH, sprintf( '"%s" is not a valid file key.', $file ), $slug, $file );
			}
		}
	}

	/**
	 * Tell whether a name can be one segment of a key.
	 *
	 * @param string $segment The name.
	 * @return bool True when valid.
	 */
	protected function is_valid_segment( string $segment ) : bool {
		return '' !== $segment
			&& '.' !== $segment
			&& '..' !== $segment
			&& strlen( $segment ) <= self::MAX_SEGMENT_BYTES
			&& 1 !== preg_match( '/[\\\\\/\x00-\x1F\x7F]/', $segment );
	}

	/**
	 * Validate a slug and key, require the app, and keep the key inside it.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key.
	 * @param bool $allow_root Whether the empty key is accepted.
	 * @param bool $follow_final Whether the last segment is followed when it
	 *                           is a link (reads and writes), or only its
	 *                           parent is checked (delete and rename, which
	 *                           act on the link itself).
	 * @return string The key's absolute path.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not exist.
	 */
	protected function resolve( string $slug, string $file, bool $allow_root, bool $follow_final ) : string {
		$this->check_key( $slug, $file, $allow_root );
		$this->require_app( $slug );

		$app_dir = $this->app_dir( $slug );
		$path    = '' === $file ? $app_dir : $app_dir . '/' . $file;

		clearstatcache( true );

		$app_real = realpath( $app_dir );
		$probe    = ( $follow_final || '' === $file ) ? $path : dirname( $path );

		// A path that does not exist yet is judged by its nearest existing
		// ancestor; the app directory exists, so the walk always ends.
		while ( false === realpath( $probe ) && $probe !== $app_dir ) {
			$probe = dirname( $probe );
		}

		if ( false === $app_real || ! $this->is_inside( $probe, $app_real ) ) {
			$this->fail( StorageException::INVALID_PATH, sprintf( '"%s" leads outside the app.', $file ), $slug, $file );
		}

		// A broken link cannot be checked, and writing through one creates
		// its target wherever it points (WP_Filesystem writes in place).
		if ( $follow_final && '' !== $file && @is_link( $path ) && false === realpath( $path ) ) {
			$this->fail( StorageException::INVALID_PATH, sprintf( '"%s" is a broken link.', $file ), $slug, $file );
		}

		return $path;
	}

	/**
	 * Resolve a key that must be an existing file, for reading.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key.
	 * @return string The file's absolute path.
	 * @throws StorageException INVALID_PATH; NOT_FOUND; WRONG_TYPE for a directory.
	 */
	protected function resolve_existing_file( string $slug, string $file ) : string {
		$path = $this->resolve( $slug, $file, false, true );

		if ( ! $this->fs->exists( $path ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( '"%s" does not exist.', $file ), $slug, $file );
		}

		if ( ! $this->fs->is_file( $path ) ) {
			$this->fail( StorageException::WRONG_TYPE, sprintf( '"%s" is a directory, not a file.', $file ), $slug, $file );
		}

		return $path;
	}

	/**
	 * Resolve a key that a file is about to be written to.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key.
	 * @return string The key's absolute path.
	 * @throws StorageException INVALID_PATH; NOT_FOUND for the app;
	 *                          WRONG_TYPE when the key is a directory or a
	 *                          parent is a file.
	 */
	protected function resolve_write_target( string $slug, string $file ) : string {
		$path = $this->resolve( $slug, $file, false, true );

		if ( $this->fs->is_dir( $path ) ) {
			$this->fail( StorageException::WRONG_TYPE, sprintf( '"%s" is a directory, not a file.', $file ), $slug, $file );
		}

		$this->assert_parents_are_dirs( $slug, $file );

		return $path;
	}

	/**
	 * Throw WRONG_TYPE when an existing parent of a key is not a directory.
	 *
	 * @param string $slug A valid slug of an existing app.
	 * @param string $file A valid, non-empty key.
	 * @return void
	 * @throws StorageException WRONG_TYPE.
	 */
	protected function assert_parents_are_dirs( string $slug, string $file ) : void {
		$parent = $this->app_dir( $slug );

		foreach ( array_slice( explode( '/', $file ), 0, -1 ) as $segment ) {
			$parent .= '/' . $segment;

			if ( $this->occupied( $parent ) && ! $this->fs->is_dir( $parent ) ) {
				$this->fail( StorageException::WRONG_TYPE, sprintf( 'A parent of "%s" is a file, not a directory.', $file ), $slug, $file );
			}
		}
	}

	/**
	 * Throw NOT_FOUND unless a live app exists.
	 *
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND.
	 */
	protected function require_app( string $slug ) : void {
		if ( ! $this->has_app( $slug ) ) {
			$this->fail( StorageException::NOT_FOUND, sprintf( 'No live app has the slug "%s".', $slug ), $slug );
		}
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
	 * Get a key's parent key.
	 *
	 * @param string $file A non-empty key.
	 * @return string The parent key; "" for a top-level key.
	 */
	protected function parent_key( string $file ) : string {
		$slash = strrpos( $file, '/' );

		return false === $slash ? '' : substr( $file, 0, $slash );
	}

	/**
	 * Remove empty directories from a key upwards, stopping at the app.
	 *
	 * Best effort: a directory that cannot be listed or removed ends it.
	 *
	 * @param string $slug A valid slug of an existing app.
	 * @param string $dir Key of the directory to start from.
	 * @return void
	 */
	protected function prune( string $slug, string $dir ) : void {
		while ( '' !== $dir ) {
			$path = $this->app_dir( $slug ) . '/' . $dir;

			if ( @is_link( $path ) || [] !== $this->fs->list_dir( $path ) || ! $this->fs->rmdir( $path ) ) {
				return;
			}

			$dir = $this->parent_key( $dir );
		}
	}

	/**
	 * List the directory names in a directory that are valid slugs.
	 *
	 * @param string $dir Absolute directory, with a trailing "/".
	 * @return list<string> Slugs in byte order; empty when $dir does not exist.
	 * @throws StorageException IO_ERROR when $dir exists but cannot be read.
	 */
	protected function list_slugs( string $dir ) : array {
		if ( ! $this->fs->is_dir( $dir ) ) {
			return [];
		}

		$entries = $this->fs->list_dir( $dir );

		if ( false === $entries ) {
			$this->fail( StorageException::IO_ERROR, 'The app directory could not be read.' );
		}

		$slugs = [];

		foreach ( $entries as $entry ) {
			$name = substr( $entry['path'], strlen( $dir ) );

			if ( $entry['is_dir'] && strlen( $name ) <= self::MAX_SEGMENT_BYTES && 1 === preg_match( self::SLUG_PATTERN, $name ) ) {
				$slugs[] = $name;
			}
		}

		return $slugs;
	}
}