<?php
/**
 * Plugin Repository
 *
 * Handles plugin-specific business logic within the repository.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer;

use SmartLicenseServer\Utils\MDParser;
use SmartLicenseServer\FileSystemHelper;
use function SmartLicenseServer\Utils\md_parser;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin repository class provides filesystem APIs to interact with hosted plugins in the repository.
 * 
 * Note: it does not represent a single hosted plugin @see \SmartLicenseServer\HostedApps\Plugin
 */
class PluginRepository extends Repository {

    /**
     * Our readme parser class.
     * 
     * @var MDParser $parser
     */
    protected $parser;

    /**
     * Constructor.
     *
     * Always bind to the `plugins` subdirectory.
     */
    public function __construct() {
        parent::__construct( 'plugins' );
        $this->parser = md_parser();
    }
    
    /**
     * Locate a plugin zip file in the repository and enter the plugin slug.
     *
     * @param string $plugin_slug The plugin slug (e.g., "my-plugin").
     * @return string|Exception Absolute file path or Exception on failure.
     */
    public function locate( $plugin_slug ) {
        if ( empty( $plugin_slug ) || ! is_string( $plugin_slug ) ) {
            return new Exception( 
                'invalid_slug', 
                __( 'Plugin slug must be a non-empty string.', 'smart-license-server' ),
                [ 'status' => 400 ] 
            );
        }

        try {
            $slug = $this->real_slug( $plugin_slug );
            $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new Exception( 
                'invalid_slug', 
                $e->getMessage(),
                [ 'status' => 400 ] 
            );
        }

        // Plugin files MUST be a .zip file named after the plugin slug.
        $plugin_file = $this->path( sprintf( '%s.zip', $slug ) );

        if ( ! $this->exists( $plugin_file ) ) {
            return new Exception(
                'file_not_found',
                __( 'Plugin file not found.', 'smart-license-server' ),
                [ 'status'=> 404]
            );
        }        

        return $plugin_file;
    }

    /**
     * Safely upload or update a plugin ZIP in the repository.
     *
     * - Post-upload: validates ZIP and extracts readme.txt to plugin folder.
     *
     * @param array  $file      The uploaded file ($_FILES format).
     * @param string $new_name  The preferred filename (without path).
     * @param bool   $update    Whether this is an update to an existing plugin.
     * @return string|Exception Relative path to stored ZIP on success, Exception on failure.
     */
    public function upload_zip( array $file, string $new_name, bool $update = false ) {
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

        // --- Post-upload: Validate ZIP and extract readme.txt ---
        $zip = new \ZipArchive();
        if ( $zip->open( $stored_path ) !== true ) {
            $cleanup_func();        
            return new Exception( 'zip_invalid', 'Uploaded ZIP could not be opened.', [ 'status' => 400 ] );
        }

        $firstEntry     = $zip->getNameIndex(0);
        $rootDir        = explode( '/', $firstEntry )[0];
        $readme_index   = $zip->locateName( $rootDir . '/readme.txt', \ZipArchive::FL_NOCASE );
        if ( $readme_index === false ) {
            $zip->close();
            $cleanup_func();
            return new Exception( 'readme_missing', 'The plugin ZIP must contain a readme.txt file.', [ 'status' => 400 ] );
        }

        $readme_contents = $zip->getFromIndex( $readme_index );
        $zip->close();

        $readme_path = FileSystemHelper::join_path( $base_folder, 'readme.txt' );

        if ( ! $this->put_contents( $readme_path, $readme_contents ) ) {
            $cleanup_func();
            return new Exception( 'readme_save_failed', 'Failed to save readme.txt.', [ 'status' => 500 ] );
        }

        return $slug;
    }

