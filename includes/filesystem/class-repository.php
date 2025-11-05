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
 * The repository class handles filesystem operations within the repository.
 *
 * Works only within allowed subdirectories (plugins, themes, softwares).
 * Provides safe IO, streaming, and ZIP file utilities.
 */
abstract class Repository extends FileSystem {

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

        return $this->list( $path );
    }

    /* -------------------------------------------------------------------------
     * ZIP Utilities
     * ---------------------------------------------------------------------- */

    /**
     * Validate if a ZIP file can be opened safely.
     *
     * @param string $filename Relative filename inside current slug dir.
     * @return string|Exception
     */
    public function validate_zip( $filename ) {
        $real = $this->real_path( $this->path( $filename ) );

        if ( ! $real || ! $this->fs->exists( $real ) ) {
            return new Exception( 'zip_not_found', __( 'ZIP file not found.', 'smart-license-server' ), [ 'status' => 404 ] );
        }

        $zip = new ZipArchive();
        $res = $zip->open( $real );
        if ( $res === true ) {
            $zip->close();
            return $real;
        }

        return new Exception( 'zip_invalid', __( 'Unable to open ZIP file.', 'smart-license-server' ), [ 'status' => 400 ] );
    }

    /**
     * List contents of a ZIP archive (inside current slug dir).
     *
     * @param string $filename Relative filename.
     * @return array|Exception
     */
    public function list_zip_contents( $filename ) {
        $real = $this->real_path( $this->path( $filename ) );

        if ( ! $real || ! $this->fs->exists( $real ) ) {
            return new Exception( 'zip_not_found', __( 'ZIP file not found.', 'smart-license-server' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $real ) !== true ) {
            return new Exception( 'zip_invalid', __( 'Unable to open ZIP file.', 'smart-license-server' ) );
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
     * @return bool|Exception
     */
    public function extract_file( $zip_filename, $file_inside, $dest_filename ) {
        $real_zip = $this->real_path( $this->path( $zip_filename ) );

        if ( ! $real_zip || ! $this->fs->exists( $real_zip ) ) {
            return new Exception( 'zip_not_found', __( 'ZIP file not found.', 'smart-license-server' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $real_zip ) !== true ) {
            return new Exception( 'zip_invalid', __( 'Unable to open ZIP file.', 'smart-license-server' ) );
        }

        $stream = $zip->getStream( $file_inside );
        if ( ! $stream ) {
            $zip->close();
            return new Exception( 'file_missing', __( 'File not found in ZIP.', 'smart-license-server' ) );
        }

        $contents = stream_get_contents( $stream );
        fclose( $stream );

        $written = $this->put_contents( $dest_filename, $contents );
        $zip->close();

        return $written ? true : new Exception( 'write_failed', __( 'Failed to write file.', 'smart-license-server' ) );
    }

    /**
     * Safely store a ZIP file in the repository.
     *
     * Does not handle folder creation or metadata extraction. Returns the stored path.
     *
     * @param string $tmp_path Temporary uploaded file path.
     * @param string $dest_path Absolute destination path where ZIP should be moved.
     * @return string|Exception Absolute path of stored ZIP or Exception on failure.
     */
    public function store_zip( $tmp_path, $dest_path ) {
        // Check uploaded file
        if ( ! is_uploaded_file( $tmp_path ) ) {
            return new Exception( 'invalid_temp_file', 'The temporary file is not valid.' );
        }

        // Ensure .zip extension
        $file_info = wp_check_filetype( $dest_path );
        if ( $file_info['ext'] !== 'zip' ) {
            return new Exception( 'invalid_file_type', 'Only ZIP files are allowed.' );
        }

        // Move uploaded file
        if ( ! $this->rename( $tmp_path, $dest_path ) ) {
            return new Exception( 'move_failed', 'Failed to move uploaded file to repository.' );
        }

        $this->chmod( $dest_path, FS_CHMOD_FILE );

        // Quick ZIP sanity check
        $zip = new \ZipArchive();
        if ( $zip->open( $dest_path ) !== true ) {
            $this->delete( $dest_path );
            return new Exception( 'zip_invalid', 'Uploaded ZIP could not be opened.' );
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

        if ( is_smliser_error( $cleaned ) ) {
            return false;
        }

        return trailingslashit( $this->base_dir ) . $cleaned;
    }

    /**
     * Check whether the given file is a valid zip file.
     * 
     * @param string $path Absolute path
     * @return bool
     */
    public function is_valid_zip( $path ) {
        $zip = new \ZipArchive();
        $res = $zip->open( $path );

        if ( true === $res ) {
            $zip->close();
            return true;
        }

        return false;
    }

    /**
     * Get the assets for a given hosted application.
     * 
     * @abstract
     * @param string $slug The application slug.
     * @param string $type The application type.
     */
    abstract public function get_assets( string $slug, string $type );

    /**
     * Get the path to a given hosted application.
     * 
     * @abstract
     * @param string $slug The application slug.
     * @param string $filename The filename inside the application directory.
     * @return string|Exception The asset path or Exception on failure.
     */
    abstract public function get_asset_path( string $slug, string $filename );

    /**
    |---------------------------
    | SETTING UP THE REPOSITORY 
    |---------------------------
    */

    /**
     * Creates the Smart License Server repository directories
     * and ensures they are properly secured.
     *
     * @since 1.0.0
     *
     * @return true|\SmartLicenseServer\Exception True on success, Exception instance on failure.
     */
    public static function create_repository_directories() {
        $fs = FileSystem::instance();

        $directories = [
            'repository' => SMLISER_NEW_REPO_DIR,
            'plugin'     => SMLISER_PLUGINS_REPO_DIR,
            'theme'      => SMLISER_THEMES_REPO_DIR,
            'software'   => SMLISER_SOFTWARE_REPO_DIR,
        ];

        $exception = new \SmartLicenseServer\Exception();

        foreach ( $directories as $type => $dir ) {
            if ( ! $fs->is_dir( $dir ) ) {
                if ( ! $fs->mkdir( $dir ) ) {
                    $message = sprintf(
                        self::safe_translate( 'Failed to create %s directory: %s', 'smliser' ),
                        $type,
                        self::safe_esc_html( $dir )
                    );

                    $exception->add( 'directory_creation_failed', $message );
                    continue; // try creating the other directories
                }

                // Set directory permissions.
                $fs->chmod( $dir, FS_CHMOD_DIR, true );
            }
        }

        // Protect the repository root.
        $protection = self::protect_repository_directory( $directories['repository'], $fs );

        if ( is_smliser_error( $protection ) ) {
            $exception->merge_from( $protection );
        }

        return $exception->has_errors() ? $exception : true;
    }


    /**
     * Protects the given repository directory using an .htaccess file.
     *
     * @since 1.0.0
     *
     * @param string $repo_dir Absolute path to the repository directory.
     * @param object $fs       FileSystem instance.
     *
     * @return bool|\SmartLicenseServer\Exception True on success, Exception instance on failure.
     */
    public static function protect_repository_directory( string $repo_dir, $fs ) {
        $htaccess_path    = trailingslashit( $repo_dir ) . '.htaccess';
        $htaccess_content = "Deny from all";

        if ( ! $fs->exists( $htaccess_path ) ) {
            if ( ! $fs->put_contents( $htaccess_path, $htaccess_content, FS_CHMOD_FILE ) ) {
                $message = sprintf(
                    self::safe_translate( 'Failed to protect repository directory: %s', 'smliser' ),
                    self::safe_esc_html( $repo_dir )
                );

                return new \SmartLicenseServer\Exception( 'htaccess_protection_failed', $message );
            }
        }

        return true;
    }

    /**
     * Safely translate a string even outside WordPress.
     *
     * @since 1.0.0
     *
     * @param string $text Text to translate.
     * @param string $domain Text domain.
     * @return string Translated or raw text.
     */
    private static function safe_translate( string $text, string $domain = 'default' ): string {
        return function_exists( '__' ) ? __( $text, $domain ) : $text;
    }

    /**
     * Safely escape a string even outside WordPress.
     *
     * @since 1.0.0
     *
     * @param string $text Text to escape.
     * @return string Escaped or raw text.
     */
    private static function safe_esc_html( string $text ): string {
        return function_exists( 'esc_html' ) ? esc_html( $text ) : htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}