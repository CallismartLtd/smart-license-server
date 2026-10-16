<?php
/**
 * Direct FileSystem Adapter
 *
 * Raw PHP file I/O implementation of FileSystemAdapterInterface.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem\Adapters;

class DirectFileSystem implements FileSystemAdapterInterface {

	/**
	 * Construct the adapter.
	 *
	 * @param int $file_permission Default permission mode applied to files
	 *                              when no explicit mode is given.
	 * @param int $dir_permission Default permission mode applied to
	 *                             directories when no explicit mode is given.
	 */
	public function __construct(
		protected readonly int $file_permission = 0644,
		protected readonly int $dir_permission = 0755,
	) {}

	/**
	 * Determine if a given path is a directory.
	 *
	 * @param string $path Absolute or relative path.
	 * @return bool True if the path is a directory, false otherwise.
	 */
	public function is_dir( string $path ) : bool {
		return @is_dir( $path );
	}

	/**
	 * Determine if a given path is a file.
	 *
	 * @param string $path Absolute or relative path.
	 * @return bool True if the path is a file, false otherwise.
	 */
	public function is_file( string $path ) : bool {
		return @is_file( $path );
	}

	/**
	 * Check if a file or directory exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if the path exists, false otherwise.
	 */
	public function exists( string $path ) : bool {
		return file_exists( $path );
	}

	/**
	 * Check if a file is readable.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if readable, false otherwise.
	 */
	public function is_readable( string $path ) : bool {
		return @is_readable( $path );
	}

	/**
	 * Check if a file is writable.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if writable, false otherwise.
	 */
	public function is_writable( string $path ) : bool {
		return @is_writable( $path );
	}

	/**
	 * Determine if the given input is a stream wrapper.
	 *
	 * @param mixed $thing Path or input to test.
	 * @return bool True if a stream wrapper, false otherwise.
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
	 * Retrieve the contents of a file.
	 *
	 * @param string $file Absolute path to the file.
	 * @return string|false File contents or false on failure.
	 */
	public function get_contents( string $file ) : string|false {
		if ( ! $this->is_readable( $file ) ) {
			return false;
		}

		return @file_get_contents( $file );
	}

	/**
	 * Write contents to a file.
	 *
	 * @param string $path Absolute path to the file.
	 * @param string $contents Data to write.
	 * @param int|false|null $mode Optional permissions. Null (default) applies
	 *                              the adapter's configured file permission;
	 *                              false skips chmod entirely; an explicit
	 *                              int applies that mode.
	 * @return bool True on success, false on failure.
	 */
	public function put_contents( string $path, string $contents, int|false|null $mode = null ) : bool {
		if ( '' === $path ) {
			return false;
		}

		$dir = dirname( $path );
		if ( ! $this->mkdir( $dir ) ) {
			return false;
		}

		$tmp = $path . '.tmp.' . uniqid( '', true );

		$bytes = @file_put_contents( $tmp, $contents, LOCK_EX );

		if ( false === $bytes || $bytes !== strlen( $contents ) ) {
			@unlink( $tmp );
			return false;
		}

		if ( ! $this->rename( $tmp, $path ) ) {
			@unlink( $tmp );
			return false;
		}

		if ( null === $mode ) {
			$mode = $this->file_permission;
		}

		if ( false !== $mode ) {
			$this->chmod( $path, $mode );
		}

		return true;
	}

	/**
	 * Open a file for reading as a stream.
	 *
	 * The stream is opened in binary read mode and positioned at the start
	 * of the file. The caller owns it and must close it with fclose().
	 *
	 * @param string $path Absolute path to the file.
	 * @return resource|false A readable stream resource, or false on failure.
	 */
	public function read_stream( string $path ) : mixed {
		if ( ! $this->is_file( $path ) || ! $this->is_readable( $path ) ) {
			return false;
		}

		return @fopen( $path, 'rb' );
	}

	/**
	 * Write a stream's contents to a file.
	 *
	 * Reads $stream from its current position to its end and writes the
	 * bytes to $path, creating missing parent directories. An existing file
	 * is replaced only once every byte has been written, so a failed write
	 * leaves it as it was. $stream is not closed; the caller still owns it.
	 *
	 * @param string $path Absolute path to the file.
	 * @param resource $stream A readable stream resource.
	 * @param int|false|null $mode Optional permissions. Null (default) applies
	 *                              the adapter's configured file permission;
	 *                              false skips chmod entirely; an explicit
	 *                              int applies that mode.
	 * @return bool True on success, false on failure.
	 */
	public function write_stream( string $path, mixed $stream, int|false|null $mode = null ) : bool {
		if ( '' === $path || ! is_resource( $stream ) || 'stream' !== get_resource_type( $stream ) ) {
			return false;
		}

		$dir = dirname( $path );
		if ( ! $this->mkdir( $dir ) ) {
			return false;
		}

		$tmp    = $path . '.tmp.' . uniqid( '', true );
		$handle = @fopen( $tmp, 'xb' );

		if ( false === $handle ) {
			return false;
		}

		$copied = @stream_copy_to_stream( $stream, $handle );
		$closed = fclose( $handle );

		// A copy that stopped before the end of $stream was cut short by a
		// read error, so the temp file is incomplete.
		if ( false === $copied || ! $closed || ! feof( $stream ) ) {
			@unlink( $tmp );
			return false;
		}

		if ( ! $this->rename( $tmp, $path ) ) {
			@unlink( $tmp );
			return false;
		}

		if ( null === $mode ) {
			$mode = $this->file_permission;
		}

		if ( false !== $mode ) {
			$this->chmod( $path, $mode );
		}

		return true;
	}

	/**
	 * Delete a file or directory.
	 *
	 * A symbolic link is always removed as the link itself, whatever it
	 * points to and whatever $type says; its target is never touched.
	 *
	 * @param string $file Path to the file or directory.
	 * @param bool $recursive Optional. Delete recursively if true.
	 * @param string|false $type Optional. 'f' for file, 'd' for directory, false for auto.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $file, bool $recursive = false, string|false $type = false ) : bool {
		if ( @is_link( $file ) ) {
			return @unlink( $file );
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
			return @unlink( $file );
		}

		if ( 'd' === $type ) {
			return $this->rmdir( $file, $recursive );
		}

		return false;
	}

	/**
	 * Create a directory.
	 *
	 * Permissions are set explicitly after creation, so they do not depend
	 * on the process umask:
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

		// Parents this call will create, nearest first.
		$parents = [];

		if ( $recursive ) {
			for ( $dir = dirname( $path ); $dir !== dirname( $dir ) && ! $this->exists( $dir ); $dir = dirname( $dir ) ) {
				$parents[] = $dir;
			}
		}

		if ( ! @mkdir( $path, $mode, $recursive ) ) {
			// Another process may have created it in the meantime.
			clearstatcache( true, $path );
			return $this->is_dir( $path );
		}

		// mkdir() applies the umask and gives parents the same mode as $path.
		foreach ( $parents as $parent ) {
			@chmod( $parent, $this->dir_permission );
		}

		@chmod( $path, $mode );

		return true;
	}

	/**
	 * Remove a directory.
	 *
	 * A symbolic link is not a directory here, even when it points to one,
	 * so it is refused; use delete() to remove the link itself. Links found
	 * inside the tree are removed as links, never followed.
	 *
	 * @param string $path Absolute path.
	 * @param bool $recursive Optional. Remove recursively if true.
	 * @return bool True on success, false on failure.
	 */
	public function rmdir( string $path, bool $recursive = false ) : bool {
		if ( @is_link( $path ) || ! $this->is_dir( $path ) ) {
			return false;
		}

		if ( ! $recursive ) {
			return @rmdir( $path );
		}

		$items = scandir( $path );

		if ( false === $items ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$this->delete( $path . DIRECTORY_SEPARATOR . $item, true );
		}

		return @rmdir( $path );
	}

	/**
	 * Copy a file or directory.
	 *
	 * Directories are copied recursively. Without an explicit mode, each
	 * copied item gets the adapter's default for its own type: files the
	 * file permission, directories the directory permission. An explicit
	 * mode is applied to every copied item, including a destination
	 * directory that already exists. Missing parent directories of $dest
	 * are created with the default directory permission.
	 *
	 * A symbolic link is copied as a link with the same target (as `cp -P`
	 * does), never as the content it points to. No mode is applied to it.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @param bool $overwrite Optional. Overwrite if true.
	 * @param int|false $mode Optional. Permissions; false for the per-type defaults.
	 * @return bool True when everything was copied, false if anything failed.
	 */
	public function copy( string $source, string $dest, bool $overwrite = false, int|false $mode = false ) : bool {
		$source_is_link = @is_link( $source );

		// exists() follows links, so a dangling link is checked on its own.
		if ( ! $source_is_link && ! $this->exists( $source ) ) {
			return false;
		}

		$dest_taken = @is_link( $dest ) || $this->exists( $dest );

		if ( $dest_taken && ! $overwrite ) {
			return false;
		}

		// Missing parents are not copied items, so they get the default
		// directory permission (as with `mkdir -p`), not $mode.
		$dest_dir = dirname( $dest );
		if ( ! $this->mkdir( $dest_dir ) ) {
			return false;
		}

		if ( $source_is_link ) {
			$target = @readlink( $source );

			if ( false === $target ) {
				return false;
			}

			if ( $dest_taken && ! $this->delete( $dest, true ) ) {
				return false;
			}

			return @symlink( $target, $dest );
		}

		if ( $this->is_file( $source ) ) {
			return @copy( $source, $dest )
				&& $this->exists( $dest )
				&& $this->chmod( $dest, $mode ?: $this->file_permission );
		}

		if ( $this->is_dir( $source ) ) {
			// mkdir() applies the directory permission when $mode is false.
			if ( ! $this->mkdir( $dest, $mode ) ) {
				return false;
			}

			// mkdir() returns early for an existing directory without applying
			// the mode, so an explicit mode is applied here as well.
			if ( false !== $mode && ! $this->chmod( $dest, $mode ) ) {
				return false;
			}

			$items = scandir( $source );
			if ( false === $items ) {
				return false;
			}

			$success = true;

			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}

				// Pass the caller's $mode on unchanged, so a default stays per-type.
				$success = $this->copy(
					$source . DIRECTORY_SEPARATOR . $item,
					$dest . DIRECTORY_SEPARATOR . $item,
					$overwrite,
					$mode
				) && $success;
			}

			return $success;
		}

		return false;
	}

	/**
	 * Move or rename a file or directory.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @param bool $overwrite Optional. Overwrite if true.
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
	 * Rename a file or directory (handles recursive destination directory creation).
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @return bool True on success, false on failure.
	 */
	public function rename( string $source, string $dest ) : bool {
		$dest_dir = dirname( $dest );

		if ( ! $this->mkdir( $dest_dir ) ) {
			return false;
		}

		if ( @rename( $source, $dest ) ) {
			return true;
		}

		// Cross-filesystem / mount point fallback
		if ( $this->is_file( $source ) ) {
			if ( $this->copy( $source, $dest, true ) ) {
				return $this->delete( $source );
			}
		}

		return false;
	}

	/**
	 * Change file permissions.
	 *
	 * @param string $file Path to file or directory.
	 * @param int|false $mode Optional. Permissions as octal number, applied
	 *                         to every item changed. False gives each item
	 *                         the adapter's configured permission for its own
	 *                         type: files the file permission, directories
	 *                         the directory permission.
	 * @param bool $recursive Optional. Change permissions recursively.
	 *                        Symbolic links met during the walk are skipped
	 *                        (as `chmod -R` does), so nothing outside the tree
	 *                        is changed.
	 * @return bool True on success, false on failure.
	 */
	public function chmod( string $file, int|false $mode = false, bool $recursive = false ) : bool {
		$applied = $mode ?: $this->default_permission( $file );

		if ( null === $applied ) {
			return false;
		}

		if ( ! $recursive ) {
			return @chmod( $file, $applied );
		}

		$success = true;

		if ( $this->is_dir( $file ) && ! @is_link( $file ) ) {
			$items = scandir( $file );
			if ( false !== $items ) {
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}

					$child = $file . DIRECTORY_SEPARATOR . $item;

					if ( @is_link( $child ) ) {
						continue;
					}

					// Pass the caller's $mode on unchanged, so a default stays per-type.
					$success = $this->chmod( $child, $mode, true ) && $success;
				}
			}
		}

		return @chmod( $file, $applied ) && $success;
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
	 * Change file owner.
	 *
	 * @param string $file Path to file or directory.
	 * @param string|int $owner Owner name or UID.
	 * @param bool $recursive Optional. Change owner recursively. Symbolic
	 *                        links met during the walk get their own owner
	 *                        changed (as `chown -R` does) and are never
	 *                        followed.
	 * @return bool True on success, false on failure.
	 */
	public function chown( string $file, string|int $owner, bool $recursive = false ) : bool {
		if ( ! $recursive ) {
			return @chown( $file, $owner );
		}

		$success = true;

		if ( $this->is_dir( $file ) && ! @is_link( $file ) ) {
			$items = scandir( $file );
			if ( false !== $items ) {
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}

					$child = $file . DIRECTORY_SEPARATOR . $item;

					if ( @is_link( $child ) ) {
						$success = @lchown( $child, $owner ) && $success;
						continue;
					}

					$success = $this->chown( $child, $owner, true ) && $success;
				}
			}
		}

		return @chown( $file, $owner ) && $success;
	}

	/**
	 * Get file size.
	 *
	 * @param string $path Absolute path.
	 * @return int|false File size in bytes, false on failure.
	 */
	public function filesize( string $path ) : int|false {
		return @filesize( $path );
	}

	/**
	 * Get file modification time.
	 *
	 * @param string $path Absolute path.
	 * @return int|false Unix timestamp, false on failure.
	 */
	public function filemtime( string $path ) : int|false {
		return @filemtime( $path );
	}

	/**
	 * Get file stat information.
	 *
	 * Symbolic links are followed, so a link reports what it points to.
	 * Every value comes from a single stat() call, so the array describes
	 * one consistent moment even while the file is being changed.
	 * Keys:
	 *  - path:    $path exactly as given.
	 *  - exists:  always true; a missing path returns false instead.
	 *  - is_dir:  whether it is a directory.
	 *  - is_file: whether it is a regular file.
	 *  - size:    size in bytes for a file, 0 for a directory.
	 *  - mtime:   modification time as a Unix timestamp.
	 *  - perms:   permissions as a four-digit octal string, e.g. "0644".
	 *
	 * @param string $path Absolute path.
	 * @return array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}|false
	 *         The stat array, or false when $path does not exist or its
	 *         information cannot be read.
	 */
	public function stat( string $path ) : array|false {
		if ( '' === $path ) {
			return false;
		}

		$info = @stat( $path );

		if ( false === $info ) {
			return false;
		}

		// The file type is held in the top bits of the mode (S_IFMT).
		$type    = $info['mode'] & 0170000;
		$is_file = 0100000 === $type;

		return [
			'path'    => $path,
			'exists'  => true,
			'is_dir'  => 0040000 === $type,
			'is_file' => $is_file,
			'size'    => $is_file ? (int) $info['size'] : 0,
			'mtime'   => (int) $info['mtime'],
			'perms'   => sprintf( '%04o', $info['mode'] & 07777 ),
		];
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
		if ( ! $this->is_dir( $path ) ) {
			return false;
		}

		$names = @scandir( $path, \SCANDIR_SORT_NONE );

		if ( false === $names ) {
			return false;
		}

		$names = array_values( array_diff( $names, [ '.', '..' ] ) );
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