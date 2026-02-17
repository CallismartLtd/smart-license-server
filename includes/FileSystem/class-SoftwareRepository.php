<?php
/**
 * Software Repository
 *
 * Handles software-specific business logic within the repository.
 * Extends the abstract Repository to ensure secure, scoped file operations.
 *
 * @author  Callistus Nwachukwu
 * @since   0.2.0
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Utils\MDParser;
use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileSystemException;
use SmartLicenseServer\HostedApps\Software;
use ZipArchive;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Software repository class provides provides filesystem APIs to interact with hosted software in the repository.
 * 
 * Note: it does not represent a single hosted software @see \SmartLicenseServer\HostedApps\Software
 */
class SoftwareRepository extends Repository {
    use RepoFilesAwareTrait;
    /**
     * Markdown parser instance.
     * 
     * @var MDParser $parser
     */
    protected MDParser $parser;

    /**
     * Class constructor
     */
    public function __construct() {
        parent::__construct( 'software' );
        $this->parser = \smliser_md_parser();
    }

    /**
     * Locate the software ZIP file inside the repository.
     * 
     * @param string $slug The software slug.
     * @return string|Exception Absolute path to the ZIP file or Exception if not found.
     */
    public function locate( $slug ) : string| Exception {
        if ( empty( $slug ) || ! is_string( $slug ) ) {
            return new Exception(
                'invalid_slug',
                __( 'Software slug must be a non-empty string.', 'smliser' ),
                [ 'status' => 400 ]
            );
        }

        try {
            $slug = $this->real_slug( $slug );
            $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return new Exception(
                'invalid_slug',
                $e->get_error_message(),
                [ 'status' => 400 ]
            );
        }

        $software_zip   = $this->path( \sprintf( '%s.zip', $slug ) );

        if ( ! $this->exists( $software_zip ) ) {
            return new Exception(
                'software_not_found',
                __( 'The requested software package was not found in the repository.', 'smliser' ),
                [ 'status' => 404 ]
            );
        }

        return $software_zip;
    }

    /**
     * Upload a software ZIP file to the repository.
     * - Post update: validates readme.md presence in the ZIP.
     * 
     * @param UploadedFile $file    The uploaded file array from $_FILES.
     * @param string $new_name      Optional new name for the uploaded file (without .zip).
     * @param bool   $update        Whether this is an update to an existing software.
     * @return string|Exception     Relative path to stored ZIP on success, Exception on failure.
     */
    public function upload_zip( UploadedFile $file, string $new_name = '', bool $update = false ) {
        // -- Core upload.
        $stored_path = $this->safe_zip_upload( $file, $new_name, $update );

        if ( \is_smliser_error( $stored_path ) ) {
            return $stored_path;
        }

        // -- Post-upload validation for software ZIPs.
        $base_folder    = dirname( $stored_path );
        $slug           = basename( $stored_path, '.zip' );

        $cleanup_func = function() use ( $update, $stored_path, $base_folder ) {
            if ( $update ) {
                $this->delete( $stored_path ); // only remove bad zip.
            } else {
                $this->rmdir( $base_folder, true ); // remove new folder completely.
            }
        };

        // Post-upload validation: Check readme.md and metadata.
        $zip = new ZipArchive();
        if ( true !== $zip->open( $stored_path ) ) {
            $cleanup_func();
            return new Exception( 'zip_invalid', 'Uploaded ZIP file could not be opened.', [ 'status' => 400 ] );
        }

        $first_index    = $zip->getNameIndex( 0 );
        $rootDir        = explode( '/', $first_index )[0];
        $readme_index   = $zip->locateName( $rootDir . '/readme.md', ZipArchive::FL_NOCASE );

        if ( false === $readme_index ) {
            $zip->close();
            $cleanup_func();
            return new Exception( 'readme_missing', 'The uploaded software ZIP file is missing the required readme.md file.', [ 'status' => 400 ] );
        }

        $readme_contents    = $zip->getFromIndex( $readme_index );

        $zip->close();

        $readme_path = FileSystemHelper::join_path( $base_folder, 'readme.md' );

        if ( ! $this->put_contents( $readme_path, $readme_contents ) ) {
            $cleanup_func();
            return new Exception( 'readme_save_error', 'Failed to save readme.md after upload.', [ 'status' => 500 ] );
        }

        return $slug;
    }

