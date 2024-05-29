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
    private $is_loaded = false;

    /**
     * @var string The directory for the repository.
     */
    private $repo_dir;

    /**
     * @var Smliser_Repository
     */
    private static $instance = null;

    public function __construct() {
        $this->repo_dir = SMLISER_REPO_DIR;
        $this->initialize_filesystem();
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
        }
    }

    /**
     * Get all files in the repository.
     *
     * @return array|WP_Error List of files or WP_Error on failure.
     */
    public function get_all_files() {
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
     * Get the contents of a file in the repository.
     *
     * @param string $file_name Name of the file to read.
     * @return string|WP_Error File contents or WP_Error on failure.
     */
    public function get_file( $file_name ) {
        global $wp_filesystem;

        $file_path = $this->repo_dir . '/' . $file_name;

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found', 'smliser' ) );
        }

        $contents = $wp_filesystem->get_contents( $file_path );
        if ( false !== $contents ) {
            return $contents;
        }

        return new WP_Error( 'file_read_failed', __( 'Failed to read file', 'smliser' ) );
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
    public function delete_file( $file_name ) {
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
            //echo wp_kses_post( $message );
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
}

$GLOBALS['smliser_repo'] = Smliser_Repository::instance();