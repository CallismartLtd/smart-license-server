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

namespace SmartLicenseServer;

use \ZipArchive;
use SmartLicenseServer\Exception;
use SmartLicenseServer\Utils\MDParser;

use function SmartLicenseServer\Utils\md_parser;

defined( 'ABSPATH' ) || exit;

class ThemeRepository extends Repository {

    /**
     * The markdown parser
     * 
     * @var MDParser $parser
     */
    protected MDParser $parser;

    /**
     * Constructor.
     *
     * Always binds to the `themes` subdirectory.
     */
    public function __construct() {
        parent::__construct( 'themes' );
        $this->parser = md_parser();
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
    public function locate( $theme_slug ) {
        if ( empty( $theme_slug ) || ! is_string( $theme_slug ) ) {
            return new Exception(
                'invalid_slug',
                __( 'Theme slug must be a non-empty string.', 'smart-license-server' ),
                [ 'status' => 400 ]
            );
        }

        try {
            $slug = $this->real_slug( $theme_slug );
            $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new Exception(
                'invalid_slug',
                $e->getMessage(),
                [ 'status' => 400 ]
            );
        }

        // Theme files MUST be a .zip file named after the theme slug.
        $theme_file = $this->path( sprintf( '%s.zip', $slug ) );

        if ( ! $this->exists( $theme_file ) ) {
            return new Exception(
                'file_not_found',
                __( 'Theme file not found.', 'smart-license-server' ),
                [ 'status'=> 404]
            );
        }

        return $theme_file;
    }

    /**
     * Safely upload or update a theme ZIP in the repository.
     *
     * - Pre-upload: determines destination folder and slug.
     * - Core upload: uses Repository::store_zip() to move the file safely.
     * - Post-upload: validates ZIP and extracts readme.txt to plugin folder.
     *
     * @param array  $file      The uploaded file ($_FILES format).
     * @param string $new_name  The preferred filename (without path).
     * @param bool   $update    Whether this is an update to an existing plugin.
     * @return string|Exception Relative path to stored ZIP on success, Exception on failure.
     */
    public function upload_zip( array $file, string $new_name = '', bool $update = false ) {
        
    }

    /**
     * Abstract Implementation: Get theme assets as URLs.
     *
     * Theme assets usually include screenshots (screenshot.png, etc.).
     *
     * @param string $slug Theme slug.
     * @param string $type Asset type (e.g., 'screenshots').
     * @return array URLs of assets.
     */
    public function get_assets( string $slug, string $type ): array {
        // Concrete logic for theme screenshots, icons, etc. would go here.
        return [];
    }

    /**
     * Abstract Implementation: Get the absolute path to a given theme asset.
     *
     * @param string $slug      Theme slug.
     * @param string $filename  File name within the assets directory.
     * @return string|Exception Absolute path to asset or Exception if not found.
     */
    public function get_asset_path( string $slug, string $filename ) {
        $slug = $this->real_slug( $slug );
        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new Exception( 'invalid_dir', $e->getMessage(), [ 'status' => 400 ] );
        }

        // Themes primarily use the root directory for standard assets like screenshot.png
        $abs_path  = sanitize_and_normalize_path( trailingslashit( $base_dir ) . basename( $filename ) );

        if ( ! $this->exists( $abs_path ) ) {
            return new Exception(
                'asset_not_found',
                sprintf( 'Theme asset "%s" not found.', esc_html( $filename ) ),
                [ 'status' => 404 ]
            );
        }

        return $abs_path;
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
        // TODO: Implementation to read style.css from the zip, similar to get_readme_txt.
        return [];
    }
}