<?php
/**
 * App storage adapter contract file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps\Storage
 */

namespace SmartLicenseServer\HostedApps\Storage;

use SmartLicenseServer\Exceptions\StorageException;

/**
 * Keeps the files of hosted apps and answers questions about them.
 *
 * Philosophy: the adapter keeps an app's files wherever it chooses and
 * answers questions about them; the app type decides what those files mean.
 * A local directory, SFTP, an object store or a database all implement this
 * one contract, and the AppStorage facade proxies to whichever the site
 * admin chose. One adapter may serve one or more app types.
 *
 * What an app has
 * ---------------
 * See AppFile for the kinds and the rules for types, slugs and names:
 *  - one package (the latest distributable);
 *  - one manifest (app.json);
 *  - any number of artifacts, assets and documents, each named.
 * The adapter stores names exactly as given. Which names are allowed (icon
 * names, screenshot numbering, artifact naming) is the app type's business
 * and is checked before any method here is called.
 *
 * App lifecycle
 * -------------
 * An app exists in storage from the first put_package() until it is
 * trashed or purged. Every other put_*() method requires the app to exist.
 * A trashed app keeps its files, is not visible to has_app() or any file
 * method, and comes back unchanged with restore_app().
 *
 * Uploads
 * -------
 * put_package(), put_artifact() and put_asset() take the absolute path of a
 * local file (normally an upload's temporary file). It is validated by the
 * app type before the call. On success the local file no longer exists; on
 * failure it is left where it was.
 *
 * Serving
 * -------
 * A file is served by asking, in order: temporary_url() for a direct link,
 * local_path() for a path the web server can send, and read_stream() as the
 * fallback that always works. read_stream() never loads a whole file into
 * memory.
 *
 * Errors
 * ------
 * Every method either completes or throws a StorageException carrying one
 * of its codes; no method returns a failure value. A type the adapter does
 * not serve, or a type, slug or name that breaks the rules, throws
 * INVALID_PATH before anything else is checked.
 *
 * Order
 * -----
 * Every list is sorted by name in byte order.
 *
 * File information
 * ----------------
 * stat() and the list methods describe a file as
 * array{name: string, size: int, mtime: int, mime_type: string}:
 *  - name: the file's name ("{slug}.zip" for the package, "app.json" for
 *    the manifest);
 *  - size: bytes;
 *  - mtime: last modification as a Unix timestamp;
 *  - mime_type: the detected media type, "application/octet-stream" when it
 *    cannot be detected.
 */
interface AppStorageAdapterInterface {

	/*
	|--------
	| Apps
	|--------
	*/

	/**
	 * Tell whether a live app exists in storage.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return bool True when the app exists and is not trashed.
	 * @throws StorageException INVALID_PATH.
	 */
	public function has_app( string $type, string $slug ) : bool;

	/**
	 * Move a live app to the trash, keeping its files.
	 *
	 * The time it was trashed is recorded for list_trashed().
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when no live app has
	 *                          the slug; ALREADY_EXISTS when a trashed app
	 *                          already has it (purge that first); IO_ERROR.
	 */
	public function trash_app( string $type, string $slug ) : void;

	/**
	 * Bring a trashed app back to life, files unchanged.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when no trashed app
	 *                          has the slug; ALREADY_EXISTS when a live app
	 *                          has it; IO_ERROR.
	 */
	public function restore_app( string $type, string $slug ) : void;

	/**
	 * Permanently delete an app, live and trashed copies alike.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when neither a live
	 *                          nor a trashed app has the slug; IO_ERROR.
	 */
	public function purge_app( string $type, string $slug ) : void;

	/**
	 * List the trashed apps of a type.
	 *
	 * @param string $type App type.
	 * @return list<array{slug: string, trashed_at: int}> In slug byte order;
	 *         trashed_at is the Unix time trash_app() recorded.
	 * @throws StorageException INVALID_PATH; IO_ERROR.
	 */
	public function list_trashed( string $type ) : array;

	/*
	|-----------
	| Package
	|-----------
	*/

	/**
	 * Store the main package, creating the app when it does not exist yet.
	 *
	 * Replaces an existing package. The previous package is kept until the
	 * new one is fully stored, so a failure leaves it as it was.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $local_path Absolute path of a regular local file.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when $local_path does
	 *                          not exist; WRONG_TYPE when it is not a regular
	 *                          file; IO_ERROR.
	 */
	public function put_package( string $type, string $slug, string $local_path ) : void;

	/*
	|------------
	| Manifest
	|------------
	*/

	/**
	 * Get the manifest.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return array<mixed>|null The decoded app.json, or null when the app
	 *                           has none or it does not decode to an array.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not
	 *                          exist; IO_ERROR.
	 */
	public function get_manifest( string $type, string $slug ) : ?array;

	/**
	 * Store the manifest, replacing any existing one.
	 *
	 * Stored as pretty-printed JSON with unescaped slashes.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param array<mixed> $manifest The manifest.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not
	 *                          exist; IO_ERROR, also when it cannot be
	 *                          encoded as JSON.
	 */
	public function put_manifest( string $type, string $slug, array $manifest ) : void;

	/*
	|-------------
	| Artifacts
	|-------------
	*/

	/**
	 * List an app's artifacts.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return list<array{name: string, size: int, mtime: int, mime_type: string}>
	 *         In name byte order; empty when there are none.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not
	 *                          exist; IO_ERROR.
	 */
	public function list_artifacts( string $type, string $slug ) : array;

