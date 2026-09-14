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
     * @param int|false|null $mode Optional permissions. Null (default) applies
     *                              the adapter's configured file permission;
     *                              false skips chmod entirely; an explicit
     *                              int applies that mode.
     * @return bool True on success, false on failure.
     */
    public function put_contents( string $path, string $contents, int|false|null $mode = null ): bool {
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
     * @param int|false $chmod Optional permissions. False uses the
     *                          adapter's configured directory permission.
     * @param bool $recursive Optional. Create intermediate directories if true.
     * @return bool True on success, false on failure.
     */
    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool {
        if ( $this->exists( $path ) ) {
            return true;
        }

        $result = @mkdir( $path, $chmod ?: $this->dir_permission, $recursive );

        if ( $result && false !== $chmod ) {
            $this->chmod( $path, $chmod );
        }

        return $result;
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

            $this->delete( $path . DIRECTORY_SEPARATOR . $item, true );
        }

        return @rmdir( $path );
    }

    /**
     * Copy a file or directory.
     *
     * @param string $source Source path.
     * @param string $dest Destination path.
     * @param bool $overwrite Optional. Overwrite if true.
     * @param int|false $mode Optional. Permissions.
     * @return bool True on success, false on failure.
     */
    public function copy( string $source, string $dest, bool $overwrite = false, int|false $mode = false ): bool {
        if ( ! $this->exists( $source ) ) {
            return false;
        }

        if ( $this->exists( $dest ) && ! $overwrite ) {
            return false;
        }

        if ( ! $mode ) {
            $mode = $this->is_file( $source ) ? $this->file_permission : $this->dir_permission;
        }

        $dest_dir = dirname( $dest );
        if ( ! $this->mkdir( $dest_dir ) ) {
            return false;
        }

        if ( $this->is_file( $source ) ) {
            return @copy( $source, $dest )
                && $this->exists( $dest )
                && $this->chmod( $dest, $mode );
        }

        if ( $this->is_dir( $source ) ) {
            if ( ! $this->mkdir( $dest, $mode ) ) {
                return false;
            }

            $items = scandir( $source );
            if ( false === $items ) {
                return false;
            }

            foreach ( $items as $item ) {
                if ( '.' === $item || '..' === $item ) {
                    continue;
                }

                $this->copy(
                    $source . DIRECTORY_SEPARATOR . $item,
                    $dest . DIRECTORY_SEPARATOR . $item,
                    $overwrite,
                    $mode
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
    public function rename( string $source, string $dest ): bool {
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
     * @param int|false $mode Optional. Permissions as octal number. False
     *                         uses the adapter's configured file/directory
     *                         permission depending on the target's type.
     * @param bool $recursive Optional. Change permissions recursively.
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

        if ( ! $recursive ) {
            return @chmod( $file, $mode );
        }

        $success = true;

        if ( $this->is_dir( $file ) ) {
            $items = scandir( $file );
            if ( false !== $items ) {
                foreach ( $items as $item ) {
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
            $items = scandir( $file );
            if ( false !== $items ) {
                foreach ( $items as $item ) {
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
        }

        return @chown( $file, $owner ) && $success;
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

        $perms = @fileperms( $path );

        return [
            'path'    => $path,
            'exists'  => true,
            'is_dir'  => $this->is_dir( $path ),
            'is_file' => $this->is_file( $path ),
            'size'    => $this->is_file( $path ) ? $this->filesize( $path ) : 0,
            'mtime'   => $this->filemtime( $path ),
            'perms'   => false !== $perms ? substr( sprintf( '%o', $perms ), -4 ) : false,
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
    public function readfile( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ): bool {
        if ( ! $this->is_file( $path ) ) {
            return false;
        }

        $stream = @fopen( $path, 'rb' );

        if ( false === $stream ) {
            return false;
        }

        if ( $start > 0 ) {
            fseek( $stream, $start );
        }

        $remaining = $length > 0 ? $length : null;

        while ( ! feof( $stream ) ) {
            if ( null !== $remaining && $remaining <= 0 ) {
                break;
            }

            $read_length = ( null !== $remaining )
                ? min( $chunk_size, $remaining )
                : $chunk_size;

            $buffer = fread( $stream, $read_length );

            if ( false === $buffer ) {
                fclose( $stream );
                return false;
            }

            echo $buffer;
            flush();

            if ( null !== $remaining ) {
                $remaining -= strlen( $buffer );
            }
        }

        fclose( $stream );

        return true;
    }
}