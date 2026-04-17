<?php
/**
 * Plugin Repository
 *
 * Handles plugin-specific business logic within the repository.
 * Extends the abstract Repository to ensure secure, scoped file operations.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileSystemException;
use SmartLicenseServer\Utils\MDParser;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Plugin repository class provides filesystem APIs to interact with hosted plugins in the repository.
 * 
 * Note: it does not represent a single hosted plugin @see \SmartLicenseServer\HostedApps\Plugin
 */
class PluginRepository extends Repository {
    use WPRepoUtils;

    /**
     * Allowed plugin banner names.
     */
    const ALLOWED_BANNER_NAMES  = [ 'low'  => 'banner-772x250', 'high' => 'banner-1544x500' ];

    /**
     * Our readme parser class.
     * 
     * @var MDParser $parser
     */
    protected MDParser $parser;

    /**
     * Constructor.
     *
     * Always bind to the `plugins` subdirectory.
     */
    public function __construct() {
        parent::__construct( 'plugins' );
        $this->parser = \smliser_md_parser();
    }
    
    /**
     * Locate a plugin zip file in the repository and enter the plugin slug.
     *
     * @param string $plugin_slug The plugin slug (e.g., "my-plugin").
     * @return string|Exception Absolute file path or error on failure.
     */
    public function locate( $plugin_slug ) : string| Exception {
        if ( empty( $plugin_slug ) || ! is_string( $plugin_slug ) ) {
            return new Exception( 
                'invalid_slug', 
                __( 'Plugin slug must be a non-empty string.', 'smliser' ),
                [ 'status' => 400 ] 
            );
        }

        try {
            $slug = $this->real_slug( $plugin_slug );
            $this->enter_slug( $slug );
        } catch ( FileSystemException $e ) {
            return new Exception( 
                'invalid_slug', 
                $e->get_error_message(),
                [ 'status' => 400 ] 
            );
        }

        // Plugin files MUST be a .zip file named after the plugin slug.
        $plugin_file = $this->path( sprintf( '%s.zip', $slug ) );

        if ( ! $this->exists( $plugin_file ) ) {
            return new Exception(
                'file_not_found',
                __( 'Plugin file not found.', 'smliser' ),
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
     * @param UploadedFile $file    The uploaded file ($_FILES format).
     * @param string $new_name      The preferred filename (without path).
     * @param bool   $update        Whether this is an update to an existing plugin.
     * @return string|Exception     Relative path to stored ZIP on success, Exception on failure.
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

        // Post-upload: Validate ZIP and extract readme.txt.
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
            return new Exception( 'readme_missing', 'The plugin ZIP file must contain a readme.txt file.', [ 'status' => 400 ] );
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
     * Validate names and types of asset that can be uploaded for a theme.
     * 
     * @param UploadedFile $file The uploaded file instance.
     * @param string $type       The asset type.
     * @return Exception|string On error, file name otherwise.
     */
    public function validate_app_asset( UploadedFile $file, string $type, string $asset_dir ) : Exception|string {
        $ext        = $file->get_canonical_extension();
        $validation = $this->is_valid_image( $file->get_tmp_path() );

        if ( is_smliser_error( $validation ) ) {
            return $validation;
        }

        switch ( $type ) {
            case 'banner':
            case 'banners':

                if ( ! in_array( $file->get_name( false ), static::ALLOWED_BANNER_NAMES, true ) ) {
                    return new Exception( 'filename_error', sprintf( 'Banner name must be one of: %s', implode( ', ', static::ALLOWED_BANNER_NAMES ) ) );
                }
        
                return $file->get_name();

            case 'icon':
                $allowed    = in_array( $file->get_name( false ), static::ALLOWED_ICON_NAMES, true );
                if ( ! $allowed ) {
                    return new Exception(
                        'filename_error',
                        sprintf( 'Icon name must be one of: %s', implode( ', ', static::ALLOWED_ICON_NAMES ) )
                    );
                }

                return $file->get_name();

            case 'screenshots':
            case 'screenshot':
                if ( preg_match( '/screenshot-(\d+)/', $file->get_name(), $m ) ) {
                    $screenshot = sprintf( 'screenshot-%d.%s', $m[1], $ext );
                    
                } else {
                    // Auto-generate next index.
                    $screenshot = sprintf( '%s.%s', $this->find_next_screenshot_name( $asset_dir ), $ext );
                }

                return $screenshot;

            default:
                return new Exception( 'invalid_type', 'Plugins only supports banners, icon, screenshots as asset type.', [ 'status' => 400 ] );
        }
        
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
        } catch ( FileSystemException $e ) {
            return [];
        }

        $assets_dir = FileSystemHelper::join_path( $base_dir, 'assets/' );
        
        if ( ! $this->is_dir( $assets_dir ) ) {
            return [];
        }

        switch ( $type ) {
            case 'banners':
                $urls = [];
                foreach ( [ 'low'  => 'banner-772x250', 'high' => 'banner-1544x500' ] as $key => $basename ) {
                    $path       = FileSystemHelper::join_path( $assets_dir, $basename );
                    $pattern    = sprintf( '%s.*{%s}', $path, implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ) );
                    $matches    = glob( $pattern, GLOB_BRACE );
                    $urls[ $key ] = ( $matches && $this->is_file( $matches[0] ) )
                        ? smliser_get_asset_url( 'plugin', $slug, basename( $matches[0] ) )
                        : '';
                }
                
                return $urls;

            case 'icons':
                $urls = [];
                // Check the usual sized icons.
                foreach ( ['1x' => 'icon-128x128', '2x' => 'icon-256x256' ] as $key => $basename ) {
                    $path       = FileSystemHelper::join_path( $assets_dir, $basename );
                    $pattern    = sprintf( '%s.*{%s}', $path, implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ) );
                    $matches    = glob( $pattern, GLOB_BRACE );
                    $urls[ $key ] = ( $matches && $this->is_file( $matches[0] ) )
                        ? smliser_get_asset_url( 'plugin', $slug, basename( $matches[0] ) )
                        : '';
                }

                // Check for universal icon.* and use it as fallback for 1x if no 128x128 exists.
                $icon_path      = FileSystemHelper::join_path( $assets_dir, 'icon' );
                $icon_pattern   = sprintf( '%s.*{%s}', $icon_path, implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ) );
                $icon_matches   = glob( $icon_pattern, GLOB_BRACE );
                
                if ( empty( $urls['1x'] ) && ! empty( $icon_matches ) ) {
                    foreach ( $icon_matches as $icon ) {
                        if ( $this->is_file( $icon ) ) {
                            $urls['1x'] = smliser_get_asset_url( 'plugin', $slug, basename( $icon ) );
                            break;
                        }

                    }
                }
                
                return $urls;

            case 'screenshots':
                $path       = FileSystemHelper::join_path( $assets_dir, 'screenshot' );
                $pattern    = sprintf( '%s-*{%s}', $path, implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ) );
                $files      = glob( $pattern, GLOB_BRACE );
                
