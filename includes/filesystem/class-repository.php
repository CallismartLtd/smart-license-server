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
     * The trash directory for queued deletions.
     * 
     * @var string
     */
    const TRASH_DIR = 'trash';

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
        $this->base_dir = wp_normalize_path( SMLISER_REPO_DIR );
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
        $real     = $this->full_path( $relative );

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
        return $this->full_path( $relative );
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
     * List contents of a ZIP archive (inside current slug dir).
     *
     * @param string $filename Relative filename.
     * @return array|Exception
     */
    public function list_zip_contents( $filename ) {
        $real = $this->full_path( $this->path( $filename ) );

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
     * Safely store a ZIP file in the repository.
     *
     * Does not handle folder creation or metadata extraction. Returns the stored path.
     *
     * @param string $from Absolute temporary file path.
     * @param string $to   Absolute destination file path.
     * @return string|\SmartLicenseServer\Exception Absolute path of stored ZIP or Exception on failure.
     */
    private function save_zip_file( $from, $to ) {
        // Ensure destination file name ends with .zip.
        $ext    = FileSystemHelper::get_extension( $to );
        if ( 'zip' !== strtolower( $ext ) ) {
            return new Exception( 'invalid_file_type', 'The file must have a .zip extension.', [ 'status' => 400 ] );
        }

        // Save the file to the destination.
        if ( ! $this->rename( $from, $to ) ) {
            return new Exception( 'file_saving_failed', 'Failed to save the uploaded ZIP file.', [ 'status' => 500 ] );
        }

        @$this->chmod( $to, FS_CHMOD_FILE );

        // Quick ZIP sanity check.
        if ( ! $this->is_valid_zip( $to ) ) {
            $this->delete( $to );
            return new Exception( 'zip_invalid', 'The uploaded file is not a valid ZIP archive.', [ 'status' => 400 ] );
        }

        return $to;
    }

    /**
     * Safely upload or update an application ZIP file in the repository.
     *
     * @param array  $file      The uploaded file ($_FILES format).
     * @param string $new_name  The preferred filename (without path).
     * @param bool   $update    Whether this is an update to an existing plugin.
     * @return string|\SmartLicenseServer\Exception Relative path to stored ZIP on success, Exception on failure.
     */
    protected function safe_zip_upload( array $file, string $new_name, bool $update = false ) {
        if ( empty( $file ) || ! isset( $file['tmp_name'], $file['name'] ) ) {
            return new Exception( 'invalid_file', 'No file uploaded.', [ 'status' => 400 ] );
        }

        if ( empty( $new_name ) ) {
            return new Exception( 'invalid_filename', 'The new filename cannot be empty.', [ 'status' => 400 ] );
        }

        $tmp_name = $file['tmp_name'] ?? '';

        if ( ! is_uploaded_file( $tmp_name ) ) {
            return new Exception( 'invalid_temp_file', 'The temporary file is not valid.', [ 'status' => 400 ] );
        }

        if ( 'zip' !== FileSystemHelper::get_canonical_extension( $tmp_name ) ) {
            return new Exception( 'invalid_file_type', 'IThe application archive file must be in ZIP format.', [ 'status' => 400 ] );
        }

        // Normalize filename
        $new_name  = FileSystemHelper::sanitize_filename( $new_name );

        try {
            $slug = $this->real_slug( $new_name );
        } catch ( \InvalidArgumentException $e ) {
            return new Exception( 'invalid_slug', $e->getMessage(), [ 'status' => 400 ] );
        }

        // Force the filename to strictly be "{slug}.zip".
        $file_name = "{$slug}.zip";

        // Build destination folder and file path
        try {
            $base_folder = $this->path( $slug );
            $dest_path   = FileSystemHelper::join_path( $base_folder, $file_name );
        } catch ( \RuntimeException $e ) {
            return new Exception( 'repo_error', $e->getMessage(), [ 'status' => 500 ] );
        }

        if ( ! $update ) {
            // New upload: prevent overwriting existing slug.
            if ( $this->is_dir( $base_folder ) ) {
                return new Exception(
                    'plugin_slug_exists',
                    sprintf( 'The slug "%s" is not available, you can change the plugin name and try again.', $slug ),
                    [ 'status' => 400 ]
                );
            }

            if ( ! $this->mkdir( $base_folder, FS_CHMOD_DIR ) ) {
                return new Exception( 'repo_error', 'Unable to create plugin directory.', [ 'status' => 500 ] );
            }
            
        } else {
            // Update: ensure slug folder and plugin already exists.
            if ( ! $this->is_dir( $base_folder ) && ! $this->mkdir( $base_folder, FS_CHMOD_DIR )) {
                return new Exception(
                    'plugin_not_found',
                    sprintf( 'The plugin slug "%s" does not exist in the repository, and attempt to create one failed.', $slug ),
                    [ 'status' => 404 ]
                );
            }
        }

        return $this->save_zip_file( $tmp_name, $dest_path );
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
     * Construct the full absolute path inside the repository.
     * 
     * @param $relative_path
     * @return string|false Absolute path or false on failure.
     */
    public function full_path( $relative_path ) {
        $cleaned = \sanitize_and_normalize_path( $relative_path );

        if ( is_smliser_error( $cleaned ) ) {
            return false;
        }

        return FileSystemHelper::join_path( $this->base_dir, $cleaned );
    }

    /**
     * Queues the given app slug for deletion from the repository.
     * 
     * @param string $slug The application slug.
     * @return bool True on success, false on failure.
     */
    public function queue_app_for_deletion( string $slug ) {
        $trash_dir = FileSystemHelper::join_path( $this->base_dir, self::TRASH_DIR );

        if ( ! $this->is_dir( $trash_dir ) && ! $this->mkdir( $trash_dir, FS_CHMOD_DIR, true ) ) {
            return false;
        }

        $app_dir    = $this->path( $slug );
        $app_type   = $this->current_dir;
        $destination = FileSystemHelper::join_path( $trash_dir, $app_type, $slug );
        
        if ( ! $this->is_dir( $destination ) && ! $this->mkdir( $destination, FS_CHMOD_DIR, true ) ) {
            return false;
        }

        return $this->rename( $app_dir, $destination );

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
        $htaccess_path    = FileSystemHelper::join_path( $repo_dir, '.htaccess' );
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