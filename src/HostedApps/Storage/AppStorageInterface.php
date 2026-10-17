<?php
/**
 * App storage contract file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps\Storage
 */

namespace SmartLicenseServer\HostedApps\Storage;

use SmartLicenseServer\Exceptions\StorageException;

/**
 * Stores the files of hosted apps of one app type.
 *
 * Philosophy: storage stores bytes under an app's identity and knows
 * nothing else. It has no idea what a package, an asset, an artifact or a
 * readme is; that layout belongs to the type logic above it. So any
 * backend (a local directory, an object store, a remote server) implements
 * this one interface and every app type works on it unchanged.
 *
 * Identity
 * --------
 * An instance serves exactly one app type. Inside it an app is addressed by
 * its slug, and a file inside the app by a key:
 *  - Slug: lowercase letters, digits and underscores in runs joined by
 *    single hyphens (the output of the app's sanitize_slug()), at most 255
 *    bytes. Pattern: ^[a-z0-9_]+(-[a-z0-9_]+)*$
 *  - Key: a relative path with "/" between segments, e.g. "assets/icon.png".
 *    Each segment is 1 to 255 bytes, is neither "." nor "..", and contains
 *    no "/", "\" or control character. A key never starts or ends with "/"
 *    and never contains "//". The empty key "" means the app itself; only
 *    the methods that say so accept it.
 * A slug or key that breaks these rules is refused, never repaired.
 *
 * Boundary
 * --------
 * No key ever reads or changes anything outside its app, whatever the
 * backend holds (symbolic links included).
 *
 * Directories
 * -----------
 * Directories exist only to hold files. Writing a file creates its missing
 * parent directories; a delete or rename that leaves a directory empty
 * removes it, up to but never including the app itself (a directory that
 * cannot be removed is left, and the operation still succeeds). An app
 * exists from create_app() until it is trashed or purged, even while it
 * holds no file.
 *
 * Errors
 * ------
 * Every method either completes or throws a StorageException carrying one
 * of its codes; none returns a failure value. Invalid slugs and keys throw
 * INVALID_PATH before anything else is checked, and every file method
 * throws NOT_FOUND (with an empty file in the error data) when the app does
 * not exist.
 *
 * Order
 * -----
 * Every list is sorted by name in byte order.
 */
interface AppStorageInterface {

	/*
	|------------
	| Identity
	|------------
	*/

	/**
	 * Get the app type this storage serves.
	 *
	 * @return string The normalized app type, e.g. "plugin".
	 */
	public function app_type() : string;

	/**
	 * Get the local directory that holds this type's apps.
	 *
	 * @return string|null The absolute path with a trailing "/", or null when
	 *                     the apps are not stored on the local filesystem.
	 */
	public function location() : ?string;

	/*
	|--------
	| Apps
	|--------
	*/

	/**
	 * List the slugs of all live apps.
	 *
	 * Trashed apps are not included.
	 *
	 * @return list<string> Slugs in byte order.
	 * @throws StorageException IO_ERROR when the storage cannot be read.
	 */
	public function list_apps() : array;

	/**
	 * Tell whether a live app exists.
	 *
	 * @param string $slug App slug.
	 * @return bool True when the app exists and is not trashed.
	 * @throws StorageException INVALID_PATH for an invalid slug.
	 */
	public function has_app( string $slug ) : bool;

	/**
	 * Create an empty app.
	 *
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH for an invalid slug;
	 *                          ALREADY_EXISTS when anything already occupies
	 *                          the slug among live apps; IO_ERROR when it
	 *                          cannot be created.
	 */
	public function create_app( string $slug ) : void;

	/**
	 * Move a live app to the trash.
	 *
	 * The app stops being live and its files are kept until restore_app()
	 * or purge_app(). The time it was trashed is recorded.
	 *
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH for an invalid slug; NOT_FOUND
	 *                          when no live app has the slug; ALREADY_EXISTS
	 *                          when a trashed app already has the slug (purge
	 *                          it first); IO_ERROR when it cannot be moved.
	 */
	public function trash_app( string $slug ) : void;

	/**
	 * Bring a trashed app back to life, files unchanged.
	 *
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH for an invalid slug; NOT_FOUND
	 *                          when no trashed app has the slug;
	 *                          ALREADY_EXISTS when a live app has the slug;
	 *                          IO_ERROR when it cannot be moved.
	 */
	public function restore_app( string $slug ) : void;

	/**
	 * Permanently delete an app, live and trashed copies alike.
	 *
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH for an invalid slug; NOT_FOUND
	 *                          when neither a live nor a trashed app has the
	 *                          slug; IO_ERROR when it cannot be deleted.
	 */
	public function purge_app( string $slug ) : void;

	/**
	 * List the trashed apps.
	 *
	 * @return list<array{slug: string, trashed_at: int}> In slug byte order;
	 *         trashed_at is the Unix timestamp recorded by trash_app().
	 * @throws StorageException IO_ERROR when the trash cannot be read.
	 */
	public function list_trashed() : array;

