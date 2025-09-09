<?php
/**
 * Repository class for managing application directories.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer;

use ZipArchive;

defined( 'ABSPATH' ) || exit;

/**
 * Repository handler.
 *
 * Works only within allowed subdirectories (plugins, themes, softwares).
 * Provides safe IO, streaming, and ZIP file utilities.
 */
class Repository extends FileSystem {

    /**
     * Repository base directory.
     *
     * @var string
     */
    protected $base_dir = '';

    /**
     * Allowed subdirectories in the repository.
     *
     * @var string[]
     */
    protected $allowed_dirs = [ 'plugins', 'themes', 'softwares' ];

    /**
     * Currently active subdirectory.
     *
     * @var string
     */
    protected $current_dir;

    /**
     * Currently active slug inside the subdir.
     *
     * @var string|null
     */
    protected $current_slug;

    /**
     * Constructor.
     *
     * @param string $dir One of the allowed directories.
     */
    public function __construct( $dir ) {
        parent::__construct();
        $this->base_dir = wp_normalize_path( SMLISER_NEW_REPO_DIR );
        $this->switch( $dir );
    }

    /**
     * Switch to another allowed subdirectory.
     *
     * @param string $dir
     * @return void
     * @throws \InvalidArgumentException
     */
    public function switch( $dir ) {
        if ( ! in_array( $dir, $this->allowed_dirs, true ) ) {
            throw new \InvalidArgumentException( sprintf(
                'Directory "%s" is not allowed. Allowed directories: %s',
                $dir,
                implode( ', ', $this->allowed_dirs )
            ) );
        }

        $this->current_dir  = $dir;
        $this->current_slug = null;
    }

    /**
     * Enter a slug directory inside the current subdir.
     *
     * @param string $slug Slug name (folder inside current repo subdir).
     * @return string Absolute path
     * @throws \InvalidArgumentException If the slug directory does not exist.
     */
    public function enter_slug( $slug ) {
        $slug = $this->real_slug( $slug );

        // Already in this slug? Skip
        if ( isset( $this->current_slug ) && $this->current_slug === $slug ) {
            return $this->path();
        }

        // Build relative path from base
        $relative = $this->current_dir . '/' . $slug;
        $real     = $this->real_path( $relative );

        if ( ! $real || ! $this->is_dir( $real ) ) {
            throw new \InvalidArgumentException( sprintf(
                'Slug directory "%s" does not exist in "%s" repository.',
                $slug,
                $this->current_dir
            ) );
        }

        $this->current_slug = $slug;
        return $real;
    }

    /**
     * Build an absolute path inside the current repo subdir and slug.
     *
     * @param string $filename Optional filename relative to current slug.
     * @return string
     * @throws \RuntimeException If no subdirectory is selected.
     */
    public function path( $filename = '' ) {
        if ( ! $this->current_dir ) {
            throw new \RuntimeException( 'No subdirectory selected.' );
        }

        $parts = [ $this->current_dir ];
        if ( $this->current_slug ) {
            $parts[] = $this->current_slug;
        }
        if ( $filename ) {
            $parts[] = $filename;
        }

        $relative = implode( '/', $parts );

        // Use FileSystem to get absolute path
        return $this->real_path( $relative );
    }

    /**
     * List contents of the current slug directory or given dir.
     *
     * @param string|null $filename Optional file or directory inside current slug.
     * @return array|false
     */
    public function list( $filename = null ) {
        $path = $filename ? $this->path( $filename ) : $this->path();

        if ( ! $path || ! $this->is_dir( $path ) ) {
            return false;
        }

        return $this->ls( $path );
    }

    /**
     * Read a file in chunks (inside current slug dir by default).
     *
     * @param string   $filename File name relative to current slug.
     * @param callable $callback Callback receives the chunk string.
     * @param int      $chunk_size Bytes per chunk.
     * @return bool
     */
    public function read_chunked( $filename, $callback, $chunk_size = 1048576 ) {
        $path = $this->path( $filename );
        $real = $this->real_path( $path );

        if ( ! $real || ! $this->fs->exists( $real ) ) {
            return false;
        }

        $handle = fopen( $real, 'rb' );
        if ( ! $handle ) {
            return false;
        }

        while ( ! feof( $handle ) ) {
            $chunk = fread( $handle, $chunk_size );
            if ( $chunk === false ) {
                break;
            }
            call_user_func( $callback, $chunk );
        }

        fclose( $handle );
        return true;
    }

    /* -------------------------------------------------------------------------
     * ZIP Utilities
     * ---------------------------------------------------------------------- */

