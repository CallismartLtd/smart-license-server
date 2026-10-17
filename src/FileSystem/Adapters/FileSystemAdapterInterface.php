<?php
/**
 * FileSystem Adapter Interface
 *
 * Defines a contract for filesystem operations across different environments.
 * All methods correspond to the existing SmartLicenseServer\FileSystem\FileSystem class,
 * ensuring backward compatibility.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem\Adapters;

interface FileSystemAdapterInterface {

	/**
	 * Determine if a given path is a directory.
	 *
	 * @param string $path Absolute or relative path.
	 * @return bool True if the path is a directory, false otherwise.
	 */
	public function is_dir( string $path ) : bool;

	/**
	 * Determine if a given path is a file.
	 *
	 * @param string $path Absolute or relative path.
	 * @return bool True if the path is a file, false otherwise.
	 */
	public function is_file( string $path ) : bool;

	/**
	 * Check if a file or directory exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if the path exists, false otherwise.
	 */
	public function exists( string $path ) : bool;

	/**
	 * Check if a file is readable.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if readable, false otherwise.
	 */
	public function is_readable( string $path ) : bool;

	/**
	 * Check if a file is writable.
	 *
	 * @param string $path Absolute path.
	 * @return bool True if writable, false otherwise.
	 */
	public function is_writable( string $path ) : bool;

	/**
	 * Determine if the given input is a stream wrapper.
	 *
	 * @param mixed $thing Path or input to test.
	 * @return bool True if a stream wrapper, false otherwise.
	 */
	public function is_stream( mixed $thing ) : bool;

	/**
	 * Retrieve the contents of a file.
	 *
	 * @param string $file Absolute path to the file.
	 * @return string|false File contents or false on failure.
	 */
	public function get_contents( string $file ) : string|false;

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
	public function put_contents( string $path, string $contents, int|false|null $mode = null ) : bool;

	/**
	 * Open a file for reading as a stream.
	 *
	 * The stream is opened in binary read mode and positioned at the start
	 * of the file. The caller owns it and must close it with fclose().
	 *
	 * @param string $path Absolute path to the file.
	 * @return resource|false A readable stream resource, or false on failure.
	 */
	public function read_stream( string $path ) : mixed;

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
	public function write_stream( string $path, mixed $stream, int|false|null $mode = null ) : bool;

	/**
	 * Delete a file or directory.
	 *
	 * @param string $file Path to the file or directory.
	 * @param bool $recursive Optional. Delete recursively if true.
	 * @param string|false $type Optional. 'f' for file, 'd' for directory, false for auto.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $file, bool $recursive = false, string|false $type = false ) : bool;

	/**
	 * Create a directory.
	 *
	 * @param string $path Absolute path.
	 * @param int|false $chmod Optional permissions.
	 * @param bool $recursive Optional. Create intermediate directories if true.
	 * @return bool True on success, false on failure.
	 */
	public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ) : bool;

	/**
	 * Remove a directory.
	 *
	 * @param string $path Absolute path.
	 * @param bool $recursive Optional. Remove recursively if true.
	 * @return bool True on success, false on failure.
	 */
	public function rmdir( string $path, bool $recursive = false ) : bool;

	/**
	 * Copy a file or directory.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @param bool $overwrite Optional. Overwrite if true.
	 * @param int|false $mode Optional. Permissions.
	 * @return bool True on success, false on failure.
	 */
	public function copy( string $source, string $dest, bool $overwrite = false, int|false $mode = false ) : bool;

	/**
	 * Move or rename a file or directory.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @param bool $overwrite Optional. Overwrite if true.
	 * @return bool True on success, false on failure.
	 */
	public function move( string $source, string $dest, bool $overwrite = false ) : bool;

	/**
	 * Rename a file or directory.
	 *
	 * @param string $source Source path.
	 * @param string $dest Destination path.
	 * @return bool True on success, false on failure.
	 */
	public function rename( string $source, string $dest ) : bool;

	/**
	 * Change file permissions.
	 *
	 * @param string $file Path to file or directory.
	 * @param int|false $mode Optional. Permissions as octal number.
	 * @param bool $recursive Optional. Change permissions recursively.
	 * @return bool True on success, false on failure.
	 */
	public function chmod( string $file, int|false $mode = false, bool $recursive = false ) : bool;

	/**
	 * Change file owner.
	 *
	 * @param string $file Path to file or directory.
	 * @param string|int $owner Owner name or UID.
	 * @param bool $recursive Optional. Change owner recursively.
	 * @return bool True on success, false on failure.
	 */
	public function chown( string $file, string|int $owner, bool $recursive = false ) : bool;

	/**
	 * Get file size.
	 *
	 * @param string $path Absolute path.
	 * @return int|false File size in bytes, false on failure.
	 */
	public function filesize( string $path ) : int|false;

	/**
	 * Get file modification time.
	 *
	 * @param string $path Absolute path.
	 * @return int|false Unix timestamp, false on failure.
	 */
	public function filemtime( string $path ) : int|false;

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
	 *
	 * @param string $path Absolute path.
	 * @return array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}|false
	 *         The stat array, or false when $path does not exist or its
	 *         information cannot be read.
	 */
	public function stat( string $path ) : array|false;

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
	public function list_dir( string $path ) : array|false;
}
