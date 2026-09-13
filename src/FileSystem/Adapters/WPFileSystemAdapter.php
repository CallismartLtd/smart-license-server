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
    protected function init_fs(): void {
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
    public function get_contents( string $file ): string|false {
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
     * @param int|null $mode Optional. File permissions. Null (default)
     *                        applies the adapter's configured file permission.
     * @return bool True on success, false on failure.
     */
    public function put_contents( string $path, string $contents, ?int $mode = null ): bool {
        if ( empty( $path ) ) {
            return false;
        }

        $dir = dirname( $path );
        if ( ! $this->mkdir( $dir ) ) {
            return false;
        }

        return $this->fs->put_contents( $path, $contents, $mode ?? $this->file_permission );
    }

    /**
     * Delete a file or directory.
     *
     * @param string $file Path to the file/directory.
     * @param bool $recursive Optional. Delete recursively.
     * @param string|false $type Optional. 'f' for file, 'd' for directory.
     * @return bool True on success, false on failure.
     */
    public function delete( string $file, bool $recursive = false, string|false $type = false ): bool {
        if ( false === $type ) {
            if ( $this->is_file( $file ) ) {
                $type = 'f';
            } elseif ( $this->is_dir( $file ) ) {
                $type = 'd';
            } else {
                return false;
            }
        }

        return $this->fs->delete( $file, $recursive, $type );
    }

    /**
     * Create a directory.
     *
     * @param string $path Absolute path.
     * @param int|false $chmod Optional. Permissions. False uses the
     *                          adapter's configured directory permission.
     * @param bool $recursive Optional. Create recursively.
     * @return bool True on success, false on failure.
     */
    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool {
        if ( $this->exists( $path ) ) {
            return true;
        }

        if ( $recursive ) {
            return $this->mkdir_recursive( $path, $chmod );
        }

        return $this->fs->mkdir( $path, $chmod ?: $this->dir_permission );
    }

    /**
     * Create directories recursively.
     *
     * @param string $path Absolute path.
     * @param int|false $chmod Optional permissions.
     * @return bool True on success, false on failure.
     */
    public function mkdir_recursive( string $path, int|false $chmod = false ): bool {
        $stream_wrapper = null;

        if ( $this->is_stream( $path ) ) {
            $parts = explode( '://', $path, 2 );
            $stream_wrapper = $parts[0];
            $path = $parts[1];
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
        while ( $dest_parent !== '.' && ! $this->is_dir( $dest_parent ) && dirname( $dest_parent ) !== $dest_parent ) {
            $dest_parent = dirname( $dest_parent );
        }

        if ( false === $chmod ) {
            $stats = @stat( $dest_parent );
            $chmod = $stats ? ( ( $stats['mode'] & 0777 ) | $this->dir_permission ) : $this->dir_permission;
        }

        $relative_parts = explode( $sep, ltrim( substr( $path, strlen( $dest_parent ) ), $sep ) );
        $current = $dest_parent;

        foreach ( $relative_parts as $part ) {
            $current .= $sep . $part;
            if ( ! $this->is_dir( $current ) ) {
                if ( ! $this->fs->mkdir( $current, $chmod ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Remove a directory.
     *
     * @param string $path Absolute path.
     * @param bool $recursive Optional. Remove recursively.
     * @return bool True on success, false on failure.
     */
    public function rmdir( string $path, bool $recursive = false ): bool {
        return $this->delete( $path, $recursive, 'd' );
    }

    /**
     * Copy a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Optional. Overwrite if exists.
     * @param int|false $mode Optional. Permissions.
     * @return bool True on success, false on failure.
     */
    public function copy( string $source, string $dest, bool $overwrite = false, int|false $mode = false ): bool {
        if ( ! $this->exists( $source ) ) {
            return false;
        }

        if ( $this->exists( $dest ) ) {
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

        $entries = $this->fs->dirlist( $source );

        if ( false === $entries ) {
            return false;
        }

        foreach ( $entries as $name => $_ ) {
            if ( '.' === $name || '..' === $name ) {
                continue;
            }

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
    public function move( string $source, string $dest, bool $overwrite = false ): bool {
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
    public function rename( string $source, string $dest ): bool {
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
     * @param int|false $mode Optional. Permissions. False uses the
     *                         adapter's configured file/directory
     *                         permission depending on the target's type.
     * @param bool $recursive Optional. Apply recursively.
     * @return bool True on success, false on failure.
     */
    public function chmod( string $file, int|false $mode = false, bool $recursive = false ): bool {
        if ( ! $mode ) {
            if ( $this->is_file( $file ) ) {
                $mode = $this->file_permission;
            } elseif ( $this->is_dir( $file ) ) {
                $mode = $this->dir_permission;
            } else {
                return false;
            }
        }

        return @$this->fs->chmod( $file, $mode, $recursive );
    }

    /**
     * Change file/directory owner.
     *
     * @param string $file Path.
     * @param string|int $owner Owner name or ID.
     * @param bool $recursive Optional. Apply recursively.
     * @return bool True on success, false on failure.
     */
    public function chown( string $file, string|int $owner, bool $recursive = false ): bool {
        return $this->fs->chown( $file, $owner, $recursive );
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
        if ( ! $this->exists( $path ) ) {
            return false;
        }

        return [
            'path'    => $path,
            'exists'  => true,
            'is_dir'  => $this->is_dir( $path ),
            'is_file' => $this->is_file( $path ),
            'size'    => $this->is_file( $path ) ? $this->fs->size( $path ) : 0,
            'mtime'   => $this->fs->mtime( $path ),
            'perms'   => $this->fs->getchmod( $path ),
        ];
    }

    /**
     * Output a file in chunks.
     *
     * @param string $path File path.
     * @param int $start Start position.
     * @param int $length Length to read.
     * @param int $chunk_size Read chunk size.
     * @return bool True on success, false on failure.
     */
    public function readfile( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ): bool {
        if ( ! $this->is_file( $path ) ) {
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