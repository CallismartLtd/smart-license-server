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
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileSystemException;
use SmartLicenseServer\Utils\MDParser;

/**
 * Plugin repository class provides filesystem APIs to interact with 
 * hosted plugins in the repository.
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
     * Constructor.
     *
     * Always bind to the `plugins` subdirectory.
     * 
     * @param MDParser $mdparser        The markdown parser.
     * @param URLManager $urlmanager    The URL manager.
     */
    public function __construct(
        protected MDParser $mdparser,
        protected URLManager $urlmanager
    ) {
        parent::__construct( 'plugins' );
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
     * {@inheritdoc}
     *
     * - Post-upload: validates ZIP and extracts readme.txt to plugin folder.
     */
    public function upload_zip( UploadedFile $file, string $new_name = '', bool $update = false ) : array {
        // Core upload via `Repository::safe_zip_upload()`.
        $stored_path = $this->safe_zip_upload( $file, $new_name, $update );
        if ( $stored_path instanceof Exception ) {
            return ['error' => $stored_path];
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
            return [
                'error'             => new Exception(
                    'zip_invalid',
                    'Uploaded ZIP could not be opened.',
                    [ 'status' => 400 ]
                ),
                'rollback_function' => $cleanup_func,
                'base_dir'          => $base_folder,
                'slug'              => $slug
            ];
        }

        $firstEntry     = $zip->getNameIndex(0);
        $rootDir        = explode( '/', $firstEntry )[0];
        $readme_index   = $zip->locateName( $rootDir . '/readme.txt', \ZipArchive::FL_NOCASE );
        if ( $readme_index === false ) {
            $zip->close();
            return [
                'error' => new Exception(
                    'readme_missing',
                    'The plugin ZIP file must contain a readme.txt file.',
                    [ 'status' => 400 ]
                ),
                'rollback_function' => $cleanup_func,
                'base_dir'          => $base_folder,
                'slug'              => $slug
            ];
        }

        $readme_contents = $zip->getFromIndex( $readme_index );
        $zip->close();

        $readme_path = FileSystemHelper::join_path( $base_folder, 'readme.txt' );

        if ( ! $this->put_contents( $readme_path, $readme_contents ) ) {
            return [
                'error'     => new Exception(
                    'readme_save_failed',
                    'Failed to save readme.txt after upload.',
                    [ 'status' => 500 ]
                ),
                'rollback_function' => $cleanup_func,
                'base_dir'          => $base_folder,
                'slug'              => $slug
            ];
        }

        return [
            'slug'              => $slug,
            'base_dir'          => $base_folder,
            'rollback_function' => $cleanup_func
        ];
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
     * @param string<'banners', 'icons', 'screenshots'> $type Asset type.
     * @return URL[]
     *  |array{
     *      'low': URL|string,
     *      'high': URL|string
     *  }
     *  |array{
     *      '1x': URL|string,
     *      '2x': URL|string
     *  }
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
                        ? $this->urlmanager->app_asset_url( 'plugin', $slug, basename( $matches[0] ) )
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
                        ? $this->urlmanager->app_asset_url( 'plugin', $slug, basename( $matches[0] ) )
                        : '';
                }

                // Check for universal icon.* and use it as fallback for 1x if no 128x128 exists.
                $icon_path      = FileSystemHelper::join_path( $assets_dir, 'icon' );
                $icon_pattern   = sprintf( '%s.*{%s}', $icon_path, implode( ',', static::ALLOWED_IMAGE_EXTENSIONS ) );
                $icon_matches   = glob( $icon_pattern, GLOB_BRACE );
                
                if ( empty( $urls['1x'] ) && ! empty( $icon_matches ) ) {
                    foreach ( $icon_matches as $icon ) {
                        if ( $this->is_file( $icon ) ) {
                            $urls['1x'] = $this->urlmanager->app_asset_url( 'plugin', $slug, basename( $icon ) );
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
                    $url      = $this->urlmanager->app_asset_url( 'plugin', $slug, $basename );

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
            return $this->mdparser->parse( $final_text );
        }

    }

    /**
     * Get plugin short description.
     * 
     * According to WordPress.org standards, the short description is the 
     * first non-empty text line following the header metadata block 
     * and preceding any section header (== Section ==).
     * 
     * @param string $slug The plugin slug.
     * @return string The plugin short description or empty string if absent.
     */
    public function get_short_description( string $slug ) : string {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        $lines = preg_split( '/\r\n|\r|\n/', $readme_contents );

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // Skip empty lines or the top Plugin Title (e.g., === My Plugin ===)
            if ( '' === $line || preg_match( '/^===\s*.+\s*===$/', $line ) ) {
                continue;
            }

            // Skip standard WordPress header metadata lines (Key: Value)
            if ( $this->is_readme_meta_key( $line ) ) {
                continue;
            }

            // Stop if we hit ANY section header (== Description ==, == ChangeLog ==, etc.)
            // If we reach a section header before finding text, this readme has no short description.
            if ( preg_match( '/^==\s*[^=]+\s*==$/', $line ) ) {
                break;
            }

            // First valid text line encountered is our short description
            return $line;
        }

        return '';
    }

    /**
     * Check if a line matches a standard WordPress header metadata key.
     * 
     * @param string $line
     * @return bool
     */
    private function is_readme_meta_key( string $line ) : bool {
        return (bool) preg_match( 
            '/^([^:]+):\s*(.+)$/', 
            $line 
        );
    }

    /**
     * Get plugin changelog text
     * 
     * @param string $slug The plugin slug
     * @return string The parsed changelog HTML
     */
    public function get_changelog( string $slug ) : string {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        // Match "== Changelog ==" up to the next main section "== Header ==" or EOF.
        // Negative lookahead/lookbehind ensures we only match exactly 2 equals signs (==), 
        // preserving version subheadings like "= 2.5.4 =" or "=== 2.5.4 ===".
        $pattern = '/==\s*Changelog\s*==\s*(.*?)(?=\n\s*(?<!=)==(?!=)[^=]+==(?!=)|$)/is';

        if ( preg_match( $pattern, $readme_contents, $matches ) ) {
            $changelog_md = trim( $matches[1] );

            return $changelog_md !== '' ? $this->mdparser->parse( $changelog_md ) : '';
        }

        return '';
    }

    /**
     * Get the installation text.
     * 
     * @param string $slug The plugin slug.
     * @return string The parsed installation HTML.
     */
    public function get_installation( string $slug ) : string {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        // Match "== Installation ==" up to the next main section "== Header ==" or EOF.
        // Strictly matches exactly 2 equals signs (==) for section boundaries,
        // preserving subheadings like "= Step 1 =" or "=== Manual Upload ===".
        $pattern = '/==\s*Installation\s*==\s*(.*?)(?=\n\s*(?<!=)==(?!=)[^=]+==(?!=)|$)/is';

        if ( preg_match( $pattern, $readme_contents, $matches ) ) {
            $installation_md = trim( $matches[1] );

            return $installation_md !== '' ? $this->mdparser->parse( $installation_md ) : '';
        }

        return '';
    }

    /**
     * Get plugin FAQ (Frequently Asked Questions) section.
     * 
     * @param string $slug The plugin slug.
     * @return string The parsed FAQ HTML.
     */
    public function get_faq( string $slug ) : string {
        $readme_contents = $this->get_readme_txt( $slug );

        if ( ! $readme_contents ) {
            return '';
        }

        // Match "== Frequently Asked Questions ==", "== FAQ ==", or "== F.A.Q. =="
        // up to the next main section header "== Section ==" or EOF.
        $pattern = '/==\s*(?:Frequently\s+Asked\s+Questions|FAQ|F\.A\.Q\.)\s*==\s*(.*?)(?=\n\s*(?<!=)==(?!=)[^=]+==(?!=)|$)/is';

        if ( preg_match( $pattern, $readme_contents, $matches ) ) {
            $faq_md = trim( $matches[1] );

            return $faq_md !== '' ? $this->mdparser->parse( $faq_md ) : '';
        }

        return '';
    }

    /**
     * Extract screenshot captions from a plugin's readme.txt file.
     *
     * @param string $slug Plugin slug.
     * @return string[]
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
     * @return array{src: string, caption: string}[]
     */
    public function get_screenshots( string $slug ) : array {
        /** @var URL[] */
        $assets = $this->get_assets( $slug, 'screenshots' );

        if ( empty( $assets ) ) {
            return [];
        }

        // Get captions from readme.txt
        $captions = $this->get_screenshot_captions( $slug );

        $screenshots = [];
        foreach ( $assets as $i => $url ) {
            $screenshots[ $i ] = [
                'src'     => $url->url(),
                'caption' => $captions[ $i ] ?? '',
            ];
        }

        return $screenshots;
    }

    /**
     * Get plugin icons.
     * 
     * @return array{'1x': URL|string, '2x': URL|string}
     */
    public function get_icons( string $slug ) {
        return $this->get_assets( $slug, 'icons' );
    }

    /**
     * Get plugin banners.
     * 
     * @return array{'low': URL|string, 'high': URL|string}
     */
    public function get_banners( string $slug ) {
        return $this->get_assets( $slug, 'banners' );
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