	/*
	|---------
	| Files
	|---------
	*/

	/**
	 * Get information about a file or directory in an app.
	 *
	 * Accepts the empty key, which describes the app itself.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key.
	 * @return array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}
	 *         path is the key as given; the other keys mean what they mean
	 *         in FileSystemAdapterInterface::stat().
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          key does not exist.
	 */
	public function stat( string $slug, string $file ) : array;

	/**
	 * List the entries of a directory in an app.
	 *
	 * Direct children only. Accepts the empty key, which lists the app's
	 * top level. Each entry's path is its key, e.g. "assets/icon.png". An
	 * entry is left out when its name cannot be a key segment, when it
	 * leads outside the app, or when its information cannot be read.
	 *
	 * @param string $slug App slug.
	 * @param string $dir Key of the directory. Default "", the app itself.
	 * @return list<array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}>
	 *         Entries in byte order; empty for an empty app.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          directory does not exist; WRONG_TYPE when the
	 *                          key is a file; IO_ERROR when it cannot be read.
	 */
	public function list( string $slug, string $dir = '' ) : array;

	/**
	 * Read a file's contents.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key of the file.
	 * @return string The whole contents.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          file does not exist; WRONG_TYPE when the key
	 *                          is a directory; IO_ERROR when it cannot be read.
	 */
	public function read( string $slug, string $file ) : string;

	/**
	 * Open a file for reading as a stream.
	 *
	 * The stream is positioned at the start of the file. The caller owns it
	 * and must close it with fclose().
	 *
	 * @param string $slug App slug.
	 * @param string $file Key of the file.
	 * @return resource A readable stream.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          file does not exist; WRONG_TYPE when the key
	 *                          is a directory; IO_ERROR when it cannot be
	 *                          opened.
	 */
	public function read_stream( string $slug, string $file ) : mixed;

	/**
	 * Write a file, replacing it when it exists.
	 *
	 * An existing file is replaced only once every byte has been written,
	 * so a failed write leaves it as it was.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key of the file.
	 * @param string $contents The whole contents.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not
	 *                          exist; WRONG_TYPE when the key, or one of its
	 *                          parents, is not a file where it must be;
	 *                          IO_ERROR when it cannot be written.
	 */
	public function write( string $slug, string $file, string $contents ) : void;

	/**
	 * Write a stream's contents to a file, replacing it when it exists.
	 *
	 * Reads $stream from its current position to its end. An existing file
	 * is replaced only once every byte has been written. $stream is not
	 * closed; the caller still owns it.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key of the file.
	 * @param resource $stream A readable stream.
	 * @return void
	 * @throws StorageException INVALID_PATH, also when $stream is not a
	 *                          readable stream; NOT_FOUND when the app does
	 *                          not exist; WRONG_TYPE when the key, or one of
	 *                          its parents, is not what it must be; IO_ERROR
	 *                          when it cannot be written or the stream fails.
	 */
	public function write_stream( string $slug, string $file, mixed $stream ) : void;

	/**
	 * Move a local file into an app, replacing the key when it is a file.
	 *
	 * For files already on this server, such as an upload's temporary file.
	 * On success $local_path no longer exists; on failure it is left where
	 * it was.
	 *
	 * @param string $slug App slug.
	 * @param string $file Key to store it under.
	 * @param string $local_path Absolute path of a regular local file.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or
	 *                          $local_path does not exist; WRONG_TYPE when
	 *                          $local_path is not a regular file, or the key
	 *                          or one of its parents is not what it must be;
	 *                          IO_ERROR when it cannot be moved.
	 */
	public function import( string $slug, string $file, string $local_path ) : void;

	/**
	 * Rename or move a file or directory within an app.
	 *
	 * Never overwrites. Missing parents of $to are created; a directory $from
	 * leaves empty is removed.
	 *
	 * @param string $slug App slug.
	 * @param string $from Key of the file or directory; not "".
	 * @param string $to New key; not "".
	 * @return void
	 * @throws StorageException INVALID_PATH, also when $to is $from or lies
	 *                          inside it; NOT_FOUND when the app or $from
	 *                          does not exist; ALREADY_EXISTS when $to
	 *                          exists; WRONG_TYPE when a parent of $to is a
	 *                          file; IO_ERROR when it cannot be moved.
	 */
	public function rename( string $slug, string $from, string $to ) : void;

	/**
	 * Delete a file or directory in an app.
	 *
	 * A directory it leaves empty is removed. To delete a whole app, use
	 * trash_app() or purge_app().
	 *
	 * @param string $slug App slug.
	 * @param string $file Key of the file or directory; not "".
	 * @param bool $recursive Optional. Whether a directory may still hold
	 *                        entries. Default false.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          key does not exist; NOT_EMPTY when the key is
	 *                          a directory with entries and $recursive is
	 *                          false; IO_ERROR when it cannot be deleted.
	 */
	public function delete( string $slug, string $file, bool $recursive = false ) : void;
}