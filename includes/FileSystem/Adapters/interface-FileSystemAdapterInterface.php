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

defined( 'SMLISER_ABSPATH' ) || exit;

interface FileSystemAdapterInterface {

    /**
     * Determine if a given path is a directory.
     *
     * @param string $path Absolute or relative path.
     * @return bool True if the path is a directory, false otherwise.
     */
    public function is_dir( string $path ): bool;

    /**
     * Determine if a given path is a file.
     *
     * @param string $path Absolute or relative path.
     * @return bool True if the path is a file, false otherwise.
     */
    public function is_file( string $path ): bool;

    /**
     * Check if a file or directory exists.
     *
     * @param string $path Absolute path.
     * @return bool True if the path exists, false otherwise.
     */
    public function exists( string $path ): bool;

    /**
     * Check if a file is readable.
     *
     * @param string $path Absolute path.
     * @return bool True if readable, false otherwise.
     */
    public function is_readable( string $path ): bool;

    /**
     * Check if a file is writable.
     *
     * @param string $path Absolute path.
     * @return bool True if writable, false otherwise.
     */
    public function is_writable( string $path ): bool;

    /**
     * Determine if the given input is a stream wrapper.
     *
     * @param mixed $thing Path or input to test.
     * @return bool True if a stream wrapper, false otherwise.
     */
    public function is_stream( mixed $thing ): bool;

    /**
     * Retrieve the contents of a file.
     *
     * @param string $file Absolute path to the file.
     * @return string|false File contents or false on failure.
     */
    public function get_contents( string $file ): string|false;

    /**
     * Write contents to a file.
     *
     * @param string $path Absolute path to the file.
     * @param string $contents Data to write.
     * @param int $mode File permissions (optional, default FS_CHMOD_FILE).
     * @return bool True on success, false on failure.
     */
    public function put_contents( string $path, string $contents, int $mode = FS_CHMOD_FILE ): bool;

    /**
     * Delete a file or directory.
     *
     * @param string $file Path to the file or directory.
     * @param bool $recursive Optional. Delete recursively if true.
     * @param string|false $type Optional. 'f' for file, 'd' for directory, false for auto.
     * @return bool True on success, false on failure.
     */
    public function delete( string $file, bool $recursive = false, string|false $type = false ): bool;

    /**
     * Create a directory.
     *
     * @param string $path Absolute path.
     * @param int|false $chmod Optional permissions.
     * @param bool $recursive Optional. Create intermediate directories if true.
     * @return bool True on success, false on failure.
     */
    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool;

    /**
     * Create directories recursively.
     *
     * @param string $path Absolute path.
     * @param int|false $chmod Optional permissions.
     * @return bool True on success, false on failure.
     */
    public function mkdir_recursive( string $path, int|false $chmod = false ): bool;

    /**
     * Remove a directory.
     *
     * @param string $path Absolute path.
     * @param bool $recursive Optional. Remove recursively if true.
     * @return bool True on success, false on failure.
     */
    public function rmdir( string $path, bool $recursive = false ): bool;

    /**
     * Copy a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Optional. Overwrite if true.
     * @return bool True on success, false on failure.
     */
    public function copy( string $source, string $dest, bool $overwrite = false ): bool;

    /**
     * Move or rename a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Optional. Overwrite if true.
     * @return bool True on success, false on failure.
     */
    public function move( string $source, string $dest, bool $overwrite = false ): bool;

    /**
     * Rename a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @return bool True on success, false on failure.
     */
    public function rename( string $source, string $dest ): bool;

    /**
     * Change file permissions.
     *
     * @param string $file Path to file or directory.
     * @param int|false $mode Optional. Permissions as octal number.
     * @param bool $recursive Optional. Change permissions recursively.
     * @return bool True on success, false on failure.
     */
    public function chmod( string $file, int|false $mode = false, bool $recursive = false ): bool;

    /**
     * Change file owner.
     *
     * @param string $file Path to file or directory.
     * @param string|int $owner Owner name or UID.
     * @param bool $recursive Optional. Change owner recursively.
     * @return bool True on success, false on failure.
     */
    public function chown( string $file, string|int $owner, bool $recursive = false ): bool;

    /**
     * List files and directories at a path.
     *
     * @param string|null $path Optional path. Defaults to root.
     * @return array|false Array of file info or false on failure.
     */
    public function list( string|null $path = null ): array|false;

    /**
     * Get file size.
     *
     * @param string $path Absolute path.
     * @return int|false File size in bytes, false on failure.
     */
    public function filesize( string $path ): int|false;

    /**
     * Get file modification time.
     *
     * @param string $path Absolute path.
     * @return int|false Unix timestamp, false on failure.
     */
    public function filemtime( string $path ): int|false;

    /**
     * Get file stat information.
     *
     * @param string $path Absolute path.
     * @return array|false Array with keys: path, exists, is_dir, is_file, size, mtime, perms, or false on failure.
     */
    public function stat( string $path ): array|false;

    /**
     * Output a file in chunks.
     *
     * @param string $path Absolute path.
     * @param int $start Optional start offset.
     * @param int $length Optional length to read.
     * @param int $chunk_size Optional chunk size (default 1MB).
     * @return bool True on success, false on failure.
     */
    public function readfile( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ): bool;
}
