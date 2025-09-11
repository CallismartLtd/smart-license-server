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

defined( 'ABSPATH' ) || exit;

class PluginRepository extends Repository {

    /**
     * Our readme parser class.
     * 
     * @var Callismart_Markdown_to_HTML $parser
     */
    protected $parser;

    /**
     * Constructor.
     *
     * Always bind to the `plugins` subdirectory.
     */
    public function __construct() {
        global $smliser_md_html;
        parent::__construct( 'plugins' );
        $this->parser = $smliser_md_html;
    }
    
    /**
     * Locate a plugin zip file in the repository and enter the plugin slug.
     *
     * @param string $plugin_slug The plugin slug (e.g., "my-plugin").
     * @return string|\WP_Error Absolute file path or WP_Error on failure.
     */
    public function locate( $plugin_slug ) {
        if ( empty( $plugin_slug ) || ! is_string( $plugin_slug ) ) {
            return new \WP_Error( 
                'invalid_slug', 
                __( 'Plugin slug must be a non-empty string.', 'smart-license-server' ),
                [ 'status' => 400 ] 
            );
        }

        // Normalize slug
        $slug = $this->real_slug( $plugin_slug );

        try {
            $path = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new \WP_Error( 
                'invalid_slug', 
                $e->getMessage(),
                [ 'status' => 400 ] 
            );
        }

        // Plugin files MUST be a .zip file named after the plugin slug.
        $plugin_file = $this->path( sprintf( '%s.zip', $slug ) );

        if ( ! $this->exists( $plugin_file ) ) {
            return new \WP_Error(
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
     * - Pre-upload: determines destination folder and slug.
     * - Core upload: uses Repository::store_zip() to move the file safely.
     * - Post-upload: validates ZIP and extracts readme.txt to plugin folder.
     *
     * @param array  $file      The uploaded file ($_FILES format).
     * @param string $new_name  The preferred filename (without path).
     * @param bool   $update    Whether this is an update to an existing plugin.
     * @return string|\WP_Error Relative path to stored ZIP on success, WP_Error on failure.
     */
    public function upload_zip( array $file, string $new_name, bool $update = false ) {
        $tmp_name = $file['tmp_name'];

        if ( ! is_uploaded_file( $tmp_name ) ) {
            return new \WP_Error( 'invalid_temp_file', 'The temporary file is not valid.', [ 'status' => 400 ] );
        }

        // Ensure it's a ZIP
        $file_type_info = wp_check_filetype( $file['name'] );
        if ( $file_type_info['ext'] !== 'zip' ) {
            return new \WP_Error( 'invalid_file_type', 'Invalid file type, the plugin must be in ZIP format.', [ 'status' => 400 ] );
        }

        // Normalize filename
        $new_name  = sanitize_file_name( basename( $new_name ) );

        // Derive the slug first
        $slug = $this->real_slug( $new_name );

        // Force the filename to strictly be "{slug}.zip"
        $file_name = $slug . '.zip';

        // Destination folder and file path
        $base_folder = $this->path( $slug );
        $dest_path   = trailingslashit( $base_folder ) . $file_name;

        if ( ! $update ) {
            // New upload: prevent overwriting existing slug
            if ( $this->is_dir( $base_folder ) ) {
                return new \WP_Error(
                    'plugin_slug_exists',
                    sprintf( 'The slug "%s" is not available, you can change the plugin name and try again.', $slug ),
                    [ 'status' => 400 ]
                );
            }
            if ( ! $this->mkdir( $base_folder, FS_CHMOD_DIR ) ) {
                return new \WP_Error( 'repo_error', 'Unable to create plugin directory.', [ 'status' => 500 ] );
            }
        } else {
            // Update: ensure slug folder and plugin already exists.

            if ( ! $this->is_dir( $base_folder ) && ! $this->mkdir( $base_folder, FS_CHMOD_DIR )) {
                return new \WP_Error(
                    'plugin_not_found',
                    sprintf( 'The plugin slug "%s" does not exist in the repository, and attempt to create one failed.', $slug ),
                    [ 'status' => 404 ]
                );
            }

            // Optional: enforce slug consistency
            $expected_slug = $this->real_slug( $slug );
            if ( $slug !== $expected_slug ) {
                return new \WP_Error(
                    'slug_mismatch',
                    sprintf( 'Uploaded plugin "%s" does not match the target slug "%s".', $slug, $expected_slug ),
                    [ 'status' => 400 ]
                );
            }
        }

        // --- Core upload via Repository::store_zip() ---
        $stored_path = $this->store_zip( $tmp_name, $dest_path );
        if ( is_wp_error( $stored_path ) ) {
            return $stored_path;
        }

        // --- Post-upload: Validate ZIP and extract readme.txt ---
        $zip = new \ZipArchive();
        if ( $zip->open( $stored_path ) !== true ) {
            // Cleanup differs depending on upload vs update
            if ( $update ) {
                $this->delete( $stored_path ); // only remove bad zip
            } else {
                $this->rmdir( $base_folder, true ); // remove new folder completely
            }
            return new \WP_Error( 'zip_invalid', 'Uploaded ZIP could not be opened.', [ 'status' => 400 ] );
        }

        $firstEntry = $zip->getNameIndex(0);
        $rootDir = explode('/', $firstEntry)[0];
        $readme_index = $zip->locateName( $rootDir . '/readme.txt', \ZipArchive::FL_NOCASE );
        if ( $readme_index === false ) {
            $zip->close();
            if ( $update ) {
                $this->delete( $stored_path ); // keep old plugin, remove bad upload
            } else {
                $this->rmdir( $base_folder, true );
            }
            return new \WP_Error( 'readme_missing', 'The plugin ZIP must contain a readme.txt file.', [ 'status' => 400 ] );
        }

        $readme_contents = $zip->getFromIndex( $readme_index );
        $zip->close();

        $readme_path = trailingslashit( $base_folder ) . 'readme.txt';
        if ( ! $this->put_contents( $readme_path, $readme_contents ) ) {
            if ( $update ) {
                $this->delete( $stored_path );
            } else {
                $this->rmdir( $base_folder, true );
            }
            return new \WP_Error( 'readme_save_failed', 'Failed to save readme.txt.', [ 'status' => 500 ] );
        }

        return untrailingslashit( $slug . '/' . $file_name );
    }

    /**
     * Upload an asset (banner, icon, or screenshot) for a plugin.
     *
     * @param string $slug     Plugin slug (e.g., "my-plugin").
     * @param array  $file     Uploaded file ($_FILES format).
     * @param string $type     Asset type: 'banner', 'icon', 'screenshot'.
     * @param string $filename Optional specific filename (for replacing/updating).
     *
     * @return string|\WP_Error Relative path to stored asset or WP_Error on failure.
     */
    public function upload_asset( string $slug, array $file, string $type, string $filename = '' ) {
        $allowed_mimes = [ 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' ];

        // Validate upload
        if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new \WP_Error( 'invalid_upload', 'Invalid uploaded file.', [ 'status' => 400 ] );
        }

        $check = wp_check_filetype( $file['name'], $allowed_mimes );
        if ( empty( $check['ext'] ) ) {
            return new \WP_Error( 'invalid_type', 'Only PNG or JPG images are allowed.', [ 'status' => 400 ] );
        }

        $slug = $this->real_slug( $slug );

        // Attempt to enter the plugin slug dir.
        try {
            $path = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new \WP_Error( 
                'invalid_slug', 
                $e->getMessage(),
                [ 'status' => 400 ] 
            );
        }

        $asset_dir = trailingslashit( $path ) . 'assets/';

        if ( ! $this->is_dir( $asset_dir ) && ! $this->mkdir( $asset_dir, FS_CHMOD_DIR ) ) {
            return new \WP_Error( 'repo_error', 'Unable to create asset directory.', [ 'status' => 500 ] );
        }

        // --- Enforce naming rules ---
        switch ( $type ) {
            case 'banner':
                $allowed = [ 'banner-772x250.png', 'banner-1544x500.png' ];
                if ( ! in_array( strtolower( $file['name'] ), $allowed, true ) ) {
                    return new \WP_Error(
                        'invalid_banner_name',
                        'Banner must be named banner-772x250.png or banner-1544x500.png.',
                        [ 'status' => 400 ]
                    );
                }
                $target_name = strtolower( $file['name'] );
                break;

            case 'icon':
                $allowed = [ 'icon-128x128.png', 'icon-256x256.png' ];
                if ( ! in_array( strtolower( $file['name'] ), $allowed, true ) ) {
                    return new \WP_Error(
                        'invalid_icon_name',
                        'Icon must be named icon-128x128.png or icon-256x256.png.',
                        [ 'status' => 400 ]
                    );
                }
                $target_name = strtolower( $file['name'] );
                break;

            case 'screenshot':
                if ( ! empty( $filename ) ) {
                    $ext = strtolower( $check['ext'] );
                    if ( preg_match( '/screenshot-(\d+)\./', $filename, $m ) ) {
                        $target_name = sprintf( 'screenshot-%d.%s', $m[1], $ext );
                    } else {
                        return new \WP_Error(
                            'invalid_screenshot_name',
                            'Screenshots must be named screenshot-{index}.png or screenshot-{index}.jpg.',
                            [ 'status' => 400 ]
                        );
                    }
                } else {
                    // Auto-generate next index
                    $existing    = glob( $asset_dir . 'screenshot-*.{png,jpg,jpeg}', GLOB_BRACE );
                    $indexes     = [];

                    foreach ( $existing as $shot ) {
                        if ( preg_match( '/screenshot-(\d+)\./', basename( $shot ), $m ) ) {
                            $indexes[] = (int) $m[1];
                        }
                    }

                    $next_index  = empty( $indexes ) ? 1 : ( max( $indexes ) + 1 );
                    $target_name = sprintf( 'screenshot-%d.%s', $next_index, strtolower( $check['ext'] ) );
                }
                break;

            default:
                return new \WP_Error( 'invalid_type', 'Unsupported asset type.', [ 'status' => 400 ] );
        }

        // Final destination
        $dest_path = trailingslashit( $asset_dir ) . $target_name;

        // Move uploaded file
        if ( ! $this->rename( $file['tmp_name'], $dest_path ) ) {
            return new \WP_Error( 'move_failed', 'Failed to save uploaded asset.', [ 'status' => 500 ] );
        }
        $this->chmod( $dest_path, FS_CHMOD_FILE );

    
        return smliser_get_app_asset_url( 'plugin', $slug, $target_name );
    }

    /**
     * Delete a plugin asset from the repository
     * 
     * @param string $slug     Plugin slug (e.g., "my-plugin").
     * @param string $type     Asset type: 'banner', 'icon', 'screenshot'.
     * @param string $filename The filename to delete.
     *
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public function delete_asset( $slug, $filename ) {
        $path = $this->get_asset_path( $slug, $filename );

        if ( is_wp_error( $path ) ) {
            return $path;
        }

        if ( ! $this->delete( $path ) ) {
            return new WP_Error( 'unable_to_delete', sprintf( 'Unable to delete the file %s', $filename ), [ 'status', 500 ] );
        }
        return true;
    }

    /**
     * Get plugin assets as URLs.
     *
     * Uses real_slug() and enter_slug() to ensure we operate inside the repository sandbox.
     *
     * @param string $slug Plugin slug.
     * @param string $type Asset type: 'banners', 'icons', 'screenshots'.
     * @return array URLs of assets. (banners/icons => keyed array, screenshots => [index => url]).
     */
    public function get_assets( string $slug, string $type ) : array {
        // Normalize slug and ensure it is valid inside the repo.
        $slug = $this->real_slug( $slug );

        try {
            $folder = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return [];
        }

        $assets_dir = trailingslashit( $folder ) . 'assets/';

        if ( ! $this->is_dir( $assets_dir ) ) {
            return [];
        }

        switch ( $type ) {
            case 'banners':
                $files = [
                    'low'  => $assets_dir . 'banner-772x250.png',
                    'high' => $assets_dir . 'banner-1544x500.png',
                ];
                break;

            case 'icons':
                $files = [
                    '1x'    => $assets_dir . 'icon-128x128.png',
                    '2x'    => $assets_dir . 'icon-256x256.png',
                ];
                break;

            case 'screenshots':
                $pattern = $assets_dir . 'screenshot-*.{png,jpg,jpeg}';
                $files   = glob( $pattern, GLOB_BRACE );
                break;

            default:
                return [];
        }

        // --- Banners & Icons: keyed results ---
        if ( in_array( $type, [ 'banners', 'icons' ], true ) ) {
            $urls = [];
            foreach ( $files as $key => $file_path ) {
                if ( $this->is_file( $file_path ) ) {
                    $urls[ $key ] = smliser_get_app_asset_url( 'plugin', $slug, basename( $file_path ) );
                } else {
                    $urls[ $key ] = '';
                }
            }
            return $urls;
        }

        // --- Screenshots: indexed results ---
        if ( 'screenshots' === $type && $files ) {
            usort( $files, function( $a, $b ) {
                return strnatcmp( basename( $a ), basename( $b ) );
            } );

            $indexed = [];
            foreach ( $files as $file_path ) {
                $basename = basename( $file_path );
                $url      = smliser_get_app_asset_url( 'plugin', $slug, 'assets/' . $basename );

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
     * Get the absolute path to a given application asset.
     *
     * @param string $slug      App slug.
     * @param string $filename  File name within the assets directory.
     * @return string|\WP_Error Absolute path to asset or WP_Error if not found.
     */
    public function get_asset_path( string $slug, string $filename ) {
        $slug = $this->real_slug( $slug );
        try {
            $base_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new \WP_Error( 'invalid_dir', $e->getMessage(), [ 'status' => 400 ] );
        }

        $asset_dir = trailingslashit( $base_dir ) . 'assets/';
        $abs_path  = sanitize_and_normalize_path( trailingslashit( $asset_dir ) . basename( $filename ) );

        if ( ! $this->exists( $abs_path ) ) {
            return new \WP_Error(
                'asset_not_found',
                sprintf( 'Asset "%s" not found.', esc_html( $filename ) ),
                [ 'status' => 404 ]
            );
        }

        return $abs_path;
    }

    /**
     * Delete a plugin by it's given slug.
     * 
     * @param string $slug The plugin slug
     */
    public function delete_from_repo( $plugin_slug ) {
        if ( empty( $plugin_slug ) ) {
            return new WP_Error( 'invalid_slug', 'The application slug cannot be empty' );
        }

        $slug = $this->real_slug( $plugin_slug );
        try {
            $repo_dir = $this->enter_slug( $slug );
        } catch ( \InvalidArgumentException $e ) {
            return new \WP_Error( 
                'invalid_slug', 
                $e->getMessage(),
                [ 'status' => 400 ] 
            );
        }

        if ( ! $this->is_dir( $repo_dir ) ) {
            return new \WP_Error( 
                'plugin_repo_error', 
                sprintf( 'The plugin with slug "%s" does not exist in the repository', $slug ),
                [ 'status' => 404 ] 
            );
        }

        if ( ! $this->rmdir( $repo_dir, true ) ) {
            return new \WP_Error( 
                'plugin_repo_error', 
                sprintf( 'Unable to delete the plugin with slug "%s"!', $slug ),
                [ 'status' => 500 ] 
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
        
        // Step 1: Start from Description block
        if ( preg_match('/==\s*Description\s*==\s*(.+)/si', $readme_contents, $matches) ) {
            $description = trim($matches[1]);

            // Step 2: Remove official sections that might appear afterwards
            $official_sections = ['Installation', 'Changelog', 'Frequently Asked Questions', 'Screenshots', 'Upgrade Notice'];
            foreach ( $official_sections as $section ) {
                // Remove everything from == Section == to the next ==
                $pattern = '/==\s*' . preg_quote($section, '/') . '\s*==.*?(?==\s*\w+\s*==|$)/si';
                $description = preg_replace( $pattern, '', $description );
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
            return $this->parser->parse( esc_html($final_text) );
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

            if ( is_wp_error( $zip_path ) ) {
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
        
        return $this->get_contents( $file_path );
    }
}
