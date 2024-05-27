<?php
/**
 * Installation management class file
 * Handles plugin activation actions.
 * 
 * @author Callistus
 * @package Smliser\classes
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Smliser_install {



    /**
     * Handle plugin activation
     */
    public static function install() {
        
        $result = self::create_directory();
        if ( is_wp_error( $result ) ) {
            update_option( 'smliser_directory_error', $result->get_error_message() );
        }

        self::create_tables();
       
    }

    /**
     * Create Database table
     */
    private static function create_tables(){
        global $wpdb;
        $tables = array(
            SMLISER_LICENSE_TABLE,
            SMLISER_PRODUCT_TABLE,
        );

        foreach( $tables as $table ) {
            // phpcs:disable
            $query			= $wpdb->prepare( "SHOW TABLES LIKE %s", $table );
            $table_exists 	= $wpdb->get_var( $query );
            // phpcs:enable

            if ( $table !== $table_exists ) {
                self::table_schema();
            }
         }
    }

    /**
     * Database table schema.
     */
    private static function table_schema() {
        /**
         * License details table
         */
        $license_details_table = SMLISER_LICENSE_TABLE;
        $license_table_columns = array(
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'user_id MEDIUMINT(9) DEFAULT NULL',
            'license_key VARCHAR(300) NOT NULL UNIQUE',
            'service_id VARCHAR(300) NOT NULL',
            'item_id MEDIUMINT(9) NOT NULL',
            'allowed_sites MEDIUMINT(9) DEFAULT NULL',
            'status VARCHAR(30) DEFAULT NULL',
            'start_date DATE DEFAULT NULL',
            'end_date DATE DEFAULT NULL',
            'INDEX service_id_index (service_id)',
			'INDEX status_index (status)',
			'INDEX user_id_index (user_id)',
        );

        self::run_db_delta( $license_details_table, $license_table_columns );

    /**
     * Product table
     */
    $product_table_name = SMLISER_PRODUCT_TABLE;
    $product_table_columns = array(
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'name VARCHAR(255) NOT NULL',
        'description TEXT',
        'price DECIMAL(10, 2) NOT NULL',
        'fee DECIMAL(10, 2) DEFAULT NULL',
        'license_key VARCHAR(300) DEFAULT NULL',
        'plugin_basename VARCHAR(300) DEFAULT NULL',
        'created_at DATETIME DEFAULT CURRENT_TIMESTAMP',
        'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    );

    self::run_db_delta( $product_table_name, $product_table_columns );

    }
    
    /**
     * Create Tables.
     * 
     * @param string $table_name    The table name
     * @param array $columns        The table columns.
     */
    private static function run_db_delta( $table_name, $columns ) {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
        // phpcs:disable
        $query			= $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name );
        $table_exists 	= $wpdb->get_var( $query );
        // phpcs:enable
    
        if ( $table_exists !== $table_name ) {
            $charset_collate = self::charset_collate();
    
            $sql = "CREATE TABLE $table_name (";
            foreach ( $columns as $column ) {
                $sql .= "$column, ";
            }
    
            $sql  = rtrim( $sql, ', ' ); // Remove the trailing comma and space.
            $sql .= ") $charset_collate;";
    
            // Execute the SQL query.
            dbDelta( $sql );
        }
    }

    /**
     * Retrieve the database charset and collate settings.
     *
     * This function generates a string that includes the default character set and collate
     * settings for the WordPress database, based on the global $wpdb object.
     *
     * @global wpdb $wpdb The WordPress database object.
     * @return string The generated charset and collate settings string.
     */
    private static function charset_collate() {
        global $wpdb;
        $charset_collate = '';
        if ( ! empty( $wpdb->charset ) ) {
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        }
        if ( ! empty( $wpdb->collate ) ) {
            $charset_collate .= " COLLATE $wpdb->collate";
        }
        return $charset_collate;
    }

    /**
     * Create Premium plugins directory.
     */
    private static function create_directory() {
        global $wp_filesystem;
    
        if ( ! function_exists( 'request_filesystem_credentials' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
    
        // Request filesystem credentials (this will handle FTP/SSH details if required).
        $creds = request_filesystem_credentials( '', '', false, false, null );
        
        // Initialize the filesystem.
        if ( ! WP_Filesystem( $creds ) ) {
            return new WP_Error( 'filesystem_init_failed', __( 'Failed to initialize filesystem', 'smliser' ) );
        }
    
        $repo_dir = SMLISER_REPO_DIR;
    
        if ( ! $wp_filesystem->is_dir( $repo_dir ) ) {
            if ( ! $wp_filesystem->mkdir( $repo_dir, 0755 ) ) {
                return new WP_Error( 'directory_creation_failed', __( 'Failed to create directory: ' . esc_html( $repo_dir ), 'smliser' ) );
            }
        }
    
        // Protect the directory with an .htaccess file
        $htaccess_content = "Deny from all";
        $htaccess_path = $repo_dir . '/.htaccess';
    
        if ( ! $wp_filesystem->exists( $htaccess_path ) ) {

            $wp_filesystem->put_contents( $htaccess_path, $htaccess_content, FS_CHMOD_FILE );         
        }
    }
}
