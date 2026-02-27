<?php
/**
 * Repository class for managing hosted application directories.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\UploadedFileCollection;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileSystemException;
use ZipArchive;

use function is_smliser_error, defined;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The repository class handles filesystem operations within the repository.
 *
 * Works only within allowed subdirectories (plugins, themes, software).
 * Provides safe IO, streaming, and ZIP file utilities.
 */
abstract class Repository {
    use FileSystemAwareTrait;

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
    protected $allowed_dirs = [ 'plugins', 'themes', 'software' ];

    /**
     * The trash directory for queued deletions.
     * 
     * @var string
     */
    const TRASH_DIR = '.trash';
    /**
     * Trash metadata filename.
     * 
     * @var string
     */
    const TRASH_METADATA_FILE = '.smliser_meta';

    /**
     * Allowed image extensions.
     */
    const ALLOWED_IMAGE_EXTENSIONS = [ 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif' ];

    /**
     * Allowed icon names.
     */
    const ALLOWED_ICON_NAMES    = [ 'icon-128x128', 'icon-256x256', 'icon' ];
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
     * @param bool $switch Whether to switch to the given dir immediately.
     */
    public function __construct( $dir, $switch = true ) {
        
        $this->base_dir = FileSystemHelper::sanitize_path( SMLISER_REPO_DIR );

        if ( $switch ) {
            $this->switch( $dir );
        }
    }

    /**
     * Switch to another allowed subdirectory.
     *
     * @param string $dir
     * @return void
     * @throws FileSystemException When illegal subdirectory is passed.
     */
    public function switch( $dir ) {
        if ( ! in_array( $dir, $this->allowed_dirs, true ) ) {
            throw new FileSystemException( sprintf(
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
     * @throws FileSystemException When the slug directory does not exist.
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
            throw new FileSystemException( sprintf(
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
     * @throws FileSystemException When there is no selected directory.
     */
    public function path( $filename = '' ) {
        if ( ! $this->current_dir ) {
            throw new FileSystemException( 'No subdirectory selected.' );
        }

        $parts = [ $this->current_dir ];
        if ( $this->current_slug ) {
            $parts[] = $this->current_slug;
        }
        if ( $filename ) {
            $parts[] = $filename;
        }

        $relative = implode( '/', $parts );

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

        return $this->fs()->list( $path );
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
     * @return array|FileSystemException
     */
    public function list_zip_contents( $filename ) {
        $real = $this->full_path( $this->path( $filename ) );

        if ( ! $real || ! $this->exists( $real ) ) {
            return new FileSystemException( __( 'ZIP file not found.', 'smart-license-server' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $real ) !== true ) {
            return new FileSystemException( __( 'Unable to open ZIP file.', 'smart-license-server' ) );
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
     * @return string|Exception Absolute path of stored ZIP or error on failure.
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
     * Safely upload or update an application ZIP file.
     *
     * @param UploadedFile  $file   The uploaded file.
     * @param string $new_name      The preferred filename (without path).
     * @param bool   $update        Whether this is an update to an existing app.
     * @return string|\SmartLicenseServer\Exception Relative path to stored ZIP on success, Exception on failure.
     */
    protected function safe_zip_upload( UploadedFile $file, string $new_name, bool $update = false ) {
        if ( ! $file->is_upload_successful() ) {
            return new Exception( 'invalid_file', 'No file uploaded.', [ 'status' => 400 ] );
        }

        if ( empty( $new_name ) ) {
            return new Exception( 'invalid_filename', 'The new filename cannot be empty.', [ 'status' => 400 ] );
        }

        if ( ! $file->is_uploaded_file() ) {
            return new Exception( 'invalid_temp_file', 'The temporary file is not valid.', [ 'status' => 400 ] );
        }

        if ( 'zip' !== $file->get_canonical_extension() ) {
            return new Exception( 'invalid_file_type', 'The application archive file must be in ZIP format.', [ 'status' => 400 ] );
        }

        // Normalize filename.
        $new_name  = FileSystemHelper::sanitize_filename( $new_name );

        try {
            $slug = $this->real_slug( $new_name );
        } catch ( FileSystemException $e ) {
            return new Exception( $e->get_error_code(), $e->get_error_message(), [ 'status' => 400 ] );
        }

        // Force the filename to strictly be "{slug}.zip".
        $file_name = "{$slug}.zip";

        // Build destination directory.
        try {
            $base_folder = $this->path( $slug );
            $dest_path   = FileSystemHelper::join_path( $base_folder, $file_name );

            if ( is_smliser_error( $dest_path ) ) {
                throw $dest_path;
            }
        } catch ( FileSystemException $e ) {
            return new Exception( $e->get_error_code(), $e->get_error_message(), [ 'status' => 500 ] );
        }

        if ( ! $update ) {
            // New upload: prevent overwriting existing slug.
            if ( $this->is_dir( $base_folder ) ) {
                return new Exception(
                    'app_slug_exists',
                    sprintf( 'The slug "%s" is not available, you can change the %s name and try again.', $slug, $this->current_slug ),
                    [ 'status' => 400 ]
                );
            }

            if ( ! $this->mkdir( $base_folder, FS_CHMOD_DIR ) ) {
                return new Exception( 'repo_error', \sprintf( 'Unable to create %s directory.', $this->current_slug ), [ 'status' => 500 ] );
            }
            
        } else {
            // Update: ensure slug folder and app already exists.
            if ( ! $this->is_dir( $base_folder ) && ! $this->mkdir( $base_folder, FS_CHMOD_DIR )) {
                return new Exception(
                    'app_not_found',
                    sprintf( 'The %s slug "%s" does not exist in the repository, and attempt to create one failed.', $this->current_slug, $slug ),
                    [ 'status' => 404 ]
                );
            }
        }

        return $this->save_zip_file( $file->get_tmp_path(), $dest_path );
    }

    /**
     * Safely upload or update application assets.
     * 
     * @param string                    $slug       The application slug.
     * @param UploadedFileCollection    $files      The uploaded file instances.
     * @param string                    $asset_type The type of asset(eg screenshots, icon, banners etc).
     * @return array{
     *      uploaded: array<
     *          string|int, array{
     *              app_slug: string,
     *              app_type: string,
     *              asset_name: string,
     *              asset_url: string
     *          }
     *      },
     *      
     *      failed: array{
     *          string|int, array
     *      }
     * }
     */
    public function safe_assets_upload( string $slug, UploadedFileCollection $files, string $asset_type ) : array {
        $result = [
            'uploaded'  => [],
            'failed'    => []
        ];

        try {
            $slug       = $this->real_slug( $slug );
            $base_path  = $this->enter_slug( $slug );
            $assets_dir = FileSystemHelper::join_path( $base_path, '/assets' );

            if ( ! $assets_dir ) {
                throw new FileSystemException( 'Malformed assets directory.' );
            }

            if ( ! $this->is_dir( $assets_dir ) && ! $this->mkdir( $assets_dir, FS_CHMOD_DIR, true ) ) {
                throw new FileSystemException( 'Unable to create asset directory.' );
            }
        } catch ( FileSystemException $e ) {
            $result['failed']['system'] = $e->get_error_message();

            return $result;
        }

        foreach ( $files->all() as $file ) {
            $has_error  = false;

            if ( ! $file->is_upload_successful() ) {
                $result['failed'][$file->get_name()]['partial_upload']   = 'Upload was not success.';
                $has_error                                      = true;
            }

            if ( ! $file->is_uploaded_file() ) {
                $result['failed'][$file->get_name()]['invalid_http_upload']   = 'File is not a valid HTTP uploaded file.';
                $has_error  = true;
            }

            if ( ! $file->is_moveable() ) {
                $result['failed'][$file->get_name()]['filesystem_error']   = 'Asset cannot be reliably moved to the repository.';
                $has_error  = true;
            }

            $asset_name = $this->validate_app_asset( $file, $asset_type, $assets_dir );

            if ( is_smliser_error( $asset_name ) ) {
                $result['failed'][$file->get_name()][$asset_name->get_error_code()]   = $asset_name->get_error_message();
                $has_error  = true;
            }

            if ( $has_error ) {
                continue;
            }

            try {
                $path       = $file->move( $assets_dir, $asset_name ); // No override.
                $app_type   = rtrim( $this->current_dir, 's' ); // "plugins" => "plugin"
                $app_slug   = $slug;
                $asset_name = basename( $path );
                $raw_url    = smliser_get_asset_url( $app_type, $app_slug, $asset_name );
                $asset_url  = ( new URL( $raw_url ) )
                ->add_query_param( 'ver', $this->filemtime( $path ) )->get_href();

                $result['uploaded'][$file->get_client_name()] = \compact( 'app_slug', 'app_type', 'asset_name', 'asset_url', 'asset_type' );
            } catch( Exception $e ) {
                $result['failed'][$file->get_client_name()][]   = $e->get_error_message();
            }

        }

        return $result;
    }

    /**
     * Safely put or replace an asset in the app's asset.
     * 
     * @param string $app_slug The app slug.
     * @param UploadedFile $file The type of asset.
     * @param string $asset_type
     */
    public function put_app_asset( string $app_slug, UploadedFile $file, string $asset_type ) : array|Exception {
        try {
            $app_slug   = $this->real_slug( $app_slug );
            $base_path  = $this->enter_slug( $app_slug );
            $assets_dir = FileSystemHelper::join_path( $base_path, '/assets' );

            if ( ! $assets_dir ) {
                throw new FileSystemException( 'Malformed assets directory.' );
            }

            if ( ! $this->is_dir( $assets_dir ) && ! $this->mkdir( $assets_dir, FS_CHMOD_DIR, true ) ) {
                throw new FileSystemException( 'Unable to create asset directory.' );
            }

            if ( ! $file->is_moveable() ) {
                throw new FileSystemException( $file->get_error_message() );
            }

            $asset_name = $this->validate_app_asset( $file, $asset_type, $assets_dir );

            if ( is_smliser_error( $asset_name ) ) {
                throw new FileSystemException( $asset_name->get_error_message() );
            }

            if ( 'screenshot' === $asset_type ) {
                $file_name  = $file->get_name( false );
                if ( \str_starts_with( $file_name, 'screenshot-' ) ) {
                    $removable  = FileSystemHelper::join_path( $assets_dir, $file_name );
                    $pattern    = sprintf( '%s.*{%s}', $removable, implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ) );
                    $identicals = glob( $pattern, \GLOB_BRACE );
                    array_map( [$this, 'delete'], (array) $identicals );
                }
            }

            $path       = $file->move( $assets_dir, $asset_name, true );
            $app_type   = $this->current_dir;
            $asset_name = basename( $path );
            $raw_url    = smliser_get_asset_url( $app_type, $app_slug, $asset_name );
            $asset_url  = ( new URL( $raw_url ) )
            ->add_query_param( 'ver', $this->filemtime( $path ) )->get_href();

            return compact( 'app_slug', 'app_type', 'asset_name', 'asset_url', 'asset_type' );
        } catch ( FileSystemException $e ) {
            return $e;
        }
    }

    /**
     * Normalize an app slug to get the first folder.
     *
     * @param string $slug Input like "plugin/plugin.zip or theme.zip"
     * @return string First folder name (slug)
     * @throws FileSystemException If slug is empty or contains invalid references
     */
    public function real_slug( $slug ) {
        $slug = trim( $slug );

        if ( empty( $slug ) ) {
            throw new FileSystemException( 'Invalid slug provided.' );
        }

        $parts      = explode( '/', $slug );
        $real_slug  = $parts[0];

        if ( false !== strpos( $real_slug, '.' ) ) {
            $real_slug = substr( $real_slug, 0, strpos( $real_slug, '.' ) );
        }

        return $real_slug;
    }

    /**
     * Validate image.
     * 
     * @param string $filename Absolute path to the image file.
     * @return bool|Exception
     */
    protected function is_valid_image( string $filename ) : bool|Exception {
        $ext    = FileSystemHelper::get_canonical_extension( $filename );

        if ( ! $ext ) {
            return new Exception( 'repo_error', 'The extension for the for this image could not be trusted.', [ 'status' => 400 ] );
        }

        if ( ! in_array( $ext, static::ALLOWED_IMAGE_EXTENSIONS, true ) ) {
            return new Exception(
                'file_ext_error',
                sprintf( 'Icon file must be one of: %s', implode( ', ', static::ALLOWED_IMAGE_EXTENSIONS ) ),
            );
        }

        $image_content  = $this->get_contents( $filename );
        
        if ( preg_match( '/<(script|php|eval|iframe|object|embed|form|input|button|link|style)[^>]*>/i', $image_content ) ) {
            return new Exception( 'malicious_content', 'Potentially malicious content detected in the image.' );
        }

        return true;
    }

    /**
     * Construct the full absolute path inside the repository.
     * 
     * @param $relative_path
     * @return string|false Absolute path or false on failure.
     */
    public function full_path( $relative_path ) {
        $cleaned = \smliser_sanitize_path( $relative_path );

        if ( is_smliser_error( $cleaned ) ) {
            return false;
        }

        return FileSystemHelper::join_path( $this->base_dir, $cleaned );
    }

    /**
     * Move an app to trash.
     * 
     * @param string $slug The app slug.
     * @return true|Exception True on success, Exception on failure.
     */
    public function trash( string $slug ) {
        if ( empty( $slug ) ) {
            return new Exception( 'invalid_slug', \sprintf( 'The %s slug cannot be empty', $this->current_slug ), ['status' => 400] );
        }

        $slug = $this->real_slug( $slug );
        
        if ( ! $this->queue_app_for_deletion( $slug ) ) {
            return new Exception(
                'deletion_failed',
                sprintf( 'Failed to queue %s "%s" for deletion.', $this->current_slug, $slug ),
                [ 'status' => 500 ]
            );
        }

        return true;
    }

    /**
     * Queue the given app slug for deletion from the repository.
     * Adds a timestamp for reliable later cleanup.
     *
     * @param string $slug The application slug.
     * @return bool True on success, false on failure.
     */
    public function queue_app_for_deletion( string $slug ) {
        // FileSystemHelper::join_path() will not join `.trash` with the base dir to prevent 
        // accidental misuse, so we concatenate manually here.
        $trash_dir = $this->base_dir . DIRECTORY_SEPARATOR . self::TRASH_DIR;

        $slug = $this->real_slug( $slug );

        // Ensure trash base exists.
        if ( ! $this->is_dir( $trash_dir ) && ! $this->mkdir( $trash_dir, FS_CHMOD_DIR, true ) ) {
            return false;
        }

        $app_dir     = $this->path( $slug );
        $app_type    = $this->current_dir;
        $destination = implode( DIRECTORY_SEPARATOR, [$trash_dir, $app_type, $slug] );

        // Ensure destination folder exists.
        if ( ! $this->is_dir( $destination ) && ! $this->mkdir( $destination, FS_CHMOD_DIR, true ) ) {
            return 'false';
        }

        // Move application files.
        if ( ! $this->move( $app_dir, $destination, true ) ) {
            return 'false';
        }

        // Write timestamp file for cleanup.
        $timestamp_file = implode( DIRECTORY_SEPARATOR, [$destination, self::TRASH_METADATA_FILE] );

        if ( ! $this->put_contents( $timestamp_file, (string) time() ) ) {
            return false;
        }

        return true;
    }

    /**
     * Restore an app (plugin/theme) from the trash.
     * 
     * @param string $slug The app slug.
     * @return true|Exception True on success, Exception on failure.
     */
    public function restore_from_trash( string $slug ) {
        if ( '' === $slug ) {
            return new Exception( 
                'invalid_slug', 
                'The application slug cannot be empty',
                [ 'status' => 400 ] 
            );
        }

        $slug = $this->real_slug( $slug );

        if ( ! $this->restore_queued_deletion( $slug ) ) {
            return new Exception(
                'restore_failed',
                sprintf( 'Failed to restore "%s" from trash.', self::safe_esc_html( $slug ) ),
                [ 'status' => 500 ]
            );
        }

        return true;
    }

    /**
     * Restore queued deletions.
     *
     * @param string $slug
     * @return bool True on success, false on failure.
     */
    public function restore_queued_deletion( string $slug ) {
        // FileSystemHelper::join_path() will not join `.trash` with the base dir to prevent 
        // accidental misuse, so we concatenate manually here.
        $type      = $this->current_dir;
        $trash_dir = implode( DIRECTORY_SEPARATOR, [$this->base_dir, self::TRASH_DIR, $type, $slug] );
        $dest_dir  = implode( DIRECTORY_SEPARATOR, [$this->base_dir, $type, $slug] );

        if ( ! $this->is_dir( $trash_dir ) ) {
            return false;
        }

        if ( $this->is_dir( $dest_dir ) ) {
            return false; // destination already exists
        }

        if ( ! $this->move( $trash_dir, $dest_dir, true ) ) {
            return false;
        }

        // Remove timestamp file if it exists.
        $timestamp_file = implode( DIRECTORY_SEPARATOR, [$dest_dir, self::TRASH_METADATA_FILE] );
        if ( $this->exists( $timestamp_file ) ) {
            $this->delete( $timestamp_file );
        }

        return true;
    }

    /**
     * Find the next screenshot name in a given directory.
     * 
     * Works by serching for `screenshot-{index}` in the given directory.
     * 
     * @param string $dir The directory to search.
     * @return string
     */
    public function find_next_screenshot_name( string $dir )  {
        $path           = FileSystemHelper::join_path( $dir, 'screenshot' );
        $pattern        = $path . '*.{' . implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ) . '}';
        $screenshots    = glob( $pattern, GLOB_BRACE );
        $indexes        = [];

        foreach ( $screenshots as $screenshot ) {
            if ( preg_match( '/screenshot-(\d+)\./', basename( $screenshot ), $m ) ) {
                $indexes[] = (int) $m[1];
            }
        }

        $next_index  = empty( $indexes ) ? 1 : ( max( $indexes ) + 1 );
        return sprintf( 'screenshot-%d', $next_index );
    }

    /**
    |---------------------------
    | ABSTRACT METHODS
    |---------------------------
    */

    /**
     * Locate the application ZIP file inside the repository.
     * 
     * @abstract
     * @param string $slug The application slug.
     * @return string|Exception Absolute path to the ZIP file or Exception if not found.
     */
    abstract public function locate( $slug ) : string| Exception;

    /**
     * Upload an application ZIP file to the repository.
     * 
     * @param UploadedFile $file The uploaded file.
     * @param string $new_name The preferred filename (without path).
     * @param bool   $update    Whether this is an update to an existing app.
     */
    abstract public function upload_zip( UploadedFile $file, string $new_name, bool $update = false );

    /**
     * Get the assets for a given hosted application.
     * 
     * @abstract
     * @param string $slug The application slug.
     * @param string $type The application type.
     */
    abstract public function get_assets( string $slug, string $type );

    /**
     * Get the content of an app.json json file
     * 
     * @param \SmartLicenseServer\HostedApps\AbstractHostedApp $app
     */
    abstract public function get_app_dot_json( \SmartLicenseServer\HostedApps\AbstractHostedApp $app );

    /**
     * Regenerate app.json file
     * 
     * @param \SmartLicenseServer\HostedApps\AbstractHostedApp
     * @return array
     */
    abstract public function regenerate_app_dot_json( \SmartLicenseServer\HostedApps\AbstractHostedApp $app ) : array;

    /**
     * Allows hosted app repository classes to validate app asset types.
     * 
     * @param UploadedFile $file  Uploaded file instance.
     * @param string        $type Asset type.
     * @param string        $dir  The current asset directory.
     * @return Exception|string Error or file name
     */
    abstract public function validate_app_asset( UploadedFile $file, string $type, string $dir ) : Exception|string;

    /**
    |---------------------------
    | SETTING UP THE REPOSITORY 
    |---------------------------
    */

    /**
     * Creates all necessary directories.
     *
     * @since 1.0.0
     *
     * @return true|Exception True on success, Exception instance on failure.
     */
    public static function make_default_directories() {
        $fs = FileSystem::instance();

        $directories = [
            'repository' => SMLISER_REPO_DIR,
            'plugin'     => SMLISER_PLUGINS_REPO_DIR,
            'theme'      => SMLISER_THEMES_REPO_DIR,
            'software'   => SMLISER_SOFTWARE_REPO_DIR,
            'trash'      => FileSystemHelper::join_path( SMLISER_REPO_DIR, self::TRASH_DIR ),
            'uploads'    => SMLISER_UPLOADS_DIR,
        ];

        $exception = new Exception();

        foreach ( $directories as $type => $dir ) {
            if ( ! $fs->is_dir( $dir ) ) {
                if ( ! $fs->mkdir( $dir ) ) {
                    $message = sprintf(
                        self::safe_translate( 'Failed to create %s directory: %s', 'smliser' ),
                        $type,
                        self::safe_esc_html( $dir )
                    );

                    $exception->add( 'directory_creation_failed', $message );
                    continue; // try creating the other directories.
                }

                // Set directory permissions.
                $fs->chmod( $dir, FS_CHMOD_DIR, true );
            }
        }

        // Protect the repository root.
        $protection = self::protect_dir( $directories['repository'], $fs );
        // Protect the uploads directory.
        $protection = self::protect_dir( $directories['uploads'], $fs );

        if ( is_smliser_error( $protection ) ) {
            $exception->merge_from( $protection );
        }

        return $exception->has_errors() ? $exception : true;
    }

    /**
     * Protects the given directory using an .htaccess file.
     *
     * @since 1.0.0
     *
     * @param string $dir Absolute path to the directory.
     * @param FileSystem $fs  FileSystem instance.
     *
     * @return bool|\SmartLicenseServer\Exception True on success, Exception instance on failure.
     */
    public static function protect_dir( string $dir, $fs ) {

        $htaccess_path = FileSystemHelper::join_path( $dir, '.htaccess' );

        $htaccess_content = implode(
            "\n",
            array(
                '# Prevent directory listing',
                'Options -Indexes',
                '',
                '# Disable PHP execution when supported',
                '<IfModule mod_php.c>',
                'php_flag engine off',
                '</IfModule>',
                '',
                '# Deny all direct access (Apache 2.4+)',
                '<IfModule mod_authz_core.c>',
                'Require all denied',
                '</IfModule>',
                '',
                '# Fallback for Apache 2.2',
                '<IfModule !mod_authz_core.c>',
                'Order deny,allow',
                'Deny from all',
                '</IfModule>',
                '',
            )
        );

        if ( ! $fs->exists( $htaccess_path ) ) {

            if ( ! $fs->put_contents( $htaccess_path, $htaccess_content, FS_CHMOD_FILE ) ) {

                $message = sprintf(
                    self::safe_translate( 'Failed to protect directory: %s', 'smliser' ),
                    self::safe_esc_html( $dir )
                );

                return new Exception( 'htaccess_protection_failed', $message );
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
    protected static function safe_esc_html( string $text ): string {
        return function_exists( 'esc_html' ) ? esc_html( $text ) : htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Get the absolute path to a given application asset.
     *
     * @param string $slug      App slug.
     * @param string $filename  File name within the assets directory.
     * @return string|Exception Absolute path to asset or Exception if not found.
     */
    public function get_asset_path( string $slug, string $filename ) {
        $slug = $this->real_slug( $slug );
        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return new Exception( 'invalid_dir', $e->get_error_message(), [ 'status' => 400 ] );
        }

        $filename   = FileSystemHelper::sanitize_filename( $filename );
        $asset_dir  = FileSystemHelper::join_path( $base_dir, 'assets', $filename );

        if ( ! $this->exists( $asset_dir ) ) {
            return new Exception(
                'asset_not_found',
                sprintf( 'Asset "%s" not found.', self::safe_esc_html( $filename ) ),
                [ 'status' => 404 ]
            );
        }

        return $asset_dir;
    }

    /**
     * Delete an app asset from the repository
     * 
     * @param string $slug     App slug (e.g., "my-plugin").
     * @param string $filename The filename to delete.
     *
     * @return true|Exception True on success, Exception on failure.
     */
    public function delete_asset( $slug, $filename ) {
        
        $original_filename  = $filename;
        $filename           = FileSystemHelper::remove_extension( $filename );

        foreach ( static::ALLOWED_IMAGE_EXTENSIONS as $ext ) {
            $file   = $filename . '.' . $ext;
            $path   = $this->get_asset_path( $slug, $file );

            if ( ! is_smliser_error( $path ) ) {
                break;
            }

        }

        if ( is_smliser_error( $path ) ) {
            if ( 'asset_not_found' === $path->get_error_code() ) {
                return new Exception(
                    'file_not_found',
                    sprintf( '%s was not found.', $original_filename ),
                    ['status' => 404]
                );
            }

            return $path;
        }

        if ( ! $this->delete( $path ) ) {
            return new Exception( 'unable_to_delete', sprintf( 'Unable to delete the file %s', $filename ), [ 'status', 500 ] );
        }

        return true;
    }
}