                usort( $files, function( $a, $b ) {
                    return strnatcmp( basename( $a ), basename( $b ) );
                } );

                $indexed = [];
                foreach ( $files as $file_path ) {
                    $basename = basename( $file_path );
                    $url      = smliser_get_asset_url( 'plugin', $slug, $basename );

                    if ( preg_match( '/screenshot-(\d+)\./i', $basename, $m ) ) {
                        $indexed[ (int) $m[1] ] = $url;
                    } else {
                        $indexed[] = $url;
                    }
                }

                ksort( $indexed );
                return $indexed;
            default:
                return [];
        }
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

        // Normalize line endings.
        $readme_contents = str_replace( ["\r\n", "\r"], "\n", $readme_contents );
        
        // Step 1: Start from Description block
        if ( preg_match('/==\s*Description\s*==\s*(.+)/si', $readme_contents, $matches) ) {
            $description = trim($matches[1]);

            // Step 2: Remove official sections that might appear afterwards.
            $official_sections = ['Installation', 'Changelog', 'Frequently Asked Questions', 'Screenshots', 'Upgrade Notice'];
            foreach ( $official_sections as $section ) {
                $pattern = '/==\s*' . preg_quote( $section, '/' ) . '\s*==.*?(?=^==\s*\w.*==|\z)/msi';
                $description = preg_replace( $pattern, '', $description );
            }

            // Step 3: Optional cleanup of stray metadata lines
            $lines   = explode( "\n", $description );
            $exclude = ['Contributors:', 'Tags:', 'Stable tag:', 'Requires PHP:', 'License:', 'License URI:', 'Requires at least:', 'Tested up to:', 'WooCommerce tested up to:'];
            $cleaned = [];
            foreach ( $lines as $line ) {
                $line = trim( $line );

                if ( $line === '' ) continue;

                $skip = false;

                foreach ( $exclude as $meta ) {
                    if ( stripos( $line, $meta ) !== false ) {
                        $skip = true;
                        break;
                    }
                }
                
                if ( ! $skip ) {
                    $cleaned[] = $line;
                }
            }

            $final_text = implode( "\n", $cleaned) ;
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

        // Look for the "== Installation ==" section in the readme.txt
        if ( preg_match( '/==\s*Installation\s*==\s*(.+?)(==|$)/s', $readme_contents, $matches ) ) {
            return $this->parser->parse(  trim( $matches[1] ) );
        }

        return '';
    }

    /**
     * Get plugin FAQ (Frequently Asked Questions) section
     * 
     * @param string $slug The plugin slug
     * @return string The FAQ text in HTML format
     */
    public function get_faq( $slug ) {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        // Look for FAQ section with various possible headings.
        $patterns = [
            '/==\s*Frequently Asked Questions\s*==\s*(.+?)(==|$)/si',
            '/==\s*FAQ\s*==\s*(.+?)(==|$)/si',
            '/==\s*F\.A\.Q\.\s*==\s*(.+?)(==|$)/si',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $readme_contents, $matches ) ) {
                return $this->parser->parse( trim( $matches[1] ) );
            }
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
            $src     = $screenshot['src'];
            $caption = $screenshot['caption'];

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
        } catch ( FileSystemException $e ) {
            return '';
        }

        $file_path = FileSystemHelper::join_path( $base_dir, 'readme.txt' );

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

        $metadata               = [];
        $lines                  = preg_split( '/\r\n|\r|\n/', $readme_contents );
        $first_line_processed   = false;

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