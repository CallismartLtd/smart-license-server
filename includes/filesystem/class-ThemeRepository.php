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

use SimplePie\File;
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
     * - Post-upload: validates the theme style.css and metadata.
     *
     * @param array  $file      The uploaded file ($_FILES format).
     * @param string $new_name  The preferred filename (without path).
     * @param bool   $update    Whether this is an update to an existing theme.
     * @return string|Exception Relative path to stored ZIP on success, Exception on failure.
     */
    public function upload_zip( array $file, string $new_name = '', bool $update = false ) {
        // --- Core upload via Repository::safe_zip_upload() ---
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

        // --- Post-upload validation: Check style.css and metadata ---
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
     * Delete a theme from the repository.
     * 
     * @param string $slug The theme slug.
     * @return bool True on success, false on failure.
     */
    public function delete_from_repo( string $slug ): bool {
        try {
            $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return false;
        }

        return $this->rmdir( $this->path(), true );
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
     * Get the content of the theme style.css file.
     * 
     * @param string $slug The theme slug.
     * @return string Style.css contents or empty string if not found.
     */
    public function get_style_css( $slug ): string {
        $slug = $this->real_slug( $slug );

        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return '';
        }

        $style_path = FileSystemHelper::join_path( $base_dir, 'style.css' );

        if ( ! $this->exists( $style_path ) ) {
            // We attempt to read fom the zip file as a fallback.
            $zip_path   = $this->locate( $slug );

            if ( is_smliser_error( $zip_path ) ) {
                return '';
            }

            $zip = new \ZipArchive();
            if ( $zip->open( $zip_path ) !== true ) {
                return '';
            }

            $firstEntry = $zip->getNameIndex(0);
            $rootDir = explode('/', $firstEntry)[0];
            $style_css_index = $zip->locateName( $rootDir . '/style.css', \ZipArchive::FL_NOCASE );
            if ( false === $style_css_index ) {
                $zip->close();
                return '';
            }
            $style_contents = $zip->getFromIndex( $style_css_index );
            $zip->close();

            // cache the style.css for future use.
            if ( ! $this->put_contents( $style_path, $style_contents ) ) {
                // TODO: Log error?
            }

            return $style_contents;
        }

        return $this->get_contents( $style_path ) ?: '';
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

                // Normalize to WP-style lowercase keys (similar to plugin parser).
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