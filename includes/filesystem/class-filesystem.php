<?php
/**
 * Safe FileSystem handler for repository operations.
 *
 * Provides a sandboxed layer over WP_Filesystem, ensuring all file
 *
 * @since 0.0.6
 */

namespace SmartLicenseServer;

defined( 'ABSPATH' ) || exit;

/**
 * This class is a wrapper around WP_Filesystem
 */
class FileSystem {

    /**
     * WP_Filesystem instance.
     *
     * @var \WP_Filesystem_Base
     */
    protected $fs;

    /**
     * Constructor.
     *
     */
    public function __construct() {
        global $wp_filesystem;

        require_once ABSPATH . '/wp-admin/includes/file.php';

        if ( ! $wp_filesystem ) {
            WP_Filesystem();
        }

        $this->fs       = $wp_filesystem;
    }

    /**
     * Telss wether the given path is a directory
     */
    public function is_dir( $path ) {
        return $this->fs->is_dir( $path );
    }

    /**
     * Check if a file exists.
     *
     * @param string $path Absolute path.
     * @return bool
     */
    public function exists( $file ) {
        return $this->fs->exists( $file );
    }

    /**
     * Check if a file is readable.
     *
     * @param string $path Absolute path
     * @return bool
     */
    public function is_readable( $path ) {
        return $this->fs->is_readable( $path );
    }

    /**
     * Check if a file is writable.
     *
     * @param string $path Absolute path
     * @return bool
     */
    public function is_writable( $path ) {
        return $this->fs->is_writable( $path );
    }

    /**
     * Get file contents.
     *
     * @param string $file Absolute path to the file
     * @return string|false
     */
    public function get_contents( $file ) {
        return $this->fs->get_contents( $file );
    }

    /**
     * Put file contents.
     *
     * @param string $path Absolute path 
     * @param string $contents
     * @param int    $mode
     * @return bool
     */
    public function put_contents( $path, $contents, $mode = FS_CHMOD_FILE ) {
        return $this->fs->put_contents( $path, $contents, $mode );
    }

    /**
     * Delete a file.
     *
	 * @param string       $file      Path to the file or directory.
	 * @param bool         $recursive Optional. If set to true, deletes files and folders recursively.
	 *                                Default false.
	 * @param string|false $type      Type of resource. 'f' for file, 'd' for directory.
	 *                                Default false.
	 * @return bool True on success, false on failure.
     */
    public function delete( $file, $recursive = false, $type = false ) {
        return $this->fs->delete( $file, $recursive, $type );
    }

    /**
     * Create a directory.
     *
     * @param string $path Absolute path
     * @param int    $chmod
     * @param bool   $recursive
     * @return bool
     */
    public function mkdir( $path, $chmod = FS_CHMOD_DIR, $recursive = true ) {
        return $this->fs->mkdir( $path, $chmod, $recursive );
    }

    /**
     * Remove a directory.
     *
     * @param string $path Absolute path
     * @param bool   $recursive
     * @return bool
     */
    public function rmdir( $path, $recursive = false ) {
        return $this->fs->delete( $path, $recursive, 'd' );
    }

    /**
     * Copy a file/directory.
     *
     * @param string $source
     * @param string $dest
     * @param bool   $overwrite
     * @return bool
     */
    public function copy( $source, $dest, $overwrite = false ) {

        return $this->fs->copy( $source, $dest, $overwrite, FS_CHMOD_FILE );
    }

    /**
     * Rename or move a file/directory.
     *
     * @param string $source
     * @param string $dest
     * @return bool
     */
    public function rename( $source, $dest ) {
        return $this->fs->move( $source, $dest, true );
    }

	/**
	 * Changes filesystem permissions.
	 *
	 * @param string    $file      Path to the file.
	 * @param int|false $mode      Optional. The permissions as octal number, usually 0644 for files,
	 *                             0755 for directories. Default false.
	 * @param bool      $recursive Optional. If set to true, changes file permissions recursively.
	 *                             Default false.
	 * @return bool True on success, false on failure.
	 */
	public function chmod( $file, $mode = false, $recursive = false ) {
        return $this->fs->chmod( $file, $mode = false, $recursive = false );
    }

	/**
	 * Changes the owner of a file or directory.
	 *
	 * @param string     $file      Path to the file or directory.
	 * @param string|int $owner     A user name or number.
	 * @param bool       $recursive Optional. If set to true, changes file owner recursively.
	 *                              Default false.
	 * @return bool True on success, false on failure.
	 */
	public function chown( $file, $owner, $recursive = false ) {
        return $this->fs->chown( $file, $owner, $recursive = false );
    }

    /**
     * List files/directories.
     *
     * @param string|null $path
     * @return array|false
     */
    public function ls( $path = null ) {
        return $this->fs->dirlist( $path );
    }

    /**
     * Get file size.
     *
     * @param string $path Absolute path
     * @return int|false
     */
    public function filesize( $path ) {
       return $this->fs->size( $path );
    }

    /**
     * Get file info like stat().
     *
     * @param string $path Absolute path
     * @return array|false
     */
    public function stat( $path ) {
        if ( ! $path || ! $this->fs->exists( $path ) ) {
            return false;
        }

        return [
            'path'    => $path,
            'exists'  => true,
            'is_dir'  => $this->fs->is_dir( $path ),
            'is_file' => $this->fs->is_file( $path ),
            'size'    => $this->fs->size( $path ),
            'mtime'   => $this->fs->mtime( $path ),
            'perms'   => $this->fs->gethchmod( $path ),
        ];
    }

    /**
     * Output a file in chunks.
     *
     * @param string $path Absolute path
     * @param int    $start
     * @param int    $length
     * @param int    $chunk_size
     * @return bool
     */
    public function readfile( $path, $start = 0, $length = 0, $chunk_size = 1048576 ) {
        if ( ! $path || ! $this->exists( $path ) || ! $this->is_readable( $path ) ) {
            return false;
        }

        $handle = @fopen( $path, 'rb' );
        if ( ! $handle ) {
            return false;
        }

        $size   = $this->fs->size( $path );
        $start  = max( 0, (int) $start );
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