	/**
	 * Store an artifact.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Artifact name.
	 * @param string $local_path Absolute path of a regular local file.
	 * @param bool $overwrite Optional. Whether an artifact with the same name
	 *                        is replaced. Default false.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or
	 *                          $local_path does not exist; WRONG_TYPE when
	 *                          $local_path is not a regular file;
	 *                          ALREADY_EXISTS when the name is taken and
	 *                          $overwrite is false; IO_ERROR.
	 */
	public function put_artifact( string $type, string $slug, string $name, string $local_path, bool $overwrite = false ) : void;

	/**
	 * Rename an artifact. Never overwrites; the same name is a no-op.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $from Current name.
	 * @param string $to New name.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or $from
	 *                          does not exist; ALREADY_EXISTS when $to does;
	 *                          IO_ERROR.
	 */
	public function rename_artifact( string $type, string $slug, string $from, string $to ) : void;

	/**
	 * Delete an artifact.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Artifact name.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          artifact does not exist; IO_ERROR.
	 */
	public function delete_artifact( string $type, string $slug, string $name ) : void;

	/*
	|----------
	| Assets
	|----------
	*/

	/**
	 * List an app's assets.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return list<array{name: string, size: int, mtime: int, mime_type: string}>
	 *         In name byte order; empty when there are none.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not
	 *                          exist; IO_ERROR.
	 */
	public function list_assets( string $type, string $slug ) : array;

	/**
	 * Store an asset, replacing one with the same name.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Asset name.
	 * @param string $local_path Absolute path of a regular local file.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or
	 *                          $local_path does not exist; WRONG_TYPE when
	 *                          $local_path is not a regular file; IO_ERROR.
	 */
	public function put_asset( string $type, string $slug, string $name, string $local_path ) : void;

	/**
	 * Delete an asset.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Asset name.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          asset does not exist; IO_ERROR.
	 */
	public function delete_asset( string $type, string $slug, string $name ) : void;

	/*
	|-------------
	| Documents
	|-------------
	*/

	/**
	 * Get a document's contents.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Document name, e.g. "readme.txt".
	 * @return string|null The contents, or null when the app has no such
	 *                     document.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not
	 *                          exist; IO_ERROR.
	 */
	public function get_document( string $type, string $slug, string $name ) : ?string;

	/**
	 * Store a document, replacing one with the same name.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Document name.
	 * @param string $contents The whole contents.
	 * @return void
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app does not
	 *                          exist; IO_ERROR.
	 */
	public function put_document( string $type, string $slug, string $name, string $contents ) : void;

	/*
	|-----------
	| Serving
	|-----------
	*/

	/**
	 * Describe a stored file.
	 *
	 * @param AppFile $file The file.
	 * @return array{name: string, size: int, mtime: int, mime_type: string}
	 * @throws StorageException INVALID_PATH for a type the adapter does not
	 *                          serve; NOT_FOUND when the app or the file
	 *                          does not exist; IO_ERROR.
	 */
	public function stat( AppFile $file ) : array;

	/**
	 * Open a stored file for reading, from an offset.
	 *
	 * Uses constant memory whatever the file's size. The stream starts at
	 * $offset (an offset at or past the end gives a stream at its end). It
	 * may continue past $offset + $length, so a caller that passes $length
	 * reads at most $length bytes from it. The caller owns the stream and
	 * closes it with fclose().
	 *
	 * @param AppFile $file The file.
	 * @param int $offset Optional. First byte to read. Default 0.
	 * @param int|null $length Optional. Bytes the caller will read; null for
	 *                         everything up to the end. Default null.
	 * @return resource A readable stream.
	 * @throws StorageException INVALID_PATH, also for a negative $offset or
	 *                          $length; NOT_FOUND when the app or the file
	 *                          does not exist; IO_ERROR.
	 */
	public function read_stream( AppFile $file, int $offset = 0, ?int $length = null ) : mixed;

	/**
	 * Get a local filesystem path for a stored file.
	 *
	 * For handing the file to the web server (X-Sendfile, X-Accel-Redirect)
	 * or to tools that need a path, such as ZipArchive. The path must only
	 * be read.
	 *
	 * @param AppFile $file The file.
	 * @return string|null The absolute path, or null when the adapter does
	 *                     not keep the file on this server.
	 * @throws StorageException INVALID_PATH; NOT_FOUND when the app or the
	 *                          file does not exist.
	 */
	public function local_path( AppFile $file ) : ?string;

	/**
	 * Get a time-limited direct URL for a stored file.
	 *
	 * @param AppFile $file The file.
	 * @param int $ttl Seconds the URL stays valid; at least 1.
	 * @return string|null The URL, or null when the adapter cannot give one.
	 * @throws StorageException INVALID_PATH, also for a $ttl below 1;
	 *                          NOT_FOUND when the app or the file does not
	 *                          exist.
	 */
	public function temporary_url( AppFile $file, int $ttl ) : ?string;

	/*
	|--------------------------
	| Repository browser
	|--------------------------
	*/

	/**
	 * List what the adapter stores, for the repository browser.
	 *
	 * Read-only, and shows the adapter's own layout:
	 *  - browse() lists the types it serves;
	 *  - browse( $type ) lists the live apps of that type;
	 *  - browse( $type, $slug ) lists every file the app has, at any depth.
	 * Each entry is array{name: string, path: string, is_dir: bool,
	 * size: int, mtime: int}: name is the last segment, path is relative to
	 * the level being listed ("assets/icon-128x128.png"), is_dir is true for
	 * types and apps, size is 0 for them.
	 *
	 * @param string $type Optional. App type. Default "".
	 * @param string $slug Optional. App slug; requires $type. Default "".
	 * @return list<array{name: string, path: string, is_dir: bool, size: int, mtime: int}>
	 *         In path byte order.
	 * @throws StorageException INVALID_PATH, also for a slug without a type;
	 *                          NOT_FOUND when the app does not exist;
	 *                          IO_ERROR.
	 */
	public function browse( string $type = '', string $slug = '' ) : array;
}