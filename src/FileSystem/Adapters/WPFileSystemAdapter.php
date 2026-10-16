<?php
/**
 * WordPress Filesystem Adapter
 *
 * Implements the FileSystemAdapterInterface for WordPress environments,
 * using the WP_Filesystem API as the underlying handler.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem\Adapters;

use SmartLicenseServer\Exceptions\FileSystemException;
use WP_Filesystem_Base;

/**
 * Adapter for WordPress Filesystem.
 *
 * Provides a safe wrapper around WordPress's WP_Filesystem API,
 * implementing the FileSystemAdapterInterface.
 */
class WPFileSystemAdapter implements FileSystemAdapterInterface {

	/**
	 * The WordPress filesystem handler.
	 *
	 * @var WP_Filesystem_Base
	 */
	protected WP_Filesystem_Base $fs;

	/**
	 * Constructor.
	 *
	 * Initializes the WordPress filesystem API.
	 *
	 * @param int $file_permission Default permission mode applied to files
	 *                              when no explicit mode is given.
	 * @param int $dir_permission Default permission mode applied to
	 *                             directories when no explicit mode is given.
	 * @throws FileSystemException If WP_Filesystem fails to initialize.
	 */
	public function __construct(
		private readonly int $file_permission = 0644,
		private readonly int $dir_permission = 0755,
	) {
		$this->init_fs();
	}

	/**
	 * Initialize WP_Filesystem.
	 *
	 * @return void
	 * @throws FileSystemException If filesystem initialization fails.
	 */
	protected function init_fs() : void {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				$file_path = SMLISER_ROOT . 'wp-admin/includes/file.php';
				if ( file_exists( $file_path ) ) {
					require_once $file_path;
				}
			}

			\ob_start();
			$initialized = function_exists( 'WP_Filesystem' ) && \WP_Filesystem();
			\ob_end_clean();

