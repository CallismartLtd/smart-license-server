<?php
/**
 * Laravel FileSystem Adapter
 *
 * Provides filesystem operations via Laravel's filesystem abstraction.
 * Acts as a bridge between SmartLicenseServer\FileSystem and Laravel's
 * FilesystemAdapter (local, S3, FTP, etc).
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem\Adapters;

use Illuminate\Contracts\Filesystem\Filesystem as LaravelFilesystem;
use Illuminate\Filesystem\FilesystemAdapter;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Adapter for Laravel filesystem operations.
 *
 * Wraps an Illuminate filesystem instance and implements
 * FileSystemAdapterInterface for Smart License Server.
 */
class LaravelFileSystemAdapter implements FileSystemAdapterInterface {

    /**
     * Laravel filesystem instance.
     *
     * @var LaravelFilesystem|FilesystemAdapter
     */
    protected LaravelFilesystem $fs;

    /**
     * Constructor.
     *
     * @param LaravelFilesystem $fs Laravel filesystem instance.
     */
    public function __construct( LaravelFilesystem $fs ) {
        $this->fs = $fs;
    }

    /**
     * Determine if a given path is a directory.
     *
     * @param string $path Path to check.
     * @return bool True if directory exists, false otherwise.
     */
    public function is_dir( string $path ): bool {
        return method_exists( $this->fs, 'directoryExists' )
            ? $this->fs->directoryExists( $path )
            : false;
    }

    /**
     * Determine if a given path is a file.
     *
     * @param string $path Path to check.
     * @return bool True if file exists, false otherwise.
     */
    public function is_file( string $path ): bool {
        return $this->fs->exists( $path ) && ! $this->is_dir( $path );
    }

    /**
     * Check if a file or directory exists.
     *
     * @param string $path Path to check.
     * @return bool True if exists, false otherwise.
     */
    public function exists( string $path ): bool {
        return $this->fs->exists( $path );
    }

    /**
     * Check if a path is readable.
     *
     * Laravel does not expose explicit read permissions,
     * so existence is used as a heuristic.
     *
     * @param string $path Path to check.
     * @return bool True if readable, false otherwise.
     */
    public function is_readable( string $path ): bool {
        return $this->exists( $path );
    }

    /**
     * Check if a path is writable.
     *
     * Writability is inferred by attempting a safe temporary write.
     *
     * @param string $path Path to check.
     * @return bool True if writable, false otherwise.
     */
    public function is_writable( string $path ): bool {
        try {
            if ( $this->is_dir( $path ) ) {
                $tmp = rtrim( $path, '/' ) . '/.sls_write_test';
                $this->fs->put( $tmp, 'test' );
                $this->fs->delete( $tmp );
                return true;
            }

            if ( $this->is_file( $path ) ) {
                $dir = dirname( $path );
                return $dir !== '.' ? $this->is_writable( $dir ) : false;
            }
        } catch ( \Throwable $e ) {
            return false;
        }

        return false;
    }

    /**
     * Check if the input is a stream.
     *
     * Laravel filesystem does not use PHP streams directly.
     *
     * @param mixed $thing Value to check.
     * @return bool Always false.
     */
    public function is_stream( mixed $thing ): bool {
        return false;
    }

    /**
     * Retrieve the contents of a file.
     *
     * @param string $file Path to file.
     * @return string|false File contents or false on failure.
     */
    public function get_contents( string $file ): string|false {
        try {
            return $this->fs->get( $file );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Write contents to a file.
     *
     * @param string $path Path to file.
     * @param string $contents Contents to write.
     * @param int $mode Optional permissions (ignored).
     * @return bool True on success, false on failure.
     */
    public function put_contents( string $path, string $contents, int $mode = FS_CHMOD_FILE ): bool {
        try {
            return (bool) $this->fs->put( $path, $contents );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Delete a file or directory.
     *
     * @param string $file Path to delete.
     * @param bool $recursive Whether to delete recursively.
     * @param string|false $type Optional type hint.
     * @return bool True on success, false on failure.
     */
    public function delete( string $file, bool $recursive = false, string|false $type = false ): bool {
        try {
            if ( $recursive && method_exists( $this->fs, 'deleteDirectory' ) ) {
                return $this->fs->deleteDirectory( $file );
            }

            return $this->fs->delete( $file );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Create a directory.
     *
     * @param string $path Directory path.
     * @param int|false $chmod Ignored.
     * @param bool $recursive Ignored.
     * @return bool True on success, false on failure.
     */
    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool {
        try {
            return $this->fs->makeDirectory( $path );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Create directories recursively.
     *
     * Laravel's filesystem creates directories recursively by default,
     * so this method is an explicit semantic alias for mkdir().
     *
     * @param string $path Directory path.
     * @param int|false $chmod Optional permissions (ignored by Laravel filesystem).
     * @return bool True on success, false on failure.
     */
    public function mkdir_recursive( string $path, int|false $chmod = false ): bool {
        return $this->mkdir( $path, $chmod, true );
    }


    /**
     * Remove a directory.
     *
     * @param string $path Directory path.
     * @param bool $recursive Remove recursively.
     * @return bool True on success, false on failure.
     */
    public function rmdir( string $path, bool $recursive = false ): bool {
        return $this->delete( $path, $recursive, 'd' );
    }

    /**
     * Copy a file.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Whether to overwrite.
     * @return bool True on success, false on failure.
     */
    public function copy( string $source, string $dest, bool $overwrite = false ): bool {
        try {
            if ( $overwrite && $this->exists( $dest ) ) {
                $this->delete( $dest );
            }
            return $this->fs->copy( $source, $dest );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Move a file.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Whether to overwrite.
     * @return bool True on success, false on failure.
     */
    public function move( string $source, string $dest, bool $overwrite = false ): bool {
        try {
            if ( $overwrite && $this->exists( $dest ) ) {
                $this->delete( $dest );
            }
            return $this->fs->move( $source, $dest );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Rename a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @return bool True on success, false on failure.
     */
    public function rename( string $source, string $dest ): bool {
        return $this->move( $source, $dest, true );
    }

    /**
     * Change permissions.
     *
     * Not supported by Laravel filesystem.
     *
     * @return bool Always false.
     */
    public function chmod( string $file, int|false $mode = false, bool $recursive = false ): bool {
        return false;
    }

    /**
     * Change owner.
     *
     * Not supported by Laravel filesystem.
     *
     * @return bool Always false.
     */
    public function chown( string $file, string|int $owner, bool $recursive = false ): bool {
        return false;
    }

    /**
     * List directory contents.
     *
     * @param string|null $path Path to list.
     * @return array|false Listing or false on failure.
     */
    public function list( string|null $path = null ): array|false {
        try {
            $files = $this->fs->files( $path ?? '' );
            $dirs  = $this->fs->directories( $path ?? '' );

            $result = [];

            foreach ( $dirs as $dir ) {
                $result[] = [
                    'path' => $dir,
                    'type' => 'd',
                ];
            }

            foreach ( $files as $file ) {
                $result[] = [
                    'path' => $file,
                    'type' => 'f',
                    'size' => $this->fs->size( $file ),
                    'mtime' => $this->fs->lastModified( $file ),
                ];
            }

            return $result;
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Get file size.
     *
     * @param string $path File path.
     * @return int|false Size in bytes or false on failure.
     */
    public function filesize( string $path ): int|false {
        try {
            return $this->fs->size( $path );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Get file modification time.
     *
     * @param string $path File path.
     * @return int|false Unix timestamp or false.
     */
    public function filemtime( string $path ): int|false {
        try {
            return $this->fs->lastModified( $path );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Get file stat information.
     *
     * @param string $path Path to file.
     * @return array|false File stats or false if not found.
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
            'size'    => $this->filesize( $path ),
            'mtime'   => $this->filemtime( $path ),
            'perms'   => false,
        ];
    }

    /**
     * Output a file in chunks.
     *
     * @param string $path File path.
     * @param int $start Start offset.
     * @param int $length Length to read.
     * @param int $chunk_size Chunk size.
     * @return bool True on success, false on failure.
     */
    public function readfile( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ): bool {
        try {
            $stream = $this->fs->readStream( $path );

            if ( ! is_resource( $stream ) ) {
                return false;
            }

            if ( $start > 0 ) {
                fseek( $stream, $start );
            }

            $remaining = $length > 0 ? $length : null;

            while ( ! feof( $stream ) ) {
                $read = $remaining !== null
                    ? min( $chunk_size, $remaining )
                    : $chunk_size;

                $buffer = fread( $stream, $read );
                if ( $buffer === false ) {
                    break;
                }

                echo $buffer;

                if ( $remaining !== null ) {
                    $remaining -= strlen( $buffer );
                    if ( $remaining <= 0 ) {
                        break;
                    }
                }
            }

            fclose( $stream );
            return true;
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Get the underlining filesystem adapter instance.
     */
    public function get_fs() : mixed {
        return $this->fs;
    }
}