    /**
     * Validate if a ZIP file can be opened safely.
     *
     * @param string $filename Relative filename inside current slug dir.
     * @return string|\WP_Error
     */
    public function validate_zip( $filename ) {
        $real = $this->real_path( $this->path( $filename ) );

        if ( ! $real || ! $this->fs->exists( $real ) ) {
            return new \WP_Error( 'zip_not_found', __( 'ZIP file not found.', 'smart-license-server' ), [ 'status' => 404 ] );
        }

        $zip = new ZipArchive();
        $res = $zip->open( $real );
        if ( $res === true ) {
            $zip->close();
            return $real;
        }

        return new \WP_Error( 'zip_invalid', __( 'Unable to open ZIP file.', 'smart-license-server' ), [ 'status' => 400 ] );
    }

    /**
     * List contents of a ZIP archive (inside current slug dir).
     *
     * @param string $filename Relative filename.
     * @return array|\WP_Error
     */
    public function list_zip_contents( $filename ) {
        $real = $this->real_path( $this->path( $filename ) );

        if ( ! $real || ! $this->fs->exists( $real ) ) {
            return new \WP_Error( 'zip_not_found', __( 'ZIP file not found.', 'smart-license-server' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $real ) !== true ) {
            return new \WP_Error( 'zip_invalid', __( 'Unable to open ZIP file.', 'smart-license-server' ) );
        }

        $files = [];
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $files[] = $zip->getNameIndex( $i );
        }

        $zip->close();
        return $files;
    }

    /**
     * Extract a single file from a ZIP archive into current slug dir.
     *
     * @param string $zip_filename ZIP filename relative to current slug.
     * @param string $file_inside Filename inside the ZIP.
     * @param string $dest_filename Destination filename relative to current slug.
     * @return bool|\WP_Error
     */
    public function extract_file( $zip_filename, $file_inside, $dest_filename ) {
        $real_zip = $this->real_path( $this->path( $zip_filename ) );

        if ( ! $real_zip || ! $this->fs->exists( $real_zip ) ) {
            return new \WP_Error( 'zip_not_found', __( 'ZIP file not found.', 'smart-license-server' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $real_zip ) !== true ) {
            return new \WP_Error( 'zip_invalid', __( 'Unable to open ZIP file.', 'smart-license-server' ) );
        }

        $stream = $zip->getStream( $file_inside );
        if ( ! $stream ) {
            $zip->close();
            return new \WP_Error( 'file_missing', __( 'File not found in ZIP.', 'smart-license-server' ) );
        }

        $contents = stream_get_contents( $stream );
        fclose( $stream );

        $written = $this->put( $dest_filename, $contents );
        $zip->close();

        return $written ? true : new \WP_Error( 'write_failed', __( 'Failed to write file.', 'smart-license-server' ) );
    }

    /**
     * Safely store a ZIP file in the repository.
     *
     * Does not handle folder creation or metadata extraction. Returns the stored path.
     *
     * @param string $tmp_path Temporary uploaded file path.
     * @param string $dest_path Absolute destination path where ZIP should be moved.
     * @return string|\WP_Error Absolute path of stored ZIP or WP_Error on failure.
     */
    public function store_zip( $tmp_path, $dest_path ) {
        // Check uploaded file
        if ( ! is_uploaded_file( $tmp_path ) ) {
            return new \WP_Error( 'invalid_temp_file', 'The temporary file is not valid.' );
        }

        // Ensure .zip extension
        $file_info = wp_check_filetype( $dest_path );
        if ( $file_info['ext'] !== 'zip' ) {
            return new \WP_Error( 'invalid_file_type', 'Only ZIP files are allowed.' );
        }

        // Move uploaded file
        if ( ! $this->rename( $tmp_path, $dest_path ) ) {
            return new \WP_Error( 'move_failed', 'Failed to move uploaded file to repository.' );
        }

        $this->chmod( $dest_path, FS_CHMOD_FILE );

        // Quick ZIP sanity check
        $zip = new \ZipArchive();
        if ( $zip->open( $dest_path ) !== true ) {
            $this->delete( $dest_path );
            return new \WP_Error( 'zip_invalid', 'Uploaded ZIP could not be opened.' );
        }
        $zip->close();

        return $dest_path;
    }

    /**
     * Normalize a plugin slug to get the first folder.
     *
     * @param string $slug Input like "plugin/plugin.zip"
     * @return string First folder name (slug)
     * @throws \InvalidArgumentException If slug is empty or contains invalid references
     */
    public function real_slug( $slug ) {
        $slug = trim( $slug );

        if ( empty( $slug ) ) {
            throw new \InvalidArgumentException( 'Invalid slug provided.' );
        }

        $parts      = explode( '/', $slug );
        $real_slug  = $parts[0];

        if ( false !== strpos( $real_slug, '.' ) ) {
            $real_slug = substr( $real_slug, 0, strpos( $real_slug, '.' ) );
        }

        return $real_slug;
    }

    /**
     * Get the real path
     * 
     * @param $relative_path
     */
    public function real_path( $relative_path ) {
        $cleaned = \sanitize_and_normalize_path( $relative_path );

        if ( is_wp_error( $cleaned ) ) {
            return false;
        }

        return trailingslashit( $this->base_dir ) . $cleaned;
    }
}