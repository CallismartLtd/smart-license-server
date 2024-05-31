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
     * @var bool Where WP can directly access thefilesystem.
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
     * Class constructor
     */
    public function __construct() {
        $this->repo_dir = SMLISER_REPO_DIR;
        $this->initialize_filesystem();
    }

    /**
     * Get Repository Directory
     */
    public function get_repo_dir() {
        return $this->repo_dir;
    }

    /**
     * Initialize the WordPress filesystem.
     */
    private function initialize_filesystem() {
        global $wp_filesystem;

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
        // Validate and sanitize the plugin slug.
        $slug_parts         = explode( '/', $plugin_slug );
        $sanitized_parts    = array_map( 'sanitize_text_field', $slug_parts );
        $sanitized_slug     = implode( '/', $sanitized_parts );

        // Check for directory traversal attempts.
        if ( strpos( $sanitized_slug, '..' ) !== false ) {
            return new WP_Error( 'invalid_slug', __( 'Invalid plugin slug: ' . $sanitized_slug, 'smliser' ) );
        }

        global $wp_filesystem;

        // Construct the absolute file path.
        $file_path = trailingslashit( $this->repo_dir ) . $sanitized_slug;

        // Check if the file exists in the repository
        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found: ' . $file_path, 'smliser' ) );
        }

        return $file_path;
    }


    /**
     * Write contents to a file in the repository.
     *
     * @param string $file_name Name of the file to write to.
     * @param string $contents Contents to write.
     * @return true|WP_Error True on success or WP_Error on failure.
     */
    public function write_file( $file_name, $contents ) {
        global $wp_filesystem;

        $file_path = $this->repo_dir . '/' . $file_name;

        if ( ! $wp_filesystem->put_contents( $file_path, $contents, FS_CHMOD_FILE ) ) {
            return new WP_Error( 'file_write_failed', __( 'Failed to write file', 'smliser' ) );
        }

        return true;
    }

    /**
     * Delete a file from the repository.
     *
     * @param string $file_name Name of the file to delete.
     * @return true|WP_Error True on success or WP_Error on failure.
     */
    public function delete( $file_name ) {
        global $wp_filesystem;

        $file_path = $this->repo_dir . '/' . $file_name;

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found', 'smliser' ) );
        }

        if ( ! $wp_filesystem->delete( $file_path ) ) {
            return new WP_Error( 'file_delete_failed', __( 'Failed to delete file', 'smliser' ) );
        }

        return true;
    }

    /**
     * Update a file in the repository.
     *
     * @param string $file_name Name of the file to update.
     * @param string $contents New contents for the file.
     * @return true|WP_Error True on success or WP_Error on failure.
     */
    public function update_file( $file_name, $contents ) {
        return $this->write_file( $file_name, $contents );
    }

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
     * Instance of current class
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            return self::$instance = new self();
        }
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
        
        if ( ! move_uploaded_file( $tmp_name, $plugin_basename ) ) {
            return new WP_Error( 'failure_to_move', 'Failed to move uploaded file to the repository' );
        }

        // The plugin slug.
        return untrailingslashit( $base_name . '/' . $file_name );
    }
}

$GLOBALS['smliser_repo'] = Smliser_Repository::instance();