    /**
     * Upload a software assets to the software repository.
     * 
     * @param string $slug Software slug.
     * @param array $file Uploaded file ($_FILES format).
     * @param string $type The type of asset (e.g. cover, 'screenshots').
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
        // Only icon, cover and screenshots are allowed.
        switch ( $type ) {
            case 'icon':
                $allowed_names = [ 'icon-128x128', 'icon-256x256', 'icon' ];
                if ( ! in_array( pathinfo( $file['name'], PATHINFO_FILENAME ), $allowed_names, true ) 
                    || ! in_array( $ext, static::ALLOWED_IMAGE_EXTENSIONS, true ) ) {
                    return new Exception(
                        'invalid_icon_name',
                        'Icon must follow these naming convention: icon, icon-128x128 or icon-256x256 and be a PNG, GIF, or SVG file.',
                        [ 'status' => 400 ]
                    );
                }
                $target_name = strtolower( pathinfo( $file['name'], PATHINFO_FILENAME ) . '.' . $ext );
                break;
            case 'cover':
                $target_name = 'cover.' . $ext;
                break;
            case 'screenshot':
                if ( ! in_array( $ext, static::ALLOWED_IMAGE_EXTENSIONS, true ) ) {
                    return new Exception(
                        'invalid_screenshot_type',
                        'Screenshots must be PNG, JPG, JPEG, GIF, WEBP or SVG.',
                        [ 'status' => 400 ]
                    );
                }

                if ( ! empty( $filename ) ) {
                    if ( preg_match( '/screenshot-(\d+)\./', $filename, $m ) ) {
                        $target_name = sprintf( 'screenshot-%d.%s', $m[1], $ext );
                    } else {
                        return new Exception(
                            'invalid_screenshot_name',
                            'Screenshots must be named screenshot-{index} with a valid image extension.',
                            [ 'status' => 400 ]
                        );
                    }
                } else {
                    // Auto-generate next index.
                    $existing = glob( $asset_dir . 'screenshot-*.' . implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ), GLOB_BRACE );
                    $indexes  = [];

                    foreach ( $existing as $shot ) {
                        if ( preg_match( '/screenshot-(\d+)\./', basename( $shot ), $m ) ) {
                            $indexes[] = (int) $m[1];
                        }
                    }

                    $next_index  = empty( $indexes ) ? 1 : ( max( $indexes ) + 1 );
                    $target_name = sprintf( 'screenshot-%d.%s', $next_index, $ext );
                }
                break;

            default:
                return new Exception( 'invalid_type', 'Unsupported asset type.', [ 'status' => 400 ] );
        }

        // Final destination
        $dest_path = FileSystemHelper::join_path( $asset_dir, $target_name );

        // Move uploaded file
        if ( ! $this->rename( $file['tmp_name'], $dest_path ) ) {
            return new Exception( 'move_failed', 'Failed to save uploaded asset.', [ 'status' => 500 ] );
        }
        @$this->chmod( $dest_path, FS_CHMOD_FILE );

    
        return smliser_get_asset_url( 'software', $slug, $target_name );
    }

    /**
     * Validate names and types of asset that can be uploaded for a software.
     * 
     * @param UploadedFile $file The uploaded file instance.
     * @param string $type       The asset type.
     * @return Exception|string On error, file name otherwise.
     */
    public function validate_app_asset( UploadedFile $file, string $type, string $asset_dir ) : Exception|string {
        $ext    = $file->get_canonical_extension();

        if ( ! $ext ) {
            return new Exception( 'repo_error', 'The extension for the for this image could not be trusted.', [ 'status' => 400 ] );
        }

        if ( ! in_array( $ext, static::ALLOWED_IMAGE_EXTENSIONS, true ) ) {
            return new Exception(
                'file_ext_error',
                sprintf( 'Icon file must be one of: %s', implode( ', ', static::ALLOWED_IMAGE_EXTENSIONS ) ),
            );
        }

        // Only icon, cover and screenshots are allowed.
        switch ( $type ) {
            case 'icon':
                $allowed    = in_array( $file->get_name( false ), static::ALLOWED_ICON_NAMES, true );
                if ( ! $allowed ) {
                    return new Exception(
                        'filename_error',
                        sprintf( 'Icon name must be one of: %s', implode( ', ', static::ALLOWED_ICON_NAMES ) )
                    );
                }

                return $file->get_name();
            case 'cover':
                return sprintf( 'cover.%s', $ext );
            case 'screenshot':
                if ( preg_match( '/screenshot-(\d+)\./', $file->get_name( false ), $m ) ) {
                    return sprintf( 'screenshot-%d.%s', $m[1], $ext );
                    
                } else {
                    // Auto-generate next index.
                    return sprintf( '%s.%s', $this->find_next_screenshot_name( $asset_dir ), $ext );
                }

            default:
                return new Exception( 'invalid_type', 'Unsupported asset type.', [ 'status' => 400 ] );
        }
    }