    /**
     * Upload an asset (banner, icon, or screenshot) for a plugin.
     *
     * @param string $slug     Plugin slug (e.g., "my-plugin").
     * @param array  $file     Uploaded file ($_FILES format).
     * @param string $type     Asset type: 'banner', 'icon', 'screenshot'.
     * @param string $filename Optional specific filename (for replacing/updating).
     *
     * @return string|Exception Relative path to stored asset or Exception on failure.
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

        // Attempt to enter the plugin slug dir.
        try {
            $path = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new Exception( 
                'invalid_slug', 
                $e->getMessage(),
                [ 'status' => 400 ] 
            );
        }

        $asset_dir = trailingslashit( $path ) . 'assets/';

        if ( ! $this->is_dir( $asset_dir ) && ! $this->mkdir( $asset_dir, FS_CHMOD_DIR ) ) {
            return new Exception( 'repo_error', 'Unable to create asset directory.', [ 'status' => 500 ] );
        }

        $ext    = FileSystemHelper::get_canonical_extension( $file['tmp_name'] );

        if ( ! $ext ) {
            return new Exception( 'repo_error', 'The extension for the for this image could not be trusted.', [ 'status' => 400 ] );
        }

        // --- Enforce naming rules ---
        switch ( $type ) {
            case 'banner':
                $allowed_names = [ 'banner-772x250', 'banner-1544x500' ];

                if ( ! in_array( pathinfo( $file['name'], PATHINFO_FILENAME ), $allowed_names, true ) 
                    || ! in_array( $ext, [ 'png', 'gif', 'svg' ], true ) ) {
                    return new Exception(
                        'invalid_banner_name',
                        'Banner must be named banner-772x250 or banner-1544x500 and be a PNG, GIF, or SVG.',
                        [ 'status' => 400 ]
                    );
                }
                $target_name = strtolower( pathinfo( $file['name'], PATHINFO_FILENAME ) . '.' . $ext );
                break;

            case 'icon':
                $allowed_names = [ 'icon-128x128', 'icon-256x256', 'icon' ];
                if ( ! in_array( pathinfo( $file['name'], PATHINFO_FILENAME ), $allowed_names, true ) 
                    || ! in_array( $ext, [ 'png', 'gif', 'svg' ], true ) ) {
                    return new Exception(
                        'invalid_icon_name',
                        'Icon must be named icon-128x128 or icon-256x256 and be a PNG, GIF, or SVG.',
                        [ 'status' => 400 ]
                    );
                }
                $target_name = strtolower( pathinfo( $file['name'], PATHINFO_FILENAME ) . '.' . $ext );
                break;

            case 'screenshot':
                $allowed_exts = [ 'png', 'jpg', 'jpeg', 'gif', 'svg' ];

                if ( ! in_array( $ext, $allowed_exts, true ) ) {
                    return new Exception(
                        'invalid_screenshot_type',
                        'Screenshots must be PNG, JPG, JPEG, GIF, or SVG.',
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
                    // Auto-generate next index
                    $existing = glob( $asset_dir . 'screenshot-*.{png,jpg,jpeg,gif,svg}', GLOB_BRACE );
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
        $dest_path = trailingslashit( $asset_dir ) . $target_name;

        // Move uploaded file
        if ( ! $this->rename( $file['tmp_name'], $dest_path ) ) {
            return new Exception( 'move_failed', 'Failed to save uploaded asset.', [ 'status' => 500 ] );
        }
        @$this->chmod( $dest_path, FS_CHMOD_FILE );

    
        return smliser_get_app_asset_url( 'plugin', $slug, $target_name );
    }

    /**
     * Delete a plugin asset from the repository
     * 
     * @param string $slug     Plugin slug (e.g., "my-plugin").
     * @param string $type     Asset type: 'banner', 'icon', 'screenshot'.
     * @param string $filename The filename to delete.
     *
     * @return true|Exception True on success, Exception on failure.
     */
    public function delete_asset( $slug, $filename ) {
        $path = $this->get_asset_path( $slug, $filename );

        if ( is_smliser_error( $path ) ) {
            return $path;
        }

        if ( ! $this->delete( $path ) ) {
            return new Exception( 'unable_to_delete', sprintf( 'Unable to delete the file %s', $filename ), [ 'status', 500 ] );
        }
        return true;
    }

    /**
     * Get plugin assets as URLs.
     *
     * @uses self::real_slug() and self::enter_slug() to ensure we operate inside the repository sandbox.
     *
     * @param string $slug Plugin slug.
     * @param string $type Asset type: 'banners', 'icons', 'screenshots'.
     * @return array URLs of assets. (banners/icons => keyed array, screenshots => [index => url]).
     */
    public function get_assets( string $slug, string $type ) : array {
        // Normalize slug and ensure it is valid inside the repo.
        $slug = $this->real_slug( $slug );

        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return [];
        }

        $assets_dir = trailingslashit( $base_dir ) . 'assets/';

        if ( ! $this->is_dir( $assets_dir ) ) {
            return [];
        }