			if ( ! $initialized || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
				throw new FileSystemException( 'Failed to initialize WP_Filesystem credentials or method.' );
			}
		}

		$this->fs = $wp_filesystem;
	}

	/**
	 * Tell whether WP_Filesystem is writing to the local disk directly.
	 *
	 * The other methods (ftpext, ftpsockets, ssh2) cannot open a PHP
	 * stream on a file or create a symbolic link, so the methods that need
	 * either fall back to what the WP_Filesystem API offers.
	 *
	 * @return bool True for the 'direct' method, false otherwise.
	 */
	protected function is_direct() : bool {
		return 'direct' === $this->fs->method;
	}

	/**
	 * Resolve a put_contents()/write_stream() mode to the mode to apply.
	 *
	 * WP_Filesystem always chmods a written file and treats false as its
	 * own FS_CHMOD_FILE, so false cannot be passed through. It resolves to
	 * the mode PHP gives a newly created file instead (0666 masked by the
	 * process umask), which is what DirectFileSystem ends up with when it
	 * skips chmod.
	 *
	 * @param int|false|null $mode Null for the adapter's file permission,
	 *                              false for the new-file default, or an
	 *                              explicit mode.
	 * @return int The mode to apply.
	 */
	protected function resolve_file_mode( int|false|null $mode ) : int {
		if ( null === $mode ) {
			return $this->file_permission;
		}

		if ( false === $mode ) {
			return 0666 & ~umask();
		}

		return $mode;
	}

	/**
	 * List the names of a real directory's entries.
	 *
	 * @param string $dir Absolute path to a directory that is not a link.
	 * @return string[]|false Entry names, or false if it cannot be read.
	 */
	protected function list_names( string $dir ) : array|false {
		$entries = $this->fs->dirlist( $dir, true, false );

		if ( ! is_array( $entries ) ) {
			return false;
		}

		// Numeric names come back as integer keys.
		return array_map( 'strval', array_keys( $entries ) );
	}

	/**
	 * Check if a path is a directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if directory, false otherwise.
	 */
	public function is_dir( string $path ) : bool {
		return $this->fs->is_dir( $path );
	}

	/**
	 * Check if a path is a file.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if file, false otherwise.
	 */
	public function is_file( string $path ) : bool {
		return $this->fs->is_file( $path );
	}

	/**
	 * Check if a path exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if exists, false otherwise.
	 */
	public function exists( string $path ) : bool {
		if ( empty( $path ) ) {
			return false;
		}

		return $this->fs->exists( $path );
	}

	/**
	 * Check if a file/directory is readable.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if readable, false otherwise.
	 */
	public function is_readable( string $path ) : bool {
		return $this->fs->is_readable( $path );
	}

	/**
	 * Check if a file/directory is writable.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if writable, false otherwise.
	 */
	public function is_writable( string $path ) : bool {
		return $this->fs->is_writable( $path );
	}

	/**
	 * Check if a path uses a stream wrapper (e.g., s3:// or ftp://).
	 *
	 * @param mixed $thing The path or URL.
	 * @return bool True if stream wrapper, false otherwise.
	 */
	public function is_stream( mixed $thing ) : bool {
		if ( ! is_string( $thing ) ) {
			return false;
		}

		$scheme_separator = strpos( $thing, '://' );

		if ( false === $scheme_separator ) {
			return false;
		}

		$scheme = substr( $thing, 0, $scheme_separator );

		return in_array( $scheme, stream_get_wrappers(), true );
	}

	/**
	 * Get file contents.
	 *
	 * @param string $file Absolute path.
	 * @return string|false File contents or false on failure.
	 */
	public function get_contents( string $file ) : string|false {
		if ( empty( $file ) || ! $this->is_readable( $file ) ) {
			return false;
		}
		
		return $this->fs->get_contents( $file );
	}

	/**
	 * Write contents to a file.
	 *
	 * @param string $path Absolute path.
	 * @param string $contents Contents to write.
	 * @param int|false|null $mode Optional permissions. Null (default) applies
	 *                              the adapter's configured file permission;
	 *                              false gives the file the mode PHP gives a
	 *                              new file (WP_Filesystem cannot skip chmod);
	 *                              an explicit int applies that mode.
	 * @return bool True on success, false on failure.
	 */
	public function put_contents( string $path, string $contents, int|false|null $mode = null ) : bool {
		if ( empty( $path ) ) {
			return false;
		}

		$dir = dirname( $path );
		if ( ! $this->mkdir( $dir ) ) {
			return false;
		}

		return $this->fs->put_contents( $path, $contents, $this->resolve_file_mode( $mode ) );
	}

	/**
	 * Open a file for reading as a stream.
	 *
	 * The stream is opened in binary read mode and positioned at the start
	 * of the file. The caller owns it and must close it with fclose().
	 *
	 * With the 'direct' method the file itself is opened. The other methods
	 * cannot open a stream on a remote file, so its contents are fetched
	 * through WP_Filesystem into a php://temp stream, which PHP keeps in
	 * memory up to 2 MB and spills to a temporary file beyond that.
	 *
	 * @param string $path Absolute path to the file.
	 * @return resource|false A readable stream resource, or false on failure.
	 */
	public function read_stream( string $path ) : mixed {
		if ( ! $this->is_file( $path ) || ! $this->is_readable( $path ) ) {
			return false;
		}

		if ( $this->is_direct() ) {
			return @fopen( $path, 'rb' );
		}

		$contents = $this->fs->get_contents( $path );

		if ( false === $contents ) {
			return false;
		}

		$stream = @fopen( 'php://temp', 'w+b' );

		if ( false === $stream ) {
			return false;
		}

		if ( strlen( $contents ) !== fwrite( $stream, $contents ) ) {
			fclose( $stream );
			return false;
		}

		rewind( $stream );

		return $stream;
	}

	/**
	 * Write a stream's contents to a file.
	 *
	 * Reads $stream from its current position to its end and writes the
	 * bytes to $path, creating missing parent directories. An existing file
	 * is replaced only once every byte has been written, so a failed write
	 * leaves it as it was. $stream is not closed; the caller still owns it.
	 *
	 * With the 'direct' method the stream is copied to disk in chunks. The
	 * other methods can only write a string, so the stream is read into
	 * memory first.
	 *
	 * @param string $path Absolute path to the file.
	 * @param resource $stream A readable stream resource.
	 * @param int|false|null $mode Optional permissions. Null (default) applies
	 *                              the adapter's configured file permission;
	 *                              false gives the file the mode PHP gives a
	 *                              new file (WP_Filesystem cannot skip chmod);
	 *                              an explicit int applies that mode.
	 * @return bool True on success, false on failure.
	 */
	public function write_stream( string $path, mixed $stream, int|false|null $mode = null ) : bool {
		if ( '' === $path || ! is_resource( $stream ) || 'stream' !== get_resource_type( $stream ) ) {
			return false;
		}

		// move() deletes an existing destination recursively, so a directory
		// must never be the target.
		if ( $this->is_dir( $path ) ) {
			return false;
		}

		$dir = dirname( $path );
		if ( ! $this->mkdir( $dir ) ) {
			return false;
		}

		$mode = $this->resolve_file_mode( $mode );
		$tmp  = $path . '.tmp.' . uniqid( '', true );

		if ( ! $this->write_stream_to_temp( $tmp, $stream, $mode ) || ! $this->move( $tmp, $path, true ) ) {
			$this->fs->delete( $tmp, false, 'f' );
			return false;
		}

		$this->fs->chmod( $path, $mode );

		return true;
	}

	/**
	 * Write a stream's remaining contents to a new temporary file.
	 *
	 * @param string $tmp Absolute path of the temporary file; must not exist.
	 * @param resource $stream A readable stream resource.
	 * @param int $mode Permissions for the temporary file.
	 * @return bool True when every byte up to the end of $stream was written.
	 */
	protected function write_stream_to_temp( string $tmp, mixed $stream, int $mode ) : bool {
		if ( ! $this->is_direct() ) {
			$contents = stream_get_contents( $stream );

			// Stopping before the end means a read error cut the data short.
			if ( false === $contents || ! feof( $stream ) ) {
				return false;
			}

			return $this->fs->put_contents( $tmp, $contents, $mode );
		}

		$handle = @fopen( $tmp, 'xb' );

		if ( false === $handle ) {
			return false;
		}

		$copied = @stream_copy_to_stream( $stream, $handle );
		$closed = fclose( $handle );

		// Stopping before the end means a read error cut the data short.
		return false !== $copied && $closed && feof( $stream );
	}

	/**
	 * Delete a file or directory.
	 *
	 * A symbolic link is always removed as the link itself, whatever it
	 * points to and whatever $type says; its target is never touched.
	 *
	 * @param string $file Path to the file/directory.
	 * @param bool $recursive Optional. Delete recursively.
	 * @param string|false $type Optional. 'f' for file, 'd' for directory.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $file, bool $recursive = false, string|false $type = false ) : bool {
		if ( empty( $file ) ) {
			return false;
		}

		if ( @is_link( $file ) ) {
			return $this->fs->delete( $file, false, 'f' );
		}

		if ( false === $type ) {
			if ( $this->is_file( $file ) ) {
				$type = 'f';
			} elseif ( $this->is_dir( $file ) ) {
				$type = 'd';
			} else {
				return false;
			}
		}

		if ( 'f' === $type ) {
			return $this->fs->delete( $file, false, 'f' );
		}

		if ( 'd' === $type ) {
			return $this->rmdir( $file, $recursive );
		}

		return false;
	}

	/**
	 * Create a directory.
	 *
	 * Permissions are set explicitly after creation (WP_Filesystem chmods
	 * every directory it makes), so they do not depend on the process umask:
	 *  - the requested directory gets $chmod, or the adapter's directory
	 *    permission when $chmod is false;
	 *  - missing parent directories created on the way always get the
	 *    adapter's directory permission, never $chmod (as `mkdir -p -m`
	 *    does), so a strict mode for one folder never locks down the
	 *    folders above it.
	 *
	 * An existing directory is left untouched.
	 *
	 * @param string $path Absolute path.
	 * @param int|false $chmod Optional permissions for $path. False uses the
	 *                          adapter's configured directory permission.
	 * @param bool $recursive Optional. Create intermediate directories if true.
	 * @return bool True on success, false on failure.
	 */
	public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ) : bool {
		if ( $this->exists( $path ) ) {
			return true;
		}

		$mode = $chmod ?: $this->dir_permission;

		if ( $recursive ) {
			return $this->mkdir_recursive( $path, $mode );
		}

		return $this->fs->mkdir( $path, $mode );
	}

	/**
	 * Create a directory and any missing parents.
	 *
	 * @param string $path Absolute path.
	 * @param int $mode Permissions for $path itself. Parents created on the
	 *                  way get the adapter's directory permission.
	 * @return bool True on success, false on failure.
	 */
	protected function mkdir_recursive( string $path, int $mode ) : bool {
		$stream_wrapper = null;

		if ( $this->is_stream( $path ) ) {
			$parts          = explode( '://', $path, 2 );
			$stream_wrapper = $parts[0];
			$path           = $parts[1];
		}

		$sep  = \DIRECTORY_SEPARATOR;
		$path = str_replace( [ '/', '\\' ], $sep, $path );

		if ( null !== $stream_wrapper ) {
			$path = $stream_wrapper . '://' . $path;
		}

		$path = rtrim( $path, $sep );
		if ( empty( $path ) ) {
			$path = $sep;
		}

		$dest_parent = dirname( $path );
		while ( '.' !== $dest_parent && ! $this->is_dir( $dest_parent ) && dirname( $dest_parent ) !== $dest_parent ) {
			$dest_parent = dirname( $dest_parent );
		}

		$relative_parts = explode( $sep, ltrim( substr( $path, strlen( $dest_parent ) ), $sep ) );
		$last_index     = count( $relative_parts ) - 1;
		$current        = $dest_parent;

		foreach ( $relative_parts as $index => $part ) {
			$current .= $sep . $part;

			// A file in the way is skipped too; creating the next level under
			// it then fails, and the is_dir() check below reports that.
			if ( $this->exists( $current ) ) {
				continue;
			}

			$part_mode = $last_index === $index ? $mode : $this->dir_permission;

			if ( ! $this->fs->mkdir( $current, $part_mode ) ) {
				// Another process may have created it in the meantime.
				clearstatcache( true, $current );

				if ( ! $this->is_dir( $current ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Remove a directory.
	 *
	 * A symbolic link is not a directory here, even when it points to one,
	 * so it is refused; use delete() to remove the link itself. The walk is
	 * done here rather than by WP_Filesystem, whose recursive delete follows
	 * links to directories; links found inside the tree are removed as
	 * links, never followed.
	 *
	 * @param string $path Absolute path.
	 * @param bool $recursive Optional. Remove recursively.
	 * @return bool True on success, false on failure.
	 */
	public function rmdir( string $path, bool $recursive = false ) : bool {
		if ( @is_link( $path ) || ! $this->is_dir( $path ) ) {
			return false;
		}

		if ( $recursive ) {
			$names = $this->list_names( $path );

			if ( false === $names ) {
				return false;
			}

			foreach ( $names as $name ) {
				$this->delete( $path . \DIRECTORY_SEPARATOR . $name, true );
			}
		}

		return $this->fs->delete( $path, false, 'd' );
	}

	/**
	 * Copy a file or directory.
	 *
	 * A symbolic link is copied as a link with the same target (as `cp -P`
	 * does), never as the content it points to. No mode is applied to it.
	 * Only the 'direct' method can create links, so with the other methods
	 * copying a link fails.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @param bool $overwrite Optional. Overwrite if exists.
	 * @param int|false $mode Optional. Permissions.
	 * @return bool True on success, false on failure.
	 */
	public function copy( string $source, string $dest, bool $overwrite = false, int|false $mode = false ) : bool {
		$source_is_link = @is_link( $source );

		// exists() follows links, so a dangling link is checked on its own.
		if ( ! $source_is_link && ! $this->exists( $source ) ) {
			return false;
		}

		if ( @is_link( $dest ) || $this->exists( $dest ) ) {
			if ( ! $overwrite ) {
				return false;
			}

			if ( ! $this->delete( $dest, true ) ) {
				return false;
			}
		}

		$dest_dir = dirname( $dest );
		if ( ! $this->mkdir( $dest_dir ) ) {
			return false;
		}

		if ( $source_is_link ) {
			if ( ! $this->is_direct() ) {
				return false;
			}

			$target = @readlink( $source );

			return false !== $target && @symlink( $target, $dest );
		}

		if ( $this->is_file( $source ) ) {
			$file_mode = $mode ?: $this->file_permission;
			return $this->fs->copy( $source, $dest, false, $file_mode );
		}

		if ( ! $this->is_dir( $source ) ) {
			return false;
		}

		$dir_mode = $mode ?: $this->dir_permission;

		if ( ! $this->mkdir( $dest, $dir_mode, true ) ) {
			return false;
		}

		$names = $this->list_names( $source );

		if ( false === $names ) {
			return false;
		}

		foreach ( $names as $name ) {
			$from = $source . \DIRECTORY_SEPARATOR . $name;
			$to   = $dest . \DIRECTORY_SEPARATOR . $name;

			if ( ! $this->copy( $from, $to, false, $mode ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Move a file or directory.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @param bool $overwrite Optional. Overwrite if exists.
	 * @return bool True on success, false on failure.
	 */
	public function move( string $source, string $dest, bool $overwrite = false ) : bool {
		if ( ! $this->exists( $source ) ) {
			return false;
		}

		if ( $source === $dest ) {
			return true;
		}

		if ( $this->exists( $dest ) ) {
			if ( ! $overwrite ) {
				return false;
			}

			if ( ! $this->delete( $dest, true ) ) {
				return false;
			}
		}

		return $this->rename( $source, $dest );
	}

	/**
	 * Rename a file or directory.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @return bool True on success, false on failure.
	 */
	public function rename( string $source, string $dest ) : bool {
		if ( ! $this->exists( $source ) ) {
			return false;
		}

		if ( $source === $dest ) {
			return true;
		}

		$dest_dir = dirname( $dest );
		if ( ! $this->mkdir( $dest_dir ) ) {
			return false;
		}

		if ( ! $this->copy( $source, $dest, true ) ) {
			return false;
		}

		if ( ! $this->exists( $dest ) ) {
			return false;
		}

		if ( ! $this->delete( $source, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Change file/directory permissions.
	 *
	 * @param string $file Path.
	 * @param int|false $mode Optional. Permissions, applied to every item
	 *                         changed. False gives each item the adapter's
	 *                         configured permission for its own type: files
	 *                         the file permission, directories the directory
	 *                         permission.
	 * @param bool $recursive Optional. Apply recursively. The walk is done
	 *                        here rather than by WP_Filesystem, which follows
	 *                        links; symbolic links met during the walk are
	 *                        skipped (as `chmod -R` does), so nothing outside
	 *                        the tree is changed.
	 * @return bool True on success, false on failure.
	 */
	public function chmod( string $file, int|false $mode = false, bool $recursive = false ) : bool {
		$applied = $mode ?: $this->default_permission( $file );

		if ( null === $applied ) {
			return false;
		}

		$success = true;

		if ( $recursive && $this->is_dir( $file ) && ! @is_link( $file ) ) {
			foreach ( $this->list_names( $file ) ?: [] as $name ) {
				$child = $file . \DIRECTORY_SEPARATOR . $name;

				if ( @is_link( $child ) ) {
					continue;
				}

				// Pass the caller's $mode on unchanged, so a default stays per-type.
				$success = $this->chmod( $child, $mode, true ) && $success;
			}
		}

		return (bool) @$this->fs->chmod( $file, $applied, false ) && $success;
	}

	/**
	 * Get the adapter's configured permission for a path's type.
	 *
	 * @param string $path Absolute path.
	 * @return int|null The file permission for a file, the directory
	 *                  permission for a directory, or null when $path is
	 *                  neither.
	 */
	protected function default_permission( string $path ) : ?int {
		if ( $this->is_file( $path ) ) {
			return $this->file_permission;
		}

		if ( $this->is_dir( $path ) ) {
			return $this->dir_permission;
		}

		return null;
	}

	/**
	 * Change file/directory owner.
	 *
	 * @param string $file Path.
	 * @param string|int $owner Owner name or ID.
	 * @param bool $recursive Optional. Apply recursively. The walk is done
	 *                        here rather than by WP_Filesystem, which follows
	 *                        links. Symbolic links met during the walk are
	 *                        never followed: with the 'direct' method their
	 *                        own owner is changed (as `chown -R` does); the
	 *                        other methods cannot change a link's owner, so
	 *                        they are left as they are.
	 * @return bool True on success, false on failure.
	 */
	public function chown( string $file, string|int $owner, bool $recursive = false ) : bool {
		$success = true;

		if ( $recursive && $this->is_dir( $file ) && ! @is_link( $file ) ) {
			foreach ( $this->list_names( $file ) ?: [] as $name ) {
				$child = $file . \DIRECTORY_SEPARATOR . $name;

				if ( @is_link( $child ) ) {
					if ( $this->is_direct() ) {
						$success = @lchown( $child, $owner ) && $success;
					}

					continue;
				}

				$success = $this->chown( $child, $owner, true ) && $success;
			}
		}

		return (bool) $this->fs->chown( $file, $owner, false ) && $success;
	}

	/**
	 * Get file size.
	 *
	 * @param string $path Path.
	 * @return int|false Size in bytes or false on failure.
	 */
	public function filesize( string $path ) : int|false {
		return $this->fs->size( $path );
	}

	/**
	 * Get file modification time.
	 *
	 * @param string $path Path.
	 * @return int|false Unix timestamp or false on failure.
	 */
	public function filemtime( string $path ) : int|false {
		return $this->fs->mtime( $path );
	}

	/**
	 * Get file stat information.
	 *
	 * Symbolic links are followed, so a link reports what it points to.
	 * Keys:
	 *  - path:    $path exactly as given.
	 *  - exists:  always true; a missing path returns false instead.
	 *  - is_dir:  whether it is a directory.
	 *  - is_file: whether it is a regular file.
	 *  - size:    size in bytes for a file, 0 for a directory.
	 *  - mtime:   modification time as a Unix timestamp.
	 *  - perms:   permissions as a four-digit octal string, e.g. "0644".
	 *             With the 'direct' method the first digit carries the
	 *             setuid, setgid and sticky bits; the other methods only
	 *             report the last three digits, so it is always 0 there.
	 *
	 * @param string $path Absolute path.
	 * @return array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}|false
	 *         The stat array, or false when $path does not exist or its
	 *         information cannot be read.
	 */
	public function stat( string $path ) : array|false {
		if ( ! $this->exists( $path ) ) {
			return false;
		}

		$is_file = $this->is_file( $path );
		$size    = $is_file ? $this->fs->size( $path ) : 0;
		$mtime   = $this->fs->mtime( $path );
		$perms   = $this->read_perms( $path );

		// FTP reports a failed size or mtime lookup as -1.
		if ( ! is_int( $size ) || $size < 0 || ! is_int( $mtime ) || $mtime < 0 || null === $perms ) {
			return false;
		}

		return [
			'path'    => $path,
			'exists'  => true,
			'is_dir'  => $this->is_dir( $path ),
			'is_file' => $is_file,
			'size'    => $size,
			'mtime'   => $mtime,
			'perms'   => $perms,
		];
	}

	/**
	 * Read a path's permissions as a four-digit octal string.
	 *
	 * @param string $path Absolute path.
	 * @return string|null The permissions, e.g. "0644", or null when they
	 *                     cannot be read.
	 */
	protected function read_perms( string $path ) : ?string {
		if ( $this->is_direct() ) {
			$perms = @fileperms( $path );

			return false === $perms ? null : sprintf( '%04o', $perms & 07777 );
		}

		// WP_Filesystem returns the last three octal digits, or "0" on failure.
		$perms = (string) $this->fs->getchmod( $path );

		if ( '0' === $perms || ! ctype_digit( $perms ) ) {
			return null;
		}

		return str_pad( $perms, 4, '0', \STR_PAD_LEFT );
	}

	/**
	 * List the entries of a directory.
	 *
	 * Lists the direct children only (not recursive), without "." and "..",
	 * sorted by name in byte order so every adapter returns the same order.
	 * Each entry is the stat() array of that child, its path being $path
	 * joined to the entry name. Symbolic links are followed as stat()
	 * follows them, so a link to a directory makes $path listable and is
	 * listed as a directory. An entry whose information cannot be read,
	 * such as a link whose target is missing, is left out.
	 *
	 * @param string $path Absolute path to the directory.
	 * @return list<array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}>|false
	 *         The entries, an empty list for an empty directory, or false
	 *         when $path is not a directory or cannot be read.
	 */
	public function list_dir( string $path ) : array|false {
		// WP_Filesystem::dirlist() lists a file as itself, so check first.
		if ( ! $this->is_dir( $path ) ) {
			return false;
		}

		$names = $this->list_names( $path );

		if ( false === $names ) {
			return false;
		}

		sort( $names, \SORT_STRING );

		// rtrim() turns "/" into "", so the root still joins as "/name".
		$base    = rtrim( $path, '/\\' );
		$entries = [];

		foreach ( $names as $name ) {
			$stat = $this->stat( $base . \DIRECTORY_SEPARATOR . $name );

			if ( false !== $stat ) {
				$entries[] = $stat;
			}
		}

		return $entries;
	}
}