<?php
/**
 * Theme Repository
 *
 * Handles theme-specific business logic within the repository.
 * Extends the abstract Repository to ensure secure, scoped file operations.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Core\UploadedFile;
use \ZipArchive;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileSystemException;
use SmartLicenseServer\Utils\MDParser;

defined( 'SMLISER_ABSPATH' ) || exit;

class ThemeRepository extends Repository {
    use WPRepoUtils, RepoFilesAwareTrait;

    /**
     * The markdown parser
     * 
     * @var MDParser $parser
     */
    protected MDParser $parser;

    /**
     * Allowed additional screenshots file extensions.
     */
    const ALLOWED_SCREENSHOTS_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];

    /**
     * Constructor.
     *
     * Always binds to the `themes` subdirectory.
     */
    public function __construct() {
        parent::__construct( 'themes' );
        $this->parser = \smliser_md_parser();
    }

    /**
     * Locate a theme zip file in the repository and enter the theme slug directory.
     *
     * This method ensures:
     * 1. The slug is valid and normalized.
     * 2. The theme directory exists and is entered (sandboxing the scope).
     * 3. The theme ZIP file ({slug}.zip) exists inside that directory.
     *
     * @param string $theme_slug The theme slug (e.g., "my-awesome-theme").
     * @return string|Exception Absolute file path to the ZIP or Exception on failure.
     */
    public function locate( $theme_slug ) : string| Exception {
        if ( empty( $theme_slug ) || ! is_string( $theme_slug ) ) {
            return new Exception(
                'invalid_slug',
                __( 'Theme slug must be a non-empty string.', 'smliser' ),
                [ 'status' => 400 ]
            );
        }

        try {
            $slug = $this->real_slug( $theme_slug );
            $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return new Exception(
                'invalid_slug',
                $e->get_error_message(),
                [ 'status' => 400 ]
            );
        }

        // Theme files MUST be a .zip file named after the theme slug.
        $theme_file = $this->path( sprintf( '%s.zip', $slug ) );

        if ( ! $this->exists( $theme_file ) ) {
            return new Exception(
                'file_not_found',
                __( 'Theme file not found.', 'smliser' ),
                [ 'status'=> 404]
            );
        }

        return $theme_file;
    }

    /**
     * Safely upload or update a theme ZIP in the repository.
     *
     * - Post-upload: validates the theme style.css and metadata.
     *
     * @param UploadedFile  $file      The uploaded file instance.
     * @param string $new_name  The preferred filename (without path).
     * @param bool   $update    Whether this is an update to an existing theme.
     * @return string|Exception Relative path to stored ZIP on success, Exception on failure.
     */
    public function upload_zip( UploadedFile $file, string $new_name = '', bool $update = false ) {
        // Core upload via `Repository::safe_zip_upload()`.
        $stored_path = $this->safe_zip_upload( $file, $new_name, $update );
        if ( is_smliser_error( $stored_path ) ) {
            return $stored_path;
        }

        $base_folder    = dirname( $stored_path );
        $slug           = basename( $stored_path, '.zip' );

        $cleanup_func = function() use ( $update, $stored_path, $base_folder ) {
            if ( $update ) {
                $this->delete( $stored_path ); // only remove bad zip.
            } else {
                $this->rmdir( $base_folder, true ); // remove new folder completely.
            }
        };

        // Post-upload validation: Check style.css and metadata.
        $zip = new ZipArchive();

        if ( true !== $zip->open( $stored_path ) ) {
            $cleanup_func();
            return new Exception( 'zip_invalid', 'Uploaded ZIP could not be opened.', [ 'status' => 400 ] );
        }
        $firstEntry         = $zip->getNameIndex(0);
        $rootDir            = explode( '/', $firstEntry )[0];
        $style_css_index    = $zip->locateName( $rootDir . '/style.css', \ZipArchive::FL_NOCASE );

        if ( false === $style_css_index ) {
            $zip->close();
            $cleanup_func();
            return new Exception( 'style_missing', 'The theme zip file must contain a style.css file', [ 'status' => 400 ] );
        }

        $style_css_content = $zip->getFromIndex( $style_css_index );
        $zip->close();

        $style_css_path = FileSystemHelper::join_path( $base_folder, 'style.css' );

        if ( ! $this->put_contents( $style_css_path, $style_css_content ) ) {
            $cleanup_func();
            return new Exception( 'style_css_save_faild', 'Could not save the theme style.css file', [ 'status' => 500 ] );
        }

        return $slug;
    }

    /**
     * Upload a themes' assets (eg. screenshot, screenshots) to the theme repository.
     * 
     * @param string $slug Theme slug.
     * @param array $file Uploaded file ($_FILES format).
     * @param string $type The type of asset (e.g., 'screenshots').
     * @param string $filename Desired filename within the asset type directory.
     * @return string|Exception Relative path to stored asset on success, Exception on failure.
     */
    public function upload_asset( string $slug, array $file, string $type, string $filename = '' ) {
        // Validate upload
        if ( ! is_uploaded_file( $file['tmp_name'] ?? '' ) ) {
            return new Exception( 'invalid_upload', 'Invalid uploaded file.', [ 'status' => 400 ] );
        }

        if ( ! FileSystemHelper::is_image( $file['tmp_name'] ) ) {
            return new Exception( 'invalid_type', 'Only images are allowed.', [ 'status' => 400 ] );
        }

        $slug = $this->real_slug( $slug );

        // Enter theme slug directory.
        try {
            $path = $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return new Exception( 
                'invalid_slug', 
                $e->get_error_message(),
                [ 'status' => 400 ] 
            );
        }

        $asset_dir = FileSystemHelper::join_path( $path, 'assets/' );

        if ( ! $this->is_dir( $asset_dir ) && ! $this->mkdir( $asset_dir, FS_CHMOD_DIR, true ) ) {
            return new Exception( 'repo_error', 'Unable to create asset directory.', [ 'status' => 500 ] );
        }

        $ext    = FileSystemHelper::get_canonical_extension( $file['tmp_name'] );

        if ( ! $ext ) {
            return new Exception( 'repo_error', 'The extension for the for this image could not be trusted.', [ 'status' => 400 ] );
        }

        // Upload individual asset type.
        // We support main screenshot.png and additional screenshots.
        switch ( $type ) {
            case 'screenshot':
                // Only png image is allowed for main screenshot.
                if ( 'png' !== strtolower( $ext ) ) {
                    return new Exception( 'invalid_type', 'The main screenshot must be a PNG image.', [ 'status' => 400 ] );
                }

                $filename = 'screenshot.png';
                break;
            case 'additional_screenshots':
                $allowed_exts = self::ALLOWED_SCREENSHOTS_EXT;
                if ( ! in_array( strtolower( $ext ), $allowed_exts, true ) ) {
                    return new Exception( 'invalid_type', 'Invalid image type for additional screenshot.', [ 'status' => 400 ] );
                }

                if ( ! empty( $filename ) ) {
                    if ( preg_match( '/screenshot-(\d+)\./', $filename, $m ) ) {
                        $filename = sprintf( 'screenshot-%d.%s', $m[1], $ext );
                    } else {
                        return new Exception(
                            'invalid_screenshot_name',
                            'Screenshots must be named screenshot-{index} with a valid image extension.',
                            [ 'status' => 400 ]
                        );
                    }
                } else {
                    // Auto-generate next index.
                    $pattern        = FileSystemHelper::join_path( $asset_dir, 'screenshot-*.{' . implode( ',', $allowed_exts ) . '}' );
                    $screenshots    = glob( $pattern, GLOB_BRACE );
                    $indexes        = [];

                    foreach ( $screenshots as $screenshot ) {
                        if ( preg_match( '/screenshot-(\d+)\./', basename( $screenshot ), $m ) ) {
                            $indexes[] = (int) $m[1];
                        }
                    }

                    $next_index  = empty( $indexes ) ? 1 : ( max( $indexes ) + 1 );
                    $filename = sprintf( 'screenshot-%d.%s', $next_index, $ext );

                }
                break;

            default:
                return new Exception( 'invalid_type', 'Only screenshot.png and additional theme screenshot images are allowed', [ 'status' => 400 ] );
        }

        $target_path = FileSystemHelper::join_path( $asset_dir, $filename );

        if ( ! $this->rename( $file['tmp_name'], $target_path ) ) {
            return new Exception( 'upload_failed', 'Failed to move uploaded file.', [ 'status' => 500 ] );
        }

        @$this->chmod( $target_path, FS_CHMOD_FILE );

        return smliser_get_asset_url( 'theme', $slug, \basename( $target_path ) );
        
    }

    /**
     * Validate names and types of asset that can be uploaded for a theme.
     * 
     * @param UploadedFile $file The uploaded file instance.
     * @param string $type       The asset type.
     * @return FileSystemException|string On error, file name otherwise.
     */
    public function validate_app_asset( UploadedFile $file, string $type, string $asset_dir ) : FileSystemException|string {
        return '';
    }

    /**
     * Abstract Implementation: Get theme assets as URLs.
     *
     * Theme assets includes screenshots (screenshot.png, etc.).
     *
     * @param string $slug Theme slug.
     * @param string $type Asset type (e.g., 'screenshots').
     * @return string|array URLs of assets.
     */
    public function get_assets( string $slug, string $type ) {
        // Normalize slug and ensure it is valid inside the repo.
        $slug = $this->real_slug( $slug );

        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return ( 'screenshot' === $type ) ? '' : [];
        }

        $assets_dir = FileSystemHelper::join_path( $base_dir, 'assets/' );

        if ( ! $this->is_dir( $assets_dir ) ) {
            return ( 'screenshot' === $type ) ? '' : [];
        }

        switch ( $type ) {
            case 'screenshot':
                $screenshot = FileSystemHelper::join_path( $assets_dir, 'screenshot.png' );

                if ( $this->exists( $screenshot ) ) {
                    return \smliser_get_asset_url( 'theme', $slug, \basename( $screenshot ) );
                }

                return '';

            case 'additional_screenshots':
                $pattern        = FileSystemHelper::join_path( $assets_dir, 'screenshot-*.{' . implode( ',', self::ALLOWED_SCREENSHOTS_EXT ) . '}' );
                $screenshots    = glob( $pattern, GLOB_BRACE );
                
                usort( $screenshots, function( $a, $b ) {
                    return strnatcmp( basename( $a ), basename( $b ) );
                } );
                
                $urls   = [];

                foreach ( $screenshots as $screenshot ) {
                    $name   = basename( $screenshot );

                    $url    = \smliser_get_asset_url( 'theme', $slug, $name );

                    if ( preg_match( '/screenshot-(\d+)\./i', $name, $m ) ) {
                       $urls[ (int) $m[1] ] = $url;
                    } else {
                        $urls[] = $url;
                    }
                }

                ksort( $urls );

                return $urls;
                break;

            default:
                return [];
        }
    }

    /**
     * Get the theme descriptions.
     * 
     * @return string The theme description in HTML format.
     */
    public function get_description( $slug ) : string {
        $metadata = $this->get_metadata( $slug );

        if ( ! isset( $metadata['description'] ) ) {
            return '';
        }

        return $this->parser->parse( $metadata['description'] );
    }

    /**
     * Get short description.
     * 
     * @return string
     */
    public function get_short_description( $slug ) {
        return \substr( $this->get_description( $slug ), 0, 800 );
    }

    /**
     * Get the content of the theme style.css file.
     * 
     * @param string $slug The theme slug.
     * @return string Style.css contents or empty string if not found.
     */
    public function get_style_css( $slug ): string {
        return $this->file_get_contents( $slug, 'style.css' );
    }
    
    /**
     * Get theme metadata from the style.css header.
     *
     * This method would parse the style.css file for theme headers (Theme Name, Version, etc.)
     *
     * @param string $slug The theme slug.
     * @return array<string, mixed>
     */
    
    public function get_metadata( $slug ): array {
        $style_contents = $this->get_style_css( $slug );

        if ( ! $style_contents ) {
            return [];
        }

        $metadata = [];
        $lines    = preg_split( '/\r\n|\r|\n/', $style_contents );

        /**
         * Expected header block looks like:
         *
         * /*
         * Theme Name: My Theme
         * Description: Example theme
         * Version: 1.0
         * Tags: blog, clean, minimal
         * * /
         */

        $header_started = false;

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // Detect beginning of comment block.
            if ( ! $header_started && preg_match( '/^\/\*/', $line ) ) {
                $header_started = true;
                continue;
            }

            // Stop when comment block closes.
            if ( $header_started && preg_match( '/\*\//', $line ) ) {
                break;
            }

            if ( ! $header_started || '' === $line ) {
                continue;
            }

            /*
            * Parse key: value fields
            * Example:
            *   Theme Name: Smart Portal
            *   Version: 1.0.0
            *   Tags: dashboard, smart-woo
            */
            if ( preg_match( '/^([^:]+):\s*(.+)$/', $line, $matches ) ) {

                $raw_key = trim( $matches[1] );
                $value   = trim( $matches[2] );

                $normalized_key = strtolower(
                    str_replace( ' ', '_', $raw_key )
                );

                switch ( $normalized_key ) {
                    case 'tags':
                        $metadata[ $normalized_key ] = array_map(
                            'trim',
                            explode( ',', $value )
                        );
                        break;

                    default:
                        $metadata[ $normalized_key ] = $value;
                }
            }
        }

        return $metadata;
    }

}