    /**
     * Get software assets as URL.
     * 
     * @param string $slug Software slug.
     * @param string $type Asset type (e.g., cover, screenshots).
     * @return string|array Asset URLs or Exception on failure.
     */
    public function get_assets( string $slug, string $type ) : string|array {
        $slug = $this->real_slug( $slug );

        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return ( 'cover' === $type ) ? '' : [];
        }

        $assets_dir = FileSystemHelper::join_path( $base_dir, 'assets/' );

        if ( ! $this->is_dir( $assets_dir ) ) {
             return ( 'cover' === $type ) ? '' : [];
        }

        $possible_exts  = static::ALLOWED_IMAGE_EXTENSIONS;

        switch ( $type ) {
            case 'icons':
                $icons = [];
                $possible_icons = [ 'icon-128x128', 'icon-256x256', 'icon' ];

                foreach ( $possible_icons as $icon_name ) {
                    $pattern = $assets_dir . $icon_name . '.{' . implode( ',', $possible_exts ) . '}';
                    $icon_files = glob( $pattern, GLOB_BRACE );

                    foreach ( $icon_files as $icon ) {
                        $icons[] = smliser_get_asset_url( 'software', $slug, basename( $icon ) );
                    }
                }

                return $icons;

            case 'cover':
                $cover_files    = glob( $assets_dir . 'cover.{' . implode( ',', $possible_exts ) . '}', GLOB_BRACE );
                if ( empty( $cover_files ) ) {
                    return '';
                }
                $cover          = \reset( $cover_files );
                return smliser_get_asset_url( 'software', $slug, basename( $cover ) );

            case 'screenshots':
                $screenshot_files = glob( $assets_dir . 'screenshot-*.{' . implode( ',', $possible_exts ) . '}', GLOB_BRACE );
                $screenshots = [];

                foreach ( $screenshot_files as $screenshot ) {
                    $screenshots[] = smliser_get_asset_url( 'software', $slug, basename( $screenshot ) );
                }

                return $screenshots;

            default:
                return [];
        }
    }

    /**
     * Get the app.json metadata for a software.
     * 
     * @param Software $software
     * @return array
     */
    public function get_app_dot_json( $software ) : array {
        $slug = $this->real_slug( $software->get_slug() );

        try {
            $base_dir =$this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return $this->regenerate_app_dot_json( $software );
        }

        $app_json_path = FileSystemHelper::join_path( $base_dir, 'app.json' );

        if ( ! $this->exists( $app_json_path ) ) {
            return $this->regenerate_app_dot_json( $software );
        }

        $contents   = $this->get_contents( $app_json_path );
        $data       = \json_decode( $contents, true );

        if ( \json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            return $data;
        }

        // Repair json file.
        return $this->regenerate_app_dot_json( $software );
    }

    /**
     * Build app.jon file.
     * 
     * @param Software $software
     * @return array
     */
    public function regenerate_app_dot_json( $software ) : array {
        $defaults   = [
            'name'                  => $software->get_name(),
            'slug'                  => $software->get_slug(),
            'version'               => $software->get_version(),
            'short_description'     => $software->get_short_description(),
        ];

        $manifest   = $software->get_manifest();
        $manifest   = $defaults + $manifest;

        $slug       = $this->real_slug( $software->get_slug() );
        $base_dir   = $this->enter_slug( $slug );
        $file_path  = FileSystemHelper::join_path( $base_dir, 'app.json' );

        $flags      = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $json       = \smliser_safe_json_encode( $manifest, $flags );

        $this->put_contents( $file_path, $json );

        return $manifest;

    }

    /**
     * Get description from readm.md file.
     * 
     * @param string $slug
     * @return string
     */
    public function get_description( $slug ) {
        return $this->get_readme( $slug );
    }

    /**
     * Get the value of faq key in the app.json file
     *
     * @param Software $software
     * @return string HTML markup of frequently asked questions.
     */
    public function get_faq( Software $software ) : string {
        $app_json = $this->get_app_dot_json( $software );
        $faq      = $app_json['faq'] ?? array();

        if ( empty( $faq ) || ! is_array( $faq ) ) {
            return '';
        }

        $html  = '<dl>';

        foreach ( $faq as $question => $answer ) {
            if ( ! is_string( $question ) || ! is_string( $answer ) ) {
                continue;
            }

            $html .= '<dt>';
            $html .= self::safe_esc_html( $question );
            $html .= '</dt>';

            $html .= '<dd>';
            $html .= self::safe_esc_html( $answer );
            $html .= '</dd>';
        }

        $html .= '</dl>';

        return $html;
    }

    /**
     * Get short description.
     * 
     * @param Software $software
     * @return string
     */
    public function get_short_description( Software $software ) : string {
        $app_json   = $this->get_app_dot_json( $software );

        return $app_json['short_description'] ?? '';
    }
}