        switch ( $type ) {
            case 'banners':
                $urls = [];
                foreach ( [
                    'low'  => 'banner-772x250',
                    'high' => 'banner-1544x500',
                ] as $key => $basename ) {
                    $pattern = $assets_dir . $basename . '.{png,gif,svg}';
                    $matches = glob( $pattern, GLOB_BRACE );
                    $urls[ $key ] = ( $matches && $this->is_file( $matches[0] ) )
                        ? smliser_get_app_asset_url( 'plugin', $slug, basename( $matches[0] ) )
                        : '';
                }
                return $urls;

            case 'icons':
                $urls = [];

                // Check the usual sized icons
                foreach ( [
                    '1x' => 'icon-128x128',
                    '2x' => 'icon-256x256',
                ] as $key => $basename ) {
                    $pattern = $assets_dir . $basename . '.{png,gif,svg}';
                    $matches = glob( $pattern, GLOB_BRACE );
                    $urls[ $key ] = ( $matches && $this->is_file( $matches[0] ) )
                        ? smliser_get_app_asset_url( 'plugin', $slug, basename( $matches[0] ) )
                        : '';
                }

                // Check for universal icon.svg and use it as fallback for 1x if no 128x128 exists
                $universal = $assets_dir . 'icon.svg';
                if ( $this->is_file( $universal ) && empty( $urls['1x'] ) ) {
                    $urls['1x'] = smliser_get_app_asset_url( 'plugin', $slug, 'icon.svg' );
                }

                return $urls;

            case 'screenshots':
                $pattern = $assets_dir . 'screenshot-*.{png,jpg,jpeg,gif,svg}';
                $files   = glob( $pattern, GLOB_BRACE );
                break;

            default:
                return [];
        }

        // --- Screenshots: indexed results ---
        if ( 'screenshots' === $type && $files ) {
            usort( $files, function( $a, $b ) {
                return strnatcmp( basename( $a ), basename( $b ) );
            } );

            $indexed = [];
            foreach ( $files as $file_path ) {
                $basename = basename( $file_path );
                $url      = smliser_get_app_asset_url( 'plugin', $slug, $basename );

                if ( preg_match( '/screenshot-(\d+)\./i', $basename, $m ) ) {
                    $indexed[ (int) $m[1] ] = $url;
                } else {
                    $indexed[] = $url;
                }
            }
            ksort( $indexed );
            return $indexed;
        }

        return [];
    }

    /**
     * Delete a plugin by it's given slug.
     * 
     * @param string $slug The plugin slug.
     * @return true|Exception True on success, Exception on failure.
     */
    public function trash( string $plugin_slug ) {
        if ( empty( $plugin_slug ) ) {
            return new Exception( 'invalid_slug', 'The application slug cannot be empty' );
        }

        $slug = $this->real_slug( $plugin_slug );
        
        if ( ! $this->queue_app_for_deletion( $slug ) ) {
            return new Exception(
                'deletion_failed',
                \sprintf('Failed to queue plugin "%s" for deletion.', $slug )
            );
        }

        return true;
    }

    /**
     * Restore a plugin from the trash.
     * 
     * @param string $plugin_slug The plugin slug.
     * @return true|Exception True on success, Exception on failure.
     */
    public function restore_from_trash( string $slug ) {
        if ( empty( $slug ) ) {
            return new Exception( 'invalid_slug', 'The application slug cannot be empty' );
        }

        $slug = $this->real_slug( $slug );

        if ( ! $this->restore_queued_deletion( $slug ) ) {
            return new Exception(
                'restore_failed',
                \sprintf('Failed to restore plugin "%s" from trash.', $slug )
            );
        }

        return true;
    }

    /**
     * Get plugin description from the readme.txt file.
     * 
     * @param string $slug The plugin slug.
     * @return string The plugin description.
     */
    public function get_description( $slug ) {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        // Normalize line endings
        $readme_contents = str_replace( ["\r\n", "\r"], "\n", $readme_contents );
        
        // Step 1: Start from Description block
        if ( preg_match('/==\s*Description\s*==\s*(.+)/si', $readme_contents, $matches) ) {
            $description = trim($matches[1]);

            // Step 2: Remove official sections that might appear afterwards
            $official_sections = ['Installation', 'Changelog', 'Frequently Asked Questions', 'Screenshots', 'Upgrade Notice'];
            foreach ( $official_sections as $section ) {
                $pattern = '/==\s*' . preg_quote( $section, '/' ) . '\s*==.*?(?=^==\s*\w.*==|\z)/msi';
                $description = preg_replace( $pattern, '', $description );;
            }

            // Step 3: Optional cleanup of stray metadata lines
            $lines   = explode("\n", $description);
            $exclude = ['Contributors:', 'Tags:', 'Stable tag:', 'Requires PHP:', 'License:', 'License URI:', 'Requires at least:', 'Tested up to:', 'WooCommerce tested up to:'];
            $cleaned = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $skip = false;
                foreach ($exclude as $meta) {
                    if ( stripos($line, $meta) !== false ) {
                        $skip = true;
                        break;
                    }
                }
                if (!$skip) {
                    $cleaned[] = $line;
                }
            }

            $final_text = implode("\n", $cleaned);
            return $this->parser->parse( $final_text );
        }

    }

    /**
     * Get plugin short description
     * 
     * @param string $slug The plugin slug.
     * @return string The plugin short description.
     */
    public function get_short_description( $slug ) {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }
        $lines           = preg_split( '/\r\n|\r|\n/', $readme_contents );
        $found_meta      = false;
        $short_description = '';

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // Stop searching once we reach the `== Description ==` section.
            if ( '== Description ==' === $line ) {
                break;
            }

            // Skip empty lines or plugin name section.
            if ( empty( $line ) || ( str_starts_with( $line, '===' ) && str_ends_with( $line, '===' ) ) ) {
                continue;
            }

            // Detect plugin meta section (lines with colons).
            if ( str_contains( $line, ':' ) ) {
                $found_meta = true;
                continue;
            }

            // If we’ve passed the meta and find a valid line, it's the short description.
            if ( $found_meta ) {
                $short_description = $line;
                break;
            }
        }

        return $short_description;

    }

    /**
     * Get plugin changelog text
     * 
     * @param string $slug The plugin slug
     * @return string The changelog text
     */
    public function get_changelog( $slug ) {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        // Look for the "== Changelog ==" section in the readme.txt
        if ( preg_match( '/==\s*Changelog\s*==\s*(.+?)(==|$)/s', $readme_contents, $matches ) ) {
            return $this->parser->parse(  trim( $matches[1] ) );
        }

        return '';
    }

    /**
     * Get the installation text
     * 
     * @param string $slug the plugin slug
     * @return string The plugin installation text section
     */
    public function get_installation( $slug ) {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        // Look for the "== Changelog ==" section in the readme.txt
        if ( preg_match( '/==\s*Installation\s*==\s*(.+?)(==|$)/s', $readme_contents, $matches ) ) {
            return $this->parser->parse(  trim( $matches[1] ) );
        }

        return '';
    }

    /**
     * Extract screenshot captions from a plugin's readme.txt file.
     *
     * @param string $slug Plugin slug.
     * @return array<int, string> [ index => caption ]
     */
    public function get_screenshot_captions( string $slug ) : array {
        $content = $this->get_readme_txt( $slug );

        if ( empty( $content ) ) {
            return [];
        }

        $lines    = preg_split( "/(\r?\n)/", $content );
        $captions = [];
        $capture  = false;

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // Section starts
            if ( preg_match( '/^==\s*Screenshots\s*==$/i', $trimmed ) ) {
                $capture = true;
                continue;
            }

            // Section ends (next == Section == heading)
            if ( $capture && preg_match( '/^==.+==$/', $trimmed ) ) {
                break;
            }

            // Numbered caption lines like "1. Some caption"
            if ( $capture && preg_match( '/^(\d+)\.\s*(.+)$/', $trimmed, $m ) ) {
                $captions[ (int) $m[1] ] = $m[2];
            }
        }

        return $captions;
    }

    /**
     * Get plugin screenshots in WordPress.org-style format.
     *
     * @param string $slug Plugin slug.
     * @return array<int, array{src: string, caption: string}>
     */
    public function get_screenshots( string $slug ) : array {
        // Get screenshot URLs from repo
        $assets = $this->get_assets( $slug, 'screenshots' );

        if ( empty( $assets ) ) {
            return [];
        }

        // Get captions from readme.txt
        $captions = $this->get_screenshot_captions( $slug );

        $formatted = [];
        foreach ( $assets as $i => $url ) {
            $formatted[ $i ] = [
                'src'     => $url,
                'caption' => $captions[ $i ] ?? '',
            ];
        }

        return $formatted;
    }
    
    /**
     * Get plugin screenshots as a formatted HTML ordered list.
     *
     * This method mimics the HTML structure found in the `sections -> screenshots`
     * property of the WordPress.org plugin information API.
     *
     * @param string $slug Plugin slug.
     * @return string HTML of the screenshots ordered list.
     */
    public function get_screenshot_html( string $slug ): string {
        // Get the structured screenshot data from your existing method.
        $screenshots = $this->get_screenshots( $slug );

        if ( empty( $screenshots ) ) {
            return '';
        }

        $html = '<ol>';

        foreach ( $screenshots as $i => $screenshot ) {
            $src     = esc_url( $screenshot['src'] );
            $caption = esc_html( $screenshot['caption'] );

            // This is the structure you see in the API response's sections property.
            // It includes a link to the full-size image and a caption.
            $html .= '<li>';
            $html .= '<a href="' . $src . '">';
            $html .= '<img src="' . $src . '" alt="' . $caption . '">';
            $html .= '</a>';
            if ( ! empty( $caption ) ) {
                $html .= '<p>' . $caption . '</p>';
            }
            $html .= '</li>';
        }

        $html .= '</ol>';

        return $html;
    }

    /**
     * Get the contents of the readme file.
     * 
     * @param string $slug The plugin slug.
     * @return string The readme.txt content, or empty string.
     */
    public function get_readme_txt( $slug ) {
        $slug = $this->real_slug( $slug );
        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return '';
        }

        $file_path = trailingslashit( $base_dir ) . 'readme.txt';

        if ( ! $this->exists( $file_path ) ) {
            // Attempt to get it from the zipped plugin file.
            $zip_path = $this->locate( $slug );

            if ( is_smliser_error( $zip_path ) ) {
                return '';
            }

            $zip = new \ZipArchive();
            if ( $zip->open( $zip_path ) !== true ) {
                return '';
            }

            $firstEntry = $zip->getNameIndex(0);
            $rootDir = explode('/', $firstEntry)[0];
            $readme_index = $zip->locateName( $rootDir . '/readme.txt', \ZipArchive::FL_NOCASE );
        
            if ( $readme_index === false ) {
                $zip->close();
                return '';
            }

            $readme_contents = $zip->getFromIndex( $readme_index );
            $zip->close();

            if ( ! $this->put_contents( $file_path, $readme_contents ) ) {
                // TODO: Logging.
            }

            return $readme_contents;
        }
        
        return $this->get_contents( $file_path ) ?: '';
    }

    /**
     * Get plugin metadata from the readme.txt header.
     *
     * @param string $slug The plugin slug.
     * @return array<string, mixed>
     */
    public function get_metadata( $slug ): array {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return [];
        }

        $metadata = [];
        $lines    = preg_split( '/\r\n|\r|\n/', $readme_contents );
        $first_line_processed = false;

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // Stop when the first section header begins (Description, Installation, etc).
            if ( preg_match( '/^==\s*[\w\s]+\s*==$/', $line ) ) {
                break;
            }

            // Skip empty lines until we've captured the title and/or headers.
            if ( '' === $line ) {
                continue;
            }

            /*
            * Detect plugin title.
            * Valid formats:
            *   === Plugin Name ===
            *   == Plugin Name ==
            *   # Plugin Name
            *   Plugin Name (H1 fallback)
            */
            if ( ! $first_line_processed ) {
                if ( preg_match( '/^={1,3}\s*(.+?)\s*={1,3}$/', $line, $m ) ) {
                    $metadata['plugin_name'] = trim( $m[1] );
                    $first_line_processed = true;
                    continue;
                }
                if ( preg_match( '/^#\s*(.+)$/', $line, $m ) ) {
                    $metadata['plugin_name'] = trim( $m[1] );
                    $first_line_processed = true;
                    continue;
                }

                // Fallback: treat the first non-empty line as title
                $metadata['plugin_name'] = $line;
                $first_line_processed = true;
                continue;
            }

            /*
            * Parse key: value fields
            * Example:
            *   Requires at least: 6.0
            *   Tested up to: 6.7
            *   Contributors: callistus, john
            */
            if ( preg_match( '/^([^:]+):\s*(.+)$/', $line, $matches ) ) {

                $raw_key = trim( $matches[1] );
                $value   = trim( $matches[2] );

                // Normalize key -> stable_tag, tested_up_to, etc.
                $normalized_key = strtolower(
                    str_replace( ' ', '_', $raw_key )
                );

                switch ( $normalized_key ) {
                    case 'contributors':
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