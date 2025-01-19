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

class Smliser_Repository {

    /**
     * @var bool Whether WP can directly access thefilesystem.
     */
    protected $is_loaded = false;

    /**
     * @var string The directory for the repository.
     */
    protected $repo_dir;
    

    /**
     * @var Smliser_Repository
     */
    protected static $instance = null;
    
    /**
     * @var wp_filesystem $repo The WordPress filesystem class.
     */
    protected $repo = null;

    /**
     * Class constructor
     */
    public function __construct() {
        global $wp_filesystem;
        $this->repo_dir = SMLISER_REPO_DIR;
        $this->initialize_filesystem();

        if ( $this->is_loaded ) {
            $this->repo = $wp_filesystem;
        }
    }

    /**
     * Initialize the WordPress filesystem.
     */
    private function initialize_filesystem() {
        if ( ! function_exists( 'request_filesystem_credentials' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $creds = request_filesystem_credentials( '', '', false, false, null );

        if ( ! WP_Filesystem( $creds ) ) {
            $this->add_connection_notice();
        } else {
            $this->is_loaded = true;
        }

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
     * Get Repository Directory
     */
    public function get_repo_dir() {
        return $this->repo_dir;
    }

    /*
    |--------------
    | CRUD methods
    |--------------
    */

    /**
     * Get all files in the repository.
     *
     * @return array|WP_Error List of files or WP_Error on failure.
     */
    public function get_all_plugin_files() {
        global $wp_filesystem;

        if ( ! $wp_filesystem->is_dir( $this->repo_dir ) ) {
            return new WP_Error( 'directory_not_found', __( 'Repository directory not found', 'smliser' ) );
        }

        $files = $wp_filesystem->dirlist( $this->repo_dir );
        if ( is_array( $files ) ) {
            $file_names = array_keys( $files );
            // Exclude .htaccess file from the list
            $filtered_files = array_filter( $file_names, function( $file ) {
                return $file !== '.htaccess';
            } );
            return $filtered_files;
        }

        return new WP_Error( 'directory_read_failed', __( 'Failed to read directory', 'smliser' ) );
    }


    /**
     * Get a plugin from the repository.
     *
     * @param string $plugin_slug The slug of the plugin (e.g., plugin-folder/plugin-file.zip).
     * @return string|WP_Error Absolute file path or WP_Error on failure.
     */
    public function get_plugin( $plugin_slug ) {
        
        if ( empty( $plugin_slug ) ) {
            return new WP_Error( 'invalid_slug', 'Plugin cannot be empty' );
        }
        // Validate and sanitize the plugin slug.
        $slug_parts         = explode( '/', $plugin_slug );
        $sanitized_parts    = array_map( 'sanitize_text_field', $slug_parts );
        $sanitized_slug     = implode( '/', $sanitized_parts );
        $sanitized_slug     = sanitize_and_normalize_path( $sanitized_slug );
        // Check for directory traversal attempts.
        if ( strpos( $sanitized_slug, '..' ) !== false ) {
            return new WP_Error( 'invalid_slug', 'Invalid plugin slug' );
        }

        global $wp_filesystem;

        // Construct the absolute file path.
        $file_path = trailingslashit( $this->repo_dir ) . $sanitized_slug;

        // Check if the file exists in the repository
        if ( ! $wp_filesystem->exists( $file_path ) ) {
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
        global $wp_filesystem;
        $plugin_basename = explode( '/', $slug );
        $file_path = $this->repo_dir . '/' . $plugin_basename[0];

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found', 'smliser' ) );
        }

        if ( ! $wp_filesystem->delete( $file_path, true ) ) {
            return new WP_Error( 'file_delete_failed', __( 'Failed to delete file', 'smliser' ) );
        }

        return true;
    }

    /**
     * Safely upload a plugin into the repository.
     *
     * @param array $file The uploaded file details.
     * @return true|WP_Error True on success or WP_Error on failure.
     */
    public function upload_to_repository( $file ) {
        global $wp_filesystem;

        $repo_dir       = $this->repo_dir;
        $file_name      = $file['name'];
        $tmp_name       = $file['tmp_name'];
        $file_type_info = wp_check_filetype( $file_name );

        if ( $file_type_info['ext'] !== 'zip' ) {
            return new WP_Error( 'invalid_file_type', 'Invalid file type, the plugin must be in zip format.' );
        }

        // Create a base folder.
        $folder_parts   = explode( '.', $file_name );
        $base_name      = sanitize_file_name( $folder_parts[0] );
        $base_folder    = trailingslashit( $repo_dir ) . $base_name;

        if ( ! $wp_filesystem->is_dir( $base_folder ) ) {
            $wp_filesystem->mkdir( $base_folder, 0755 );
        }

        $plugin_basename = trailingslashit( $base_folder ) . sanitize_file_name( $file_name );
        
        if ( ! $wp_filesystem->move( $tmp_name, $plugin_basename ) ) {
            return new WP_Error( 'failure_to_upload', 'This plugin already exists, try updating it.' );
        }

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
        global $wp_filesystem;

        $repo_dir       = $this->repo_dir;
        $file_name      = $file['name'];
        $tmp_name       = $file['tmp_name'];
        $file_mime      = wp_check_filetype( $file_name );
        $folder         = explode( '.', $file_name )[0];

        if ( $file_mime['ext'] !== 'zip' ) {
            return new WP_Error( 'invalid_file_type', 'Invalid file type, the plugin must be in zip format.' );
        }

        $original_plugin_path = $this->get_plugin( sanitize_and_normalize_path( $slug ) );

        if ( is_wp_error( $original_plugin_path ) ) {
            return $original_plugin_path;
        }

        // Attempt to replicate the original plugin path with and check if the upload will affect unintended plugin.
        $pseudo_folder      = explode( '.', $file_name );
        $plugin_basename    = $repo_dir . '/' . sanitize_and_normalize_path( $pseudo_folder[0] . '/' . $file_name ); 

        if ( $plugin_basename !==  $original_plugin_path ) {
            return new WP_Error( 'file_mismatch', 'The uploaded plugin is not same as the original.' );
        }

        if ( ! $wp_filesystem->move( $tmp_name, $plugin_basename, true ) ) {
           return new WP_Error( 'failure_to_move', 'Failed to move uploaded file to the repository' );
        }
    
        // The plugin slug.
        return sanitize_and_normalize_path( $slug );
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
                sprintf( __( 'File does not exist at path: %s', 'smart-license-server' ), esc_html( $path ) ), 
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
        $repo_dir         = $this->get_repo_dir();
        $zipped_file_path = trailingslashit( $repo_dir ) . $plugin_slug;

        if ( ! $this->repo->exists( $zipped_file_path ) || ! $this->is_readable( $zipped_file_path ) ) {
            return 'Plugin file does not exist or is not readable.';
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

        return 'Unable to read plugin file.';
    }


    /**
     * Get plugin short description
     *
     * @param string $plugin_slug The slug of the plugin zip file.
     * @return string The plugin description or an error message.
     */
    public function get_short_description( $plugin_slug ) {
        $plugin_slug        = Smliser_Plugin::normalize_slug( $plugin_slug );
        $repo_dir           = $this->get_repo_dir();
        $zipped_file_path   = trailingslashit( $repo_dir ) . $plugin_slug;
        $zip                = new ZipArchive;

        if ( @$zip->open( $zipped_file_path ) === TRUE ) {
            // Loop through the files in the zip archive
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $file_name = $zip->getNameIndex( $i );

                // Check if this file is the readme.txt file
                if ( preg_match( '/^[^\/]+\/readme\.txt$/', $file_name ) ) {
                    // Read the contents of the readme.txt file
                    $readme_contents = $zip->getFromName( $file_name );

                    // Close the zip archive
                    $zip->close();

                    // Look for the "== Description ==" section in the readme.txt
                    if ( preg_match( '/==\s*Description\s*==\s*(.+?)(==|$)/s', $readme_contents, $matches ) ) {
                        return $this->parse( esc_html( trim( $matches[1] ) ) );
                    } else {
                        return 'Description section not found in the readme.txt file.';
                    }
                }
            }
            // Close the zip archive if readme.txt is not found
            $zip->close();
            return 'readme.txt not found in the plugin file.';
        } else {
            return 'Unable to read plugin file.';
        }
    }

    /**
     * Get plugin changelog
     *
     * @param string $plugin_slug The slug of the plugin zip file.
     * @return string The plugin changelog or an error message.
     */
    public function get_changelog( $plugin_slug ) {
        $plugin_slug        = Smliser_Plugin::normalize_slug( $plugin_slug );
        $repo_dir = $this->get_repo_dir();
        $zipped_file_path = trailingslashit( $repo_dir ) . $plugin_slug;
        $zip = new ZipArchive;

        if ( $zip->open( $zipped_file_path ) === TRUE ) {
            // Loop through the files in the zip archive
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $file_name = $zip->getNameIndex( $i );

                // Check if this file is the readme.txt file
                if ( preg_match( '/^[^\/]+\/readme\.txt$/', $file_name ) ) {
                    // Read the contents of the readme.txt file
                    $readme_contents = $zip->getFromName( $file_name );

                    // Close the zip archive
                    $zip->close();

                    // Look for the "== Changelog ==" section in the readme.txt
                    if ( preg_match( '/==\s*Changelog\s*==\s*(.+?)(==|$)/s', $readme_contents, $matches ) ) {
                        return $this->parse( esc_html( trim( $matches[1] ) ) );
                    } else {
                        return 'Changelog section not found in the readme.txt file.';
                    }
                }
            }
            // Close the zip archive if readme.txt is not found
            $zip->close();
            return 'readme.txt not found in the plugin file.';
        } else {
            return 'Failed to open the plugin file.';
        }
    }

    /**
     * Get plugin installtion guide
     *
     * @param string $plugin_slug The slug of the plugin zip file.
     * @return string The plugin installation text or an error message.
     */
    public function get_installation_text( $plugin_slug ) {
        $plugin_slug        = Smliser_Plugin::normalize_slug( $plugin_slug );
        $repo_dir           = $this->get_repo_dir();
        $zipped_file_path   = trailingslashit( $repo_dir ) . $plugin_slug;
        $zip                = new ZipArchive;

        if ( $zip->open( $zipped_file_path ) === TRUE ) {
            // Loop through the files in the zip archive
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $file_name = $zip->getNameIndex( $i );

                // Check if this file is the readme.txt file
                if ( preg_match( '/^[^\/]+\/readme\.txt$/', $file_name ) ) {
                    // Read the contents of the readme.txt file
                    $readme_contents = $zip->getFromName( $file_name );

                    // Close the zip archive
                    $zip->close();

                    // Look for the "== Changelog ==" section in the readme.txt
                    if ( preg_match( '/==\s*Installation\s*==\s*(.+?)(==|$)/s', $readme_contents, $matches ) ) {
                        return $this->parse( esc_html( trim( $matches[1] ) ) );
                    } else {
                        return 'Changelog section not found in the readme.txt file.';
                    }
                }
            }
            // Close the zip archive if readme.txt is not found
            $zip->close();
            return 'readme.txt not found in the zip file.';
        } else {
            return 'Failed to open the zip file.';
        }
    }

    /**
     * Markdown to html parser
     */
    public function parse( $text ) {
        global $smliser_md_html;
        return $smliser_md_html->parse( $text );
    }

    /*
    |----------
    | Utils
    |----------
    */

    /**
     * Add a notice if file system is not direct.
     */
    private function add_connection_notice() {
        add_action( 'admin_notices', function() {
            $message = '<div class="notice notice-warning"><p>
            Your server filesystem configuration requires authentication. In other to use Smart License Server, you will
            need to configure it to use FTP credentials.</p></div>';
            echo wp_kses_post( $message );
        }  );
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
        return $this->repo->exists( $file );
    }

    /**
     * Outputs a file.
     * 
     * @param $file Full path to file
     */
    public function readfile( $file ) {
        
        if ( ! defined( 'SMLISER_CHUNK_SIZE' ) ) {
			define( 'SMLISER_CHUNK_SIZE', 1024 * 1024 );
		}
        // phpcs:disable
		$handle = @fopen( $file, 'r' );

		if ( false === $handle ) {
			return false;
		}

        $start          = 0;
        $length         = @filesize( $file ); 
		$read_length    = (int) SMLISER_CHUNK_SIZE;

		if ( $length ) {
			$end = $start + $length - 1;

			@fseek( $handle, $start ); 
			$p = @ftell( $handle ); 

			while ( ! @feof( $handle ) && $p <= $end ) { 
				// Don't run past the end of file.
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
