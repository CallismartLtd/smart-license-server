<?php
/**
 * Direct FileSystem Adapter
 *
 * Raw PHP file I/O implementation of FileSystemAdapterInterface.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem\Adapters;

defined( 'SMLISER_ABSPATH' ) || exit;

class DirectFileSystem implements FileSystemAdapterInterface {

    /**
     * Determine if a given path is a directory.
     *
     * @param string $path Absolute or relative path.
     * @return bool True if the path is a directory, false otherwise.
     */
    public function is_dir( string $path ): bool {
        return @is_dir( $path );
    }

    /**
     * Determine if a given path is a file.
     *
     * @param string $path Absolute or relative path.
     * @return bool True if the path is a file, false otherwise.
     */
    public function is_file( string $path ): bool {
        return @is_file( $path );
    }

    /**
     * Check if a file or directory exists.
     *
     * @param string $path Absolute path.
     * @return bool True if the path exists, false otherwise.
     */
    public function exists( string $path ): bool {
        return file_exists( $path );
    }

    /**
     * Check if a file is readable.
     *
     * @param string $path Absolute path.
     * @return bool True if readable, false otherwise.
     */
    public function is_readable( string $path ): bool {
        return @is_readable( $path );
    }

    /**
     * Check if a file is writable.
     *
     * @param string $path Absolute path.
     * @return bool True if writable, false otherwise.
     */
    public function is_writable( string $path ): bool {
        return @is_writable( $path );
    }

    /**
     * Determine if the given input is a stream wrapper.
     *
     * @param mixed $thing Path or input to test.
     * @return bool True if a stream wrapper, false otherwise.
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
     * Retrieve the contents of a file.
     *
     * @param string $file Absolute path to the file.
     * @return string|false File contents or false on failure.
     */
    public function get_contents( string $file ): string|false {
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
     * @param int $mode File permissions (optional, default FS_CHMOD_FILE).
     * @return bool True on success, false on failure.
     */
    public function put_contents( string $path, string $contents, int $mode = FS_CHMOD_FILE ): bool {

        if ( '' === $path ) {
            return false;
        }

        $tmp = $path . '.tmp.' . uniqid( '', true );

        $bytes = file_put_contents( $tmp, $contents, LOCK_EX );

        if ( false === $bytes || $bytes !== strlen( $contents ) ) {
            @unlink( $tmp );
            return false;
        }

        if ( ! rename( $tmp, $path ) ) {
            @unlink( $tmp );
            return false;
        }

        if ( false !== $mode ) {
            @chmod( $path, $mode );
        }

        return true;
    }


    /**
     * Delete a file or directory.
     *
     * @param string $file Path to the file or directory.
     * @param bool $recursive Optional. Delete recursively if true.
     * @param string|false $type Optional. 'f' for file, 'd' for directory, false for auto.
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
     * @param string $path Absolute path.
     * @param int|false $chmod Optional permissions.
     * @param bool $recursive Optional. Create intermediate directories if true.
     * @return bool True on success, false on failure.
     */
    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool {

        if ( $this->exists( $path ) ) {
            return true;
        }

        $result = @mkdir( $path, $chmod ?: 0755, $recursive );

        if ( $result && false !== $chmod ) {
            @chmod( $path, $chmod );
        }

        return $result;
    }

    /**
     * Create directories recursively.
     *
     * @param string $path Absolute path.
     * @param int|false $chmod Optional permissions.
     * @return bool True on success, false on failure.
     */
    public function mkdir_recursive( string $path, int|false $chmod = false ): bool {
        return $this->mkdir( $path, $chmod, true );
    }

    /**
     * Remove a directory.
     *
     * @param string $path Absolute path.
     * @param bool $recursive Optional. Remove recursively if true.
     * @return bool True on success, false on failure.
     */
    public function rmdir( string $path, bool $recursive = false ): bool {

        if ( ! $this->is_dir( $path ) ) {
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

            $full = $path . DIRECTORY_SEPARATOR . $item;

            if ( $this->is_dir( $full ) ) {
                $this->rmdir( $full, true );
            } else {
                @unlink( $full );
            }
        }

        return @rmdir( $path );
    }

    /**
     * Copy a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Optional. Overwrite if true.
     * @return bool True on success, false on failure.
     */
    public function copy( string $source, string $dest, bool $overwrite = false ): bool {

        if ( ! $this->exists( $source ) ) {
            return false;
        }

        if ( $this->exists( $dest ) && ! $overwrite ) {
            return false;
        }

        if ( $this->is_file( $source ) ) {
            return @copy( $source, $dest );
        }

        if ( $this->is_dir( $source ) ) {
            $this->mkdir( $dest );

            foreach ( scandir( $source ) as $item ) {
                if ( '.' === $item || '..' === $item ) {
                    continue;
                }

                $this->copy(
                    $source . DIRECTORY_SEPARATOR . $item,
                    $dest . DIRECTORY_SEPARATOR . $item,
                    $overwrite
                );
            }

            return true;
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
    public function move( string $source, string $dest, bool $overwrite = false ): bool {

        if ( $this->exists( $dest ) ) {
            if ( ! $overwrite ) {
                return false;
            }
            $this->delete( $dest, true );
        }

        return @rename( $source, $dest );
    }

    /**
     * Rename a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @return bool True on success, false on failure.
     */
    public function rename( string $source, string $dest ): bool {
        return @rename( $source, $dest );
    }

    /**
     * Change file permissions.
     *
     * @param string $file Path to file or directory.
     * @param int|false $mode Optional. Permissions as octal number.
     * @param bool $recursive Optional. Change permissions recursively.
     * @return bool True on success, false on failure.
     */
    public function chmod( string $file, int|false $mode = false, bool $recursive = false ): bool {

        if ( ! $mode ) {
			if ( $this->is_file( $file ) ) {
				$mode = FS_CHMOD_FILE;
			} elseif ( $this->is_dir( $file ) ) {
				$mode = FS_CHMOD_DIR;
			} else {
				return false;
			}
		}

        if ( ! $recursive ) {
            return @chmod( $file, $mode );
        }

        $success = true;

        if ( $this->is_dir( $file ) ) {
            foreach ( scandir( $file ) as $item ) {
                if ( '.' === $item || '..' === $item ) {
                    continue;
                }

                $success = $this->chmod(
                    $file . DIRECTORY_SEPARATOR . $item,
                    $mode,
                    true
                ) && $success;
            }
        }

        return @chmod( $file, $mode ) && $success;
    }

    /**
     * Change file owner.
     *
     * @param string $file Path to file or directory.
     * @param string|int $owner Owner name or UID.
     * @param bool $recursive Optional. Change owner recursively.
     * @return bool True on success, false on failure.
     */
    public function chown( string $file, string|int $owner, bool $recursive = false ): bool {

        if ( ! $recursive ) {
            return @chown( $file, $owner );
        }

        $success = true;

        if ( $this->is_dir( $file ) ) {
            foreach ( scandir( $file ) as $item ) {
                if ( '.' === $item || '..' === $item ) {
                    continue;
                }

                $success = $this->chown(
                    $file . DIRECTORY_SEPARATOR . $item,
                    $owner,
                    true
                ) && $success;
            }
        }

        return @chown( $file, $owner ) && $success;
    }

    /**
     * List files and directories at a path.
     *
     * @param string|null $path Optional path. Defaults to root.
     * @return array|false Array of file info or false on failure.
     */
    public function list( string|null $path = null ): array|false {

        $path = $path ?: getcwd();

        if ( ! $this->is_dir( $path ) ) {
            return false;
        }

        $items = scandir( $path );

        if ( false === $items ) {
            return false;
        }

        $results = [];

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;

            $results[] = $this->stat( $full );
        }

        return $results;
    }

    public function filesize( string $path ): int|false {
        return @filesize( $path );
    }

    public function filemtime( string $path ): int|false {
        return @filemtime( $path );
    }

    public function stat( string $path ): array|false {

        if ( ! $this->exists( $path ) ) {
            return false;
        }

        return [
            'path'    => $path,
            'exists'  => true,
            'is_dir'  => $this->is_dir( $path ),
            'is_file' => $this->is_file( $path ),
            'size'    => $this->is_file( $path ) ? $this->filesize( $path ) : 0,
            'mtime'   => $this->filemtime( $path ),
            'perms'   => substr( sprintf( '%o', fileperms( $path ) ), -4 ),
        ];
    }

    /**
     * Output a file in chunks.
     *
     * @param string $path Absolute path.
     * @param int $start Optional start offset.
     * @param int $length Optional length to read.
     * @param int $chunk_size Optional chunk size (default 1MB).
     * @return bool True on success, false on failure.
     */
    public function readfile(
        string $path,
        int $start = 0,
        int $length = 0,
        int $chunk_size = 1048576
    ): bool {

        if ( ! $this->is_readable( $path ) ) {
            return false;
        }

        $handle = fopen( $path, 'rb' );

        if ( false === $handle ) {
            return false;
        }

        if ( $start > 0 ) {
            fseek( $handle, $start );
        }

        $remaining = $length > 0 ? $length : null;

        while ( ! feof( $handle ) ) {

            if ( null !== $remaining && $remaining <= 0 ) {
                break;
            }

            $read_length = ( null !== $remaining )
                ? min( $chunk_size, $remaining )
                : $chunk_size;

            $buffer = fread( $handle, $read_length );

            if ( false === $buffer ) {
                fclose( $handle );
                return false;
            }

            echo $buffer;
            flush();

            if ( null !== $remaining ) {
                $remaining -= strlen( $buffer );
            }
        }

        fclose( $handle );

        return true;
    }
}
