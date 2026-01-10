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

defined( 'SMLISER_ABSPATH' ) || exit;

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
     * @var WP_Filesystem_Base|null
     */
    protected ?WP_Filesystem_Base $fs = null;

    /**
     * Constructor.
     *
     * Initializes the WordPress filesystem API.
     */
    public function __construct() {
        $this->init_fs();
    }

    /**
     * Initialize WP_Filesystem.
     *
     * @return void
     */
    protected function init_fs(): void {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            require_once SMLISER_ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->fs = $wp_filesystem;
    }

    /**
     * Get the underlying WP_Filesystem handler.
     *
     * @return WP_Filesystem_Base|null
     */
    public function get_fs(): ?WP_Filesystem_Base {
        return $this->fs;
    }

    // --- Core checks ---

    /**
     * Check if a path is a directory.
     *
     * @param string $path Absolute path.
     * @return bool True if directory, false otherwise.
     */
    public function is_dir( string $path ): bool {
        return $this->fs->is_dir( $path );
    }

    /**
     * Check if a path is a file.
     *
     * @param string $path Absolute path.
     * @return bool True if file, false otherwise.
     */
    public function is_file( string $path ): bool {
        return $this->fs->is_file( $path );
    }

    /**
     * Check if a path exists.
     *
     * @param string $path Absolute path.
     * @return bool True if exists, false otherwise.
     */
    public function exists( string $path ): bool {
        return $this->fs->exists( $path );
    }

    /**
     * Check if a file/directory is readable.
     *
     * @param string $path Absolute path.
     * @return bool True if readable, false otherwise.
     */
    public function is_readable( string $path ): bool {
        return $this->fs->is_readable( $path );
    }

    /**
     * Check if a file/directory is writable.
     *
     * @param string $path Absolute path.
     * @return bool True if writable, false otherwise.
     */
    public function is_writable( string $path ): bool {
        return $this->fs->is_writable( $path );
    }

    /**
     * Check if a path uses a stream wrapper (e.g., s3:// or ftp://).
     *
     * @param mixed $thing The path or URL.
     * @return bool True if stream wrapper, false otherwise.
     */
    public function is_stream( mixed $thing ): bool {
        $scheme_separator = strpos( $thing, '://' );
        if ( false === $scheme_separator ) {
            return false;
        }
        $stream = substr( $thing, 0, $scheme_separator );
        return in_array( $stream, stream_get_wrappers(), true );
    }

    // --- File operations ---

    /**
     * Get file contents.
     *
     * @param string $file Absolute path.
     * @return string|false File contents or false on failure.
     */
    public function get_contents( string $file ): string|false {
        return $this->fs->get_contents( $file );
    }

    /**
     * Write contents to a file.
     *
     * @param string $path    Absolute path.
     * @param string $contents Contents to write.
     * @param int    $mode     Optional. File permissions.
     * @return bool True on success, false on failure.
     */
    public function put_contents( string $path, string $contents, int $mode = FS_CHMOD_FILE ): bool {
        return $this->fs->put_contents( $path, $contents, $mode );
    }

    /**
     * Delete a file or directory.
     *
     * @param string       $file      Path to the file/directory.
     * @param bool         $recursive Optional. Delete recursively.
     * @param string|false $type      Optional. 'f' for file, 'd' for directory.
     * @return bool True on success, false on failure.
     */
    public function delete( string $file, bool $recursive = false, string|false $type = false ): bool {
        return $this->fs->delete( $file, $recursive, $type );
    }

    /**
     * Create a directory.
     *
     * @param string     $path      Absolute path.
     * @param int|false  $chmod     Optional. Permissions.
     * @param bool       $recursive Optional. Create recursively.
     * @return bool True on success, false on failure.
     */
    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool {
        if ( $recursive ) {
            return $this->mkdir_recursive( $path, $chmod );
        }
        return $this->fs->mkdir( $path, $chmod );
    }

    /**
     * Create directories recursively.
     *
     * @param string     $path  Absolute path.
     * @param int|false  $chmod Optional permissions.
     * @return bool True on success, false on failure.
     */
    public function mkdir_recursive( string $path, int|false $chmod = false ): bool {
        $stream_wrapper = null;

        if ( $this->is_stream( $path ) ) {
            $parts = explode( '://', $path, 2 );
            $stream_wrapper = $parts[0];
            $path = $parts[1];
        }

        $path = \smliser_sanitize_path( $path );
        if ( \is_smliser_error( $path ) ) {
            return false;
        }

        if ( $stream_wrapper !== null ) {
            $path = $stream_wrapper . '://' . $path;
        }

        $path = rtrim( $path, '/' );
        if ( empty( $path ) ) {
            $path = '/';
        }

        $dest_parent = dirname( $path );
        while ( $dest_parent !== '.' && ! is_dir( $dest_parent ) && dirname( $dest_parent ) !== $dest_parent ) {
            $dest_parent = dirname( $dest_parent );
        }

        $stats = @stat( $dest_parent );
        $perms = $stats ? ( $stats['mode'] & 0777 ) : 0755;
        if ( $chmod !== false ) {
            $perms = $chmod;
        }

        $relative_parts = explode( '/', ltrim( substr( $path, strlen( $dest_parent ) ), '/' ) );
        $current = $dest_parent;

        foreach ( $relative_parts as $part ) {
            $current .= '/' . $part;
            if ( ! $this->is_dir( $current ) ) {
                if ( ! $this->fs->mkdir( $current, $perms ) ) {
                    return false;
                }
                @$this->fs->chmod( $current, $perms );
            }
        }

        return true;
    }

    /**
     * Remove a directory.
     *
     * @param string $path      Absolute path.
     * @param bool   $recursive Optional. Remove recursively.
     * @return bool True on success, false on failure.
     */
    public function rmdir( string $path, bool $recursive = false ): bool {
        return $this->fs->delete( $path, $recursive, 'd' );
    }

    /**
     * Copy a file or directory.
     *
     * @param string $source    Source path.
     * @param string $dest      Destination path.
     * @param bool   $overwrite Optional. Overwrite if exists.
     * @return bool True on success, false on failure.
     */
    public function copy( string $source, string $dest, bool $overwrite = false ): bool {
        return $this->fs->copy( $source, $dest, $overwrite, FS_CHMOD_FILE );
    }

    /**
     * Move a file or directory.
     *
     * @param string $source    Source path.
     * @param string $dest      Destination path.
     * @param bool   $overwrite Optional. Overwrite if exists.
     * @return bool True on success, false on failure.
     */
    public function move( string $source, string $dest, bool $overwrite = false ): bool {
        return $this->fs->move( $source, $dest, $overwrite );
    }

    /**
     * Rename a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest   Destination path.
     * @return bool True on success, false on failure.
     */
    public function rename( string $source, string $dest ): bool {
        return $this->move( $source, $dest, true );
    }

    /**
     * Change file/directory permissions.
     *
     * @param string $file      Path.
     * @param int|false $mode   Optional. Permissions.
     * @param bool $recursive   Optional. Apply recursively.
     * @return bool True on success, false on failure.
     */
    public function chmod( string $file, int|false $mode = false, bool $recursive = false ): bool {
        return @$this->fs->chmod( $file, $mode, $recursive );
    }

    /**
     * Change file/directory owner.
     *
     * @param string     $file      Path.
     * @param string|int $owner     Owner name or ID.
     * @param bool       $recursive Optional. Apply recursively.
     * @return bool True on success, false on failure.
     */
    public function chown( string $file, string|int $owner, bool $recursive = false ): bool {
        return $this->fs->chown( $file, $owner, $recursive );
    }

    /**
     * List files/directories in a path.
     *
     * @param string|null $path Optional path.
     * @return array|false List of files or false on failure.
     */
    public function list( ?string $path = null ): array|false {
        return $this->fs->dirlist( $path );
    }

    /**
     * Get file size.
     *
     * @param string $path Path.
     * @return int|false Size in bytes or false on failure.
     */
    public function filesize( string $path ): int|false {
        return $this->fs->size( $path );
    }

    /**
     * Get file modification time.
     *
     * @param string $path Path.
     * @return int|false Unix timestamp or false on failure.
     */
    public function filemtime( string $path ): int|false {
        return $this->fs->mtime( $path );
    }

    /**
     * Get file/directory information (stat).
     *
     * @param string $path Path.
     * @return array|false Information array or false on failure.
     */
    public function stat( string $path ): array|false {
        if ( ! $path || ! $this->fs->exists( $path ) ) {
            return false;
        }

        return [
            'path'    => $path,
            'exists'  => true,
            'is_dir'  => $this->is_dir( $path ),
            'is_file' => $this->is_file( $path ),
            'size'    => $this->fs->size( $path ),
            'mtime'   => $this->fs->mtime( $path ),
            'perms'   => $this->fs->gethchmod( $path ),
        ];
    }

    /**
     * Output a file in chunks.
     *
     * @param string $path       File path.
     * @param int    $start      Start position.
     * @param int    $length     Length to read.
     * @param int    $chunk_size Read chunk size.
     * @return bool True on success, false on failure.
     */
    public function readfile( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ): bool {
        if ( ! $path || ! $this->exists( $path ) || ! $this->is_readable( $path ) ) {
            return false;
        }

        $handle = @fopen( $path, 'rb' );
        if ( ! $handle ) {
            return false;
        }

        $size   = $this->fs->size( $path );
        $start  = max( 0, $start );
        $length = $length > 0 ? $length : $size - $start;

        @fseek( $handle, $start );
        $bytes_left = $length;

        while ( $bytes_left > 0 && ! feof( $handle ) ) {
            $read_length = min( $chunk_size, $bytes_left );
            echo fread( $handle, $read_length );
            $bytes_left -= $read_length;

            if ( ob_get_length() ) {
                ob_flush();
                flush();
            }
        }

        fclose( $handle );
        return true;
    }
}
