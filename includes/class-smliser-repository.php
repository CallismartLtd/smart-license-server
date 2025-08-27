<?php
/**
 * file name class-smliser-repository.php
 * Repository management class for Smart License Server
 * 
 * @author Callistus
 * @package SmartLicenseServer
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Smart License Server Repository class which handles filesystem interaction for hosted files.
 */
class Smliser_Repository {

    /**
     * @var bool Whether we can access the filesystem.
     */
    protected $is_loaded = false;
    
    /**
     * @var string $errors
     */
    protected $errors = [];

    /**
     * @var string The directory for the repository.
     */
    protected $repo_dir;
    

    /**
     * @var Smliser_Repository
     */
    protected static $instance = null;
    
    /**
     * @var WP_Filesystem_Direct $repo The WordPress filesystem class.
     */
    public $repo = null;

    /**
     * Class constructor
     */
    public function __construct() {
        global $wp_filesystem;
        $this->initialize_filesystem();

        if ( ! empty( $this->errors ) ) {
            $this->add_notice();
        }

        $this->repo_dir = SMLISER_NEW_REPO_DIR;

        $this->repo = $wp_filesystem;
    }

    /**
     * Initialize the WordPress filesystem.
     */
    private function initialize_filesystem() {
        if ( ! function_exists( 'request_filesystem_credentials' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        ob_start();
        $creds = request_filesystem_credentials( '', '', false, false, null );
        ob_get_clean();

        if ( ! WP_Filesystem( $creds ) ) {
            $this->errors['smliser_filesys_error'] = 'Your server\'s cannot be accessed. In other to use Smart License Server, you will need to configure it to use FTP credentials.';
        }

        if ( $dir_erro = get_option( 'smliser_directory_error', false ) ) {
            $this->errors['smliser_directory_error'] = $dir_erro;
        }

        $this->is_loaded = empty( $this->errors );
    }

   /**
     * Instance of current class
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /*
    |------------
    | Getters
    |------------
    */
    
    /**
     * Get the repository directory path.
     *
     * @return string The absolute path to the repository directory.
     */
    public function get_repo_dir() {
        return $this->repo_dir;
    }

    /**
     * Get the repo directory for plugins.
     * 
     * @return string The Absolute path to the repository directory for plugins.
     */
    public function get_plugins_dir() {
        return SMLISER_PLUGINS_REPO_DIR;
    }

    /**
     * Get the repo directory for themes
     * 
     * @return string The absolute path tothe theme directory
     */
    public function get_themes_dir() {
        return SMLISER_THEMES_REPO_DIR;
    }

    /*
    |--------------
    | CRUD methods
    |--------------
    */

    /**
     * List all files in the repository.
     *
     * @return array|WP_Error List of files or WP_Error on failure.
     */
    public function list_repo() {
        if ( ! $this->repo->is_dir( $this->get_repo_dir() ) ) {
            return new WP_Error( 'directory_not_found', __( 'Repository directory not found', 'smart-license-server' ) );
        }

        $folders = $this->repo->dirlist( $this->get_repo_dir(), false );

        if ( ! is_array( $folders ) ) {
            return new WP_Error( 'directory_read_failed', __( 'Failed to read directory', 'smart-license-server' ) );
        }

        // Filter to keep only directories and restructure the data.
        $folders = array_filter( $folders, function( $folder ) {
            return isset( $folder['type'] ) && 'd' === $folder['type'];
        });

        $result = [];

        foreach ( $folders as $name => $data ) {
            $result[ $name ] = [
                'name'          => $name,
                'permissions'   => [
                    'readable' => $data['perms'],
                    'numeric'  => $data['permsn'],
                ],
                'last_modified' => [
                    'human_readable' => $data['lastmod'] . ', ' . $data['time'],
                    'unix'           => $data['lastmodunix'],
                ],
                'files' => [
                    basename( Smliser_Plugin::normalize_slug( $name ) )
                ],
            ];
        }

        return $result;
    }


    /**
     * Get the absolute path to the plugin zip file from the repository.
     *
     * @param string $plugin_slug The slug of the plugin (e.g., plugin-folder/plugin-file.zip).
     * @return string|WP_Error Absolute file path or WP_Error on failure.
     */
    public function get_plugin( $plugin_slug ) {
        if ( empty( $plugin_slug ) ) {
            return new WP_Error( 'invalid_slug', 'Plugin cannot be empty', array( 'status' => 400 ) );
        }

        // Validate and sanitize the plugin slug.

        if ( ! is_string( $plugin_slug ) ) {
            return new WP_Error( 'smliser_repo_error', 'Plugin slugs must be a string.', array( 'status' => 400 ) );
        }

        $slug   = Smliser_Plugin::normalize_slug( $plugin_slug );

        // Construct the absolute file path.
        $file_path = $this->set_path( $slug, 'plugins' );

        // Check if the file exists in the repository
        if ( ! $this->repo->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', 'Plugin file not found');
        }

        return $file_path;
    }

    /**
     * Delete a file from the repository.
     *
     * @param string $slug Name of the file to delete.
     * @return true|WP_Error True on success or WP_Error on failure.
     */
    public function delete( $slug ) {
        $plugin_basename = explode( '/', $slug );
        $file_path = $this->set_path( $plugin_basename[0] );

        if ( is_wp_error( $file_path ) ) {
            return $file_path;
        }

        if ( ! $this->repo->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found', 'smart-license-server' ) );
        }

        if ( ! $this->repo->delete( $file_path, true ) ) {
            return new WP_Error( 'file_delete_failed', __( 'Failed to delete file', 'smart-license-server' ) );
        }

        return true;
    }

    /**
     * Safely upload a plugin into the repository.
     *
     * @param array $file The uploaded file details.
     * @param string $new_name The new name of the file.
     * @return true|WP_Error True on success or WP_Error on failure.
     */
    public function upload_to_repository( $file, $new_name ) {
        $tmp_name   = $file['tmp_name'];

        if ( ! is_uploaded_file( $tmp_name ) ) {
            return new WP_Error( 'invalid_temp_file', 'The temporary file is not valid.' );
        }

        $plugin_dir     = $this->get_plugins_dir();
        $file_name      = $this->valid_filename( $file['name'] );
        $file_type_info = wp_check_filetype( $file_name );

        if ( $file_type_info['ext'] !== 'zip' ) {
            return new WP_Error( 'invalid_file_type', 'Invalid file type, the plugin must be in zip format.' );
        }

        $file_name = str_ends_with( $new_name, $file_type_info['ext'] ) ? $new_name: $new_name . '.' .$file_type_info['ext'];

        // Create a base folder.
        $folder_parts   = explode( '.', $file_name );
        $base_name      = sanitize_file_name( $folder_parts[0] );
        $base_folder    = trailingslashit( $plugin_dir ) . $base_name;

        if ( ! $this->repo->is_dir( $base_folder )  && ! $this->repo->mkdir( $base_folder, FS_CHMOD_FILE  ) ) {
            return new WP_Error( 'smliser_repo_error', 'Unable to create a directory' );
        }

        $new_path = trailingslashit( $base_folder ) . sanitize_file_name( $file_name );
        
        if ( ! $this->repo->move( $tmp_name, $new_path ) ) {
            return new WP_Error( 'failure_to_upload', 'This plugin already exists, try updating it.' );
        }

        // Ensure the ZIP file has the correct permissions.
        $this->repo->chmod( $new_path, FS_CHMOD_FILE );
        // The plugin slug.
        return untrailingslashit( $base_name . '/' . $file_name );
    }

    /**
     * Safely update an existing plugin the repository.
     *
     * @param array $file The uploaded file details.
     * @param string $location The file to update.
     * @return true|WP_Error True on success or WP_Error on failure.
     */
    public function update_plugin( $file, $slug ) {
        $repo_dir       = $this->repo_dir;
        $file_name      = $file['name'];
        $tmp_name       = $file['tmp_name'];
        $file_mime      = wp_check_filetype( $file_name );
        $folder         = explode( '.', $file_name )[0];

        if ( $file_mime['ext'] !== 'zip' ) {
            return new WP_Error( 'invalid_file_type', 'Invalid file type, the plugin must be in zip format.' );
        }

        $original_plugin_path = $this->get_plugin( Smliser_Plugin::normalize_slug( $slug ) );

        if ( is_wp_error( $original_plugin_path ) ) {
            return $original_plugin_path;
        }

        // Attempt to replicate the original plugin path with and check if the upload will affect unintended plugin.
        $pseudo_folder  = explode( '.', $file_name );
        $new_path       = $this->set_path(  $pseudo_folder[0] . '/' . $file_name, 'plugins' ); 

        if ( $new_path !==  $original_plugin_path ) {
            return new WP_Error( 'smliser_repo_error_file_mismatch', 'The uploaded plugin file "' . $file_name . '" does not match the file "' . basename( $original_plugin_path ) . '" on this repository.' );
        }

        if ( ! $this->repo->move( $tmp_name, $new_path, true ) ) {
           return new WP_Error( 'smliser_repo_error_failure_to_move', 'Failed to move uploaded file to the repository' );
        }
    
        $this->repo->chmod( $new_path, FS_CHMOD_FILE );

        // The plugin slug.
        return Smliser_Plugin::normalize_slug( $slug );
    }

    /**
     * Open the zip file and get the plugin's readme.txt file.
     *
     * Assumes the file is located at "plugin-folder/readme.txt" within the zip archive.
     *
     * @param string $path The absolute path to the zip file.
     * @return string|WP_Error The contents of the readme.txt file or a WP_Error object on failure.
     */
    public function get_readme_txt( $path ) {
        // Check if the file exists in the repository.
        if ( ! $this->repo->exists( $path ) ) {
            return new WP_Error( 
                'smliser_repo_error', 
                __( 'File does not exist at path', 'smart-license-server' ), 
                array( 'status' => 404 ) 
            );
        }

        $zip = new ZipArchive();
        $opened = $zip->open( $path );
        if ( true !== $opened ) {
            return new WP_Error( 
                'smliser_repo_error', 
                __( 'Failed to open the zip file.', 'smart-license-server' ), 
                array( 'status' => 500 ) 
            );
        }

        // Get the folder name from the zip file by examining the first entry.
        $first_file_name = $zip->getNameIndex( 0 );
        $plugin_folder   = strstr( $first_file_name, '/', true );

        if ( ! $plugin_folder ) {
            $zip->close();
            return new WP_Error( 
                'smliser_repo_error', 
                __( 'Failed to determine the plugin folder in the zip archive.', 'smart-license-server' ), 
                array( 'status' => 500 ) 
            );
        }

        // Construct the expected path to the readme.txt file.
        $readme_path = $plugin_folder . '/readme.txt';
        $readme_contents = $zip->getFromName( $readme_path );

        $zip->close();

        if ( false === $readme_contents ) {
            return new WP_Error( 
                'smliser_repo_error', 
                __( 'The readme.txt file does not exist in the expected location.', 'smart-license-server' ), 
                array( 'status' => 404 ) 
            );
        }

        return $readme_contents;
    }


    /**
     * Get the plugin info (index text) from the readme.txt file in the plugin zip.
     *
     * @param string $plugin_slug The slug of the plugin zip file.
     * @return string The plugin description or an error message.
     */
    public function get_description( $plugin_slug ) {
        $plugin_slug      = Smliser_Plugin::normalize_slug( $plugin_slug );
        $zipped_file_path = $this->get_plugin( $plugin_slug );

        if ( is_wp_error( $zipped_file_path ) ) {
            return '';
        }

        $readme_contents = $this->get_readme_txt( $zipped_file_path );
        if ( is_wp_error( $readme_contents ) ) {
            return '';
        }

        $lines        = explode( "\n", $readme_contents );
        $render_text  = '';
        $exclude      = false;

        foreach ( $lines as $line ) {
            $line = trim( $line );
            // Skip the plugin name
            if ( str_starts_with( $line, '===' ) && str_ends_with( $line, '===' ) ) {
                continue;
            }
            // Skip metadata lines
            $meta_data = array( 'Contributors:', 'Tags:', 'Stable tag:', 'Requires PHP:', 'License:', 'License URI:', 'Requires at least:', 'tested:', 'tested up to:' );
            foreach( $meta_data as $unwanted ) {
                if ( strpos( $line, $unwanted ) !== false ) {
                    $exclude = true;
                    break;
                }
            }

            // Match section headers
            if ( preg_match( '/^==\s*(.*?)\s*==$/', $line, $matches ) ) {
                $section_title = strtolower( $matches[1] );

                // Determine if the section should be excluded
                if ( in_array( $section_title, [ 'installation', 'changelog', 'frequently asked questions', 'screenshots' ] ) ) {
                    $exclude = true;
                } else {
                    $exclude = false;
                }

                // Always include section headers unless excluded
                if ( ! $exclude ) {
                    $render_text .= ltrim( $line . "\n" );
                }

                continue;
            }

            // Include content if not in an excluded section
            if ( ! $exclude ) {
                $render_text .= $line . "\n";
            }
        }

        return $this->parse( esc_html( trim( $render_text ) ) );
    }


    /**
     * Get plugin short description.
     *
     * Extracts the short description used for SEO from the plugin's readme.txt file.
     *
     * @param string $plugin_slug The slug of the plugin zip file.
     * @return string The plugin short description or an error message.
     */
    public function get_short_description( $plugin_slug ) {
        $plugin_slug      = Smliser_Plugin::normalize_slug( $plugin_slug );
        $zipped_file_path = $this->get_plugin( $plugin_slug );

        if ( is_wp_error( $zipped_file_path ) ) {
            return '';
        }

        $readme_contents  = $this->get_readme_txt( $zipped_file_path );

        // Check if we successfully retrieved the readme.txt contents.
        if ( is_wp_error( $readme_contents ) || empty( $readme_contents ) ) {
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

        return ! empty( $short_description ) ? $short_description : 'Short description not found in readme.txt.';
    }



    /**
     * Get plugin changelog
     *
     * @param string $plugin_slug The slug of the plugin zip file.
     * @return string The plugin changelog or an error message.
     */
    public function get_changelog( $plugin_slug ) {
        $plugin_slug    = Smliser_Plugin::normalize_slug( $plugin_slug );
        $file_path      = $this->get_plugin( $plugin_slug );

        if ( is_wp_error( $file_path ) ) {
            return '';
        }

        $raw_text       = $this->get_readme_txt( $file_path );

        if ( is_wp_error( $raw_text ) ) {
            return '';
        }

        // Look for the "== Changelog ==" section in the readme.txt
        if ( preg_match( '/==\s*Changelog\s*==\s*(.+?)(==|$)/s', $raw_text, $matches ) ) {
            return $this->parse( esc_html( trim( $matches[1] ) ) );
        }

        return '';
    }

    /**
     * Get plugin installtion guide
     *
     * @param string $plugin_slug The slug of the plugin zip file.
     * @return string The plugin installation text or an error message.
     */
    public function get_installation_text( $plugin_slug ) {
        $plugin_slug        = Smliser_Plugin::normalize_slug( $plugin_slug );
        $zipped_file_path   = $this->get_plugin( $plugin_slug );

        if ( is_wp_error( $zipped_file_path  ) ) {
            return '';
        }

        $readme_contents    = $this->get_readme_txt( $zipped_file_path );

        if ( is_wp_error( $readme_contents ) ) {
            return '';
        }
        // Look for the "== Changelog ==" section in the readme.txt
        if ( preg_match( '/==\s*Installation\s*==\s*(.+?)(==|$)/s', $readme_contents, $matches ) ) {
            return $this->parse( esc_html( trim( $matches[1] ) ) );
        }

        return 'Changelog not available.';
    }

    /*
    |----------
    | Utils
    |----------
    */

    /**
     * Construct a path to a resource in the repository.
     * 
     * @param string $slug  The file slug.
     * @param string $dir   The directory being accessed {
     *                          valid values are themes and plugins, defaults to `plugins`
     * }
     */
    public function set_path( $path, $dir = 'plugins' ) {
        if ( 'themes' === $dir ) {
            $path = trailingslashit( $this->repo_dir ) . $dir . '/' . $path;
        } else {
            $path = trailingslashit( $this->repo_dir ) . 'plugins/' . $path;
        }

        return sanitize_and_normalize_path( $path );
    }

    /**
     * Provide a valid file name
     */
    private function valid_filename( $filename ) {
        return sanitize_file_name( $filename );
    }

    /**
     * Markdown to html parser.
     * 
     * @param string $text  The markdown text to parse.
     * @return string $html A HTML document.
     */
    public function parse( $text ) {
        global $smliser_md_html;
        return $smliser_md_html->parse( $text );
    }

    /**
     * Add a notice if file system is not direct.
     */
    private function add_notice() {
        $errors = $this->errors;
        add_action( 'admin_notices', function() {
            $notice = '<div class="notice notice-warning">';
            foreach( $this->errors as $code => $error ) {
                $notice .= "<p>[$code] $error</p>";
            }
            $notice .= '</div>';

            echo wp_kses_post( $notice );
        });
    }

    /**
     * Get a plugin file size
     * 
     * @param $file Full path to the file
     */
    public function size( $file ) {
        return $this->repo->size( $file );
    }

    /**
     * Checks if a plugin file is readable
     * 
     * @param $file Full path to file.
     * @uses $wp_filesystem::is_readable
     */
    public function is_readable( $file ) {
        return $this->repo->is_readable( $file );
    }

    /**
     * Check if a given file exists
     * 
     * @param string $file
     * @return bool  True when file exists, false otherwise.
     */
    public function exists( $file ) {
        if ( ! is_string( $file ) ) {
            return false;
        }
        return $this->repo->exists( $file );
    }

    /**
     * Outputs a file.
     * 
     * @param $file Full path to file
     * @param int $start The start byte.
     * @param int $length The length of bytes to read.
     */
    public function readfile( $file, $start = 0, $length = 0 ) {
        // phpcs:disable
		$handle = @fopen( $file, 'rb' );

		if ( false === $handle ) {
			return false;
		}

        $start          = 0;
        $length         = $length ? $length : @filesize( $file ); 
		$read_length    = defined( 'SMLISER_CHUNK_SIZE' ) ? (int) SMLISER_CHUNK_SIZE : 1024 * 1024;

		if ( $length ) {
			$end = $start + $length - 1;

			@fseek( $handle, $start ); 
			$p = @ftell( $handle ); 

			while ( ! @feof( $handle ) && $p <= $end ) { 
				// Must not exceed file length.
				if ( $p + $read_length > $end ) {
					$read_length = $end - $p + 1;
				}

				echo @fread( $handle, $read_length );
				$p = @ftell( $handle ); 

				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		} else {
			while ( ! @feof( $handle ) ) {
				echo @fread( $handle, $read_length );
				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		}

		return @fclose( $handle );
        // phpcs:enable

    }
}

/**
 * Global instance of the Smliser Repository class.
 *
 * This holds a singleton instance of the Smliser_Repository class,
 * which is used to manage repository-related operations across the application.
 *
 * @global Smliser_Repository $smliser_repo Singleton instance of the repository handler.
 */
$GLOBALS['smliser_repo'] = Smliser_Repository::instance();
