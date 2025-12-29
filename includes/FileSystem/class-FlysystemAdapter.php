<?php
/**
 * Flysystem Adapter
 *
 * Provides filesystem operations via League\Flysystem\Filesystem.
 * Implements the FileSystemAdapterInterface to ensure compatibility with
 * SmartLicenseServer\FileSystem\FileSystem.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\StorageAttributes;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Adapter for Flysystem-based filesystem operations.
 *
 * Wraps a League\Flysystem\Filesystem instance and provides all methods
 * required by FileSystemAdapterInterface.
 */
class FlysystemAdapter implements FileSystemAdapterInterface {

    /**
     * The Flysystem filesystem instance.
     *
     * @var Flysystem
     */
    protected Flysystem $fs;

    /**
     * Constructor.
     *
     * Initializes the adapter with a Flysystem instance.
     *
     * @param Flysystem $fs Flysystem instance to use for filesystem operations.
     */
    public function __construct( Flysystem $fs ) {
        $this->fs = $fs;
    }

    /**
     * Determine if a given path is a directory.
     *
     * @param string $path Path to check.
     * @return bool True if directory exists, false otherwise.
     */
    public function is_dir( string $path ): bool {
        return $this->fs->directoryExists( $path );
    }

    /**
     * Determine if a given path is a file.
     *
     * @param string $path Path to check.
     * @return bool True if file exists, false otherwise.
     */
    public function is_file( string $path ): bool {
        return $this->fs->fileExists( $path );
    }

    /**
     * Check if a file or directory exists.
     *
     * @param string $path Path to check.
     * @return bool True if file or directory exists, false otherwise.
     */
    public function exists( string $path ): bool {
        return $this->fs->fileExists( $path ) || $this->fs->directoryExists( $path );
    }

    /**
     * Check if a path is readable.
     *
     * Flysystem does not support permissions directly, so returns true if it exists.
     *
     * @param string $path Path to check.
     * @return bool True if path exists, false otherwise.
     */
    public function is_readable( string $path ): bool {
        return $this->exists( $path );
    }

    /**
     * Check if a path is writable.
     *
     * Attempts to write a temporary file to test writability.
     *
     * @param string $path Path to check.
     * @return bool True if writable, false otherwise.
     */
    public function is_writable( string $path ): bool {
        try {
            if ( $this->is_dir( $path ) ) {
                $tmp = rtrim( $path, '/' ) . '/.sls_temp';
                $this->fs->write( $tmp, '' );
                $this->fs->delete( $tmp );
                return true;
            }
            if ( $this->is_file( $path ) ) {
                $contents = $this->fs->read( $path );
                $this->fs->write( $path, $contents );
                return true;
            }
        } catch ( \Exception $e ) {
            return false;
        }

        return false;
    }

    /**
     * Check if the input is a stream wrapper.
     *
     * Flysystem does not use PHP stream wrappers.
     *
     * @param mixed $thing Input to check.
     * @return bool Always false.
     */
    public function is_stream( mixed $thing ): bool {
        return false;
    }

    /**
     * Retrieve the contents of a file.
     *
     * @param string $file Path to the file.
     * @return string|false File contents, or false on failure.
     */
    public function get_contents( string $file ): string|false {
        try {
            return $this->fs->read( $file );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Write contents to a file.
     *
     * @param string $path Path to the file.
     * @param string $contents Data to write.
     * @param int $mode Optional permissions (ignored in Flysystem).
     * @return bool True on success, false on failure.
     */
    public function put_contents( string $path, string $contents, int $mode = FS_CHMOD_FILE ): bool {
        try {
            if ( $this->fs->fileExists( $path ) ) {
                $this->fs->delete( $path );
            }
            $this->fs->write( $path, $contents );
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Delete a file or directory.
     *
     * @param string $file Path to delete.
     * @param bool $recursive Optional. Delete recursively if directory.
     * @param string|false $type Optional. 'f' for file, 'd' for directory, false for auto-detect.
     * @return bool True on success, false on failure.
     */
    public function delete( string $file, bool $recursive = false, string|false $type = false ): bool {
        try {
            if ( $type === 'd' || ( $recursive && $this->is_dir( $file ) ) ) {
                $this->fs->deleteDirectory( $file );
            } else {
                $this->fs->delete( $file );
            }
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Create a directory.
     *
     * @param string $path Path to create.
     * @param int|false $chmod Optional permissions (ignored in Flysystem).
     * @param bool $recursive Optional. Ignored; Flysystem always creates directories.
     * @return bool True on success, false on failure.
     */
    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool {
        try {
            $this->fs->createDirectory( $path );
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Create directories recursively.
     *
     * Flysystem automatically creates directories recursively.
     *
     * @param string $path Path to create.
     * @param int|false $chmod Optional permissions.
     * @return bool True on success, false on failure.
     */
    public function mkdir_recursive( string $path, int|false $chmod = false ): bool {
        return $this->mkdir( $path, $chmod, true );
    }

    /**
     * Remove a directory.
     *
     * @param string $path Path to remove.
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
     * @param bool $overwrite Optional. Overwrite if destination exists.
     * @return bool True on success, false on failure.
     */
    public function copy( string $source, string $dest, bool $overwrite = false ): bool {
        try {
            if ( $overwrite && $this->exists( $dest ) ) {
                $this->delete( $dest, $this->is_dir( $dest ), $this->is_dir( $dest ) ? 'd' : 'f' );
            }
            $this->fs->copy( $source, $dest );
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Move or rename a file/directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Optional. Overwrite if destination exists.
     * @return bool True on success, false on failure.
     */
    public function move( string $source, string $dest, bool $overwrite = false ): bool {
        try {
            if ( $overwrite && $this->exists( $dest ) ) {
                $this->delete( $dest, $this->is_dir( $dest ), $this->is_dir( $dest ) ? 'd' : 'f' );
            }
            $this->fs->move( $source, $dest );
            return true;
        } catch ( \Exception $e ) {
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
     * Change file permissions.
     *
     * Flysystem does not support chmod natively.
     *
     * @param string $file Path to file.
     * @param int|false $mode Optional mode.
     * @param bool $recursive Optional recursive flag.
     * @return bool Always true.
     */
    public function chmod( string $file, int|false $mode = false, bool $recursive = false ): bool {
        return true;
    }

    /**
     * Change file owner.
     *
     * Flysystem does not support chown natively.
     *
     * @param string $file Path to file.
     * @param string|int $owner Owner name or UID.
     * @param bool $recursive Optional recursive flag.
     * @return bool Always true.
     */
    public function chown( string $file, string|int $owner, bool $recursive = false ): bool {
        return true;
    }

    /**
     * List files and directories at a path.
     *
     * @param string|null $path Path to list.
     * @return array|false Array of file info, false on failure.
     */
    public function list( string|null $path = null ): array|false {
        try {
            $listing = $this->fs->listContents( $path ?? '', false );
            $result  = [];
            /** @var StorageAttributes $item */
            foreach ( $listing as $item ) {
                $result[] = [
                    'path'  => $item->path(),
                    'type'  => $item->isDir() ? 'd' : 'f',
                    'size'  => $item->isFile() ? $item->fileSize() : 0,
                    'mtime' => $item->lastModified(),
                ];
            }
            return $result;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get file size.
     *
     * @param string $path Path to file.
     * @return int|false File size in bytes, false on failure.
     */
    public function filesize( string $path ): int|false {
        try {
            return $this->fs->fileSize( $path );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get file modification time.
     *
     * @param string $path Path to file.
     * @return int|false Unix timestamp, false on failure.
     */
    public function filemtime( string $path ): int|false {
        try {
            return $this->fs->lastModified( $path );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get file stat information.
     *
     * @param string $path Path to file.
     * @return array|false Array of file info or false if not found.
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
     * @param string $path Path to file.
     * @param int $start Optional start offset.
     * @param int $length Optional number of bytes to read.
     * @param int $chunk_size Optional chunk size, default 1MB.
     * @return bool True on success, false on failure.
     */
    public function readfile( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ): bool {
        try {
            $contents = $this->fs->read( $path );
            echo substr( $contents, $start, $length ?: null );
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }
}
