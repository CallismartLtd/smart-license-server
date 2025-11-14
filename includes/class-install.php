<?php
/**
 * Installation management class file
 * Handles plugin activation actions.
 * 
 * @author Callistus
 * @package Smliser\classes
 * @since 1.0.0
 */

use SmartLicenseServer\Exception;

defined( 'ABSPATH' ) || exit;

class Smliser_install {

    /**
     * Database version migration callbacks
     * 
     * @var array $db_versions
     */
    private static $db_versions = array(
        '0.0.6' => array(
            [__CLASS__, 'migration_006' ],

        ),
        '0.1.1' => array(
            [__Class__, 'migration_011']
        )

    );

    /**
     * Handle plugin activation
     */
    public static function install() {
        
        $result = self::init_repo_dir();
        if ( is_smliser_error( $result ) ) {
            update_option( 'smliser_directory_error', $result->get_error_message() );
        } else {
            delete_option( 'smliser_directory_error' );
        }

        self::create_tables();
        return true;
       
    }

    /**
     * Create Database table
     */
    private static function create_tables(){
        global $wpdb;
        $tables = array(
            SMLISER_LICENSE_TABLE,
            SMLISER_PLUGIN_ITEM_TABLE,
            SMLISER_LICENSE_META_TABLE,
            SMLISER_PLUGIN_META_TABLE,
            SMLISER_API_ACCESS_LOG_TABLE,
            SMLISER_API_CRED_TABLE,
            SMLISER_APP_DOWNLOAD_TOKEN_TABLE,
            SMLISER_MONETIZATION_TABLE,
            SMLISER_PRICING_TIER_TABLE,
            SMLISER_THEME_ITEM_TABLE,
            SMLISER_THEME_META_TABLE,
            SMLISER_APPS_ITEM_TABLE,
            SMLISER_APPS_META_TABLE,
            SMLISER_BULK_MESSAGES_TABLE,
            SMLISER_BULK_MESSAGES_APPS_TABLE

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
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'user_id MEDIUMINT(9) DEFAULT NULL',
            'license_key VARCHAR(300) NOT NULL UNIQUE',
            'service_id VARCHAR(300) NOT NULL',
            'app_prop VARCHAR(600) DEFAULT NULL',
            'allowed_sites MEDIUMINT(9) DEFAULT NULL',
            'status VARCHAR(30) DEFAULT NULL',
            'start_date DATE DEFAULT NULL',
            'end_date DATE DEFAULT NULL',
            'INDEX service_id_index (service_id)',
            'INDEX item_id_index (item_id)',
            'INDEX status_index (status)',
            'INDEX user_id_index (user_id)',
        );

        self::run_db_delta( $license_details_table, $license_table_columns );

        /**
         * Plugin table
         */
        $plugin_table_name      = SMLISER_PLUGIN_ITEM_TABLE;
        $plugin_table_columns   = array(
            'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'name VARCHAR(255) NOT NULL',
            'slug VARCHAR(300) DEFAULT NULL',
            'status VARCHAR(300) DEFAULT \'active\'',
            'version VARCHAR(300) DEFAULT NULL',
            'author VARCHAR(255) DEFAULT NULL',
            'author_profile VARCHAR(255) DEFAULT NULL',
            'requires VARCHAR(9) DEFAULT NULL',
            'tested VARCHAR(9) DEFAULT NULL',
            'requires_php VARCHAR(9) DEFAULT NULL',
            'download_link VARCHAR(400) DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX download_link_index (download_link)',
            'INDEX slug_index (slug)',
            'INDEX author_index (author)',
        );

        self::run_db_delta( $plugin_table_name, $plugin_table_columns );

        /**
         * Plugin metadata table
         */
        $plugin_meta_table    = SMLISER_PLUGIN_META_TABLE;
        $plugin_meta_columns  = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'plugin_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX plugin_id_index (plugin_id)',
            'INDEX meta_key_index (meta_key)',
        );

        self::run_db_delta( $plugin_meta_table, $plugin_meta_columns );

        /**
         * Theme table
         */
        $theme_table_name    = SMLISER_THEME_ITEM_TABLE;
        $theme_table_columns = array(
            'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'name VARCHAR(255) NOT NULL',
            'slug VARCHAR(300) DEFAULT NULL',
            'status VARCHAR(55) DEFAULT \'active\'',
            'version VARCHAR(300) DEFAULT NULL',
            'author VARCHAR(255) DEFAULT NULL',
            'author_profile VARCHAR(255) DEFAULT NULL',
            'requires VARCHAR(9) DEFAULT NULL',         // WordPress min version
            'tested VARCHAR(9) DEFAULT NULL',           // Tested up to version
            'requires_php VARCHAR(9) DEFAULT NULL',
            'template VARCHAR(255) DEFAULT NULL',       // For child themes (parent slug)
            'tags VARCHAR(500) DEFAULT NULL',           // Theme tags/categories
            'download_link VARCHAR(400) DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX slug_index (slug)',
            'INDEX author_index (author)',
            'INDEX download_link_index (download_link)',
        );

        self::run_db_delta( $theme_table_name, $theme_table_columns );
    
        /**
         * Theme metadata table
         */
        $theme_meta_table    = SMLISER_THEME_META_TABLE;
        $theme_meta_columns  = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'theme_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX theme_id_index (theme_id)',
            'INDEX meta_key_index (meta_key)',
        );

        self::run_db_delta( $theme_meta_table, $theme_meta_columns );

        /**
         * Other applications table
         */
        $other_app_table_name    = SMLISER_APPS_ITEM_TABLE;
        $other_app_table_columns = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'type VARCHAR(50) NOT NULL',                 // e.g. library, saas, desktop, webapp
            'name VARCHAR(255) NOT NULL',
            'slug VARCHAR(300) UNIQUE NOT NULL',
            'status VARCHAR(55) DEFAULT \'active\'',
            'version VARCHAR(50) DEFAULT NULL',
            'short_description VARCHAR(500) DEFAULT NULL',
            'description LONGTEXT DEFAULT NULL',         // Full product description
            'changelog LONGTEXT DEFAULT NULL',           // Optional: product changelog
            'author VARCHAR(255) DEFAULT NULL',
            'author_profile VARCHAR(400) DEFAULT NULL',
            'homepage VARCHAR(400) DEFAULT NULL',
            'support_url VARCHAR(400) DEFAULT NULL',
            'download_link VARCHAR(400) DEFAULT NULL',
            'platform VARCHAR(100) DEFAULT NULL',        // e.g. "Windows", "Linux", "Web", "Cross-platform"
            'license VARCHAR(100) DEFAULT NULL',         // e.g. GPL, MIT, Proprietary
            'created_at DATETIME DEFAULT NULL',
            'last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX slug_index (slug)',
            'INDEX type_index (type)',
            'INDEX author_index (author)',
        );

        self::run_db_delta( $other_app_table_name, $other_app_table_columns );

        /**
         * Other applications metadata table
         */
        $other_app_meta_table    = SMLISER_APPS_META_TABLE;
        $other_app_meta_columns  = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'app_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX app_id_index (app_id)',
            'INDEX meta_key_index (meta_key)',
        );

        self::run_db_delta( $other_app_meta_table, $other_app_meta_columns );

        /**
         * License_metadata table
         */
        $license_meta_table     = SMLISER_LICENSE_META_TABLE;
        $license_meta_columns   = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'license_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX license_id_index (license_id)',
            'INDEX meta_key_index (meta_key)',
        );

        self::run_db_delta( $license_meta_table, $license_meta_columns );

        /**
         * API endpoint Access logs table.
         */
        $api_access_table   = SMLISER_API_ACCESS_LOG_TABLE;
        $api_access_columns = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'api_route VARCHAR(255) NOT NULL',
            'client_ip VARCHAR(45) DEFAULT NULL',
            'access_time DATETIME DEFAULT NULL',
            'status_code INT(3) DEFAULT NULL',
            'website VARCHAR(255) DEFAULT NULL',
            'request_data TEXT DEFAULT NULL',
            'response_data TEXT DEFAULT NULL',
            'INDEX api_route_index (api_route)',
            'INDEX client_ip_index (client_ip)',
            'INDEX access_time_index (access_time)',
        );
        self::run_db_delta( $api_access_table, $api_access_columns );

        /**
         * API credential table
         */
        $api_cred_table = SMLISER_API_CRED_TABLE;
        $api_cred_columns = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'user_id MEDIUMINT(9) NOT NULL',
            'permission VARCHAR(255) NOT NULL',
            'consumer_secret CHAR(70) NOT NULL UNIQUE',
            'consumer_public VARCHAR(255) NOT NULL UNIQUE',
            'token VARCHAR(255) DEFAULT NULL UNIQUE',
            'app_name TEXT DEFAULT NULL',
            'token_expiry DATETIME DEFAULT NULL',
            'last_accessed DATETIME DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'INDEX token_expiry_index(token_expiry)',
        );
        
        self::run_db_delta( $api_cred_table, $api_cred_columns );

        /**
         * APP Download token table
         */
        $dToken_table   = SMLISER_APP_DOWNLOAD_TOKEN_TABLE;
        $dToken_columns = array(
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'app_prop VARCHAR(255) DEFAULT NULL',
            'license_key VARCHAR(255) DEFAULT NULL',
            'token VARCHAR(255) DEFAULT NULL',
            'expiry INT',
            'INDEX expiry_index(expiry)',
            'INDEX dtoken_index(token)',
        );

        self::run_db_delta( $dToken_table, $dToken_columns );

        /**
         * Monetization table
         */
        $monetization_table   = SMLISER_MONETIZATION_TABLE;
        $monetization_columns = array(
            'id BIGINT AUTO_INCREMENT PRIMARY KEY',
            'item_type VARCHAR(50) NOT NULL',
            'item_id BIGINT NOT NULL',
            'enabled TINYINT(1) DEFAULT 0',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'UNIQUE KEY unique_item_monetization (item_type, item_id)'
        );
        self::run_db_delta( $monetization_table, $monetization_columns );

        /**
         * Pricing Tier table
         */
        $pricing_tier_table   = SMLISER_PRICING_TIER_TABLE;
        $pricing_tier_columns = array(
            'id BIGINT AUTO_INCREMENT PRIMARY KEY',
            'monetization_id BIGINT NOT NULL',
            'name VARCHAR(255) NOT NULL',
            'product_id VARCHAR(191) NOT NULL',
            'provider_id VARCHAR(50) NOT NULL',
            'billing_cycle VARCHAR(50) DEFAULT NULL',
            'max_sites INT DEFAULT 1',
            'features TEXT DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'INDEX monetization_id_index (monetization_id)',
        );
        self::run_db_delta( $pricing_tier_table, $pricing_tier_columns );

        /**
         * Bulk messages table schema
         */
        $bulk_messages_table    = SMLISER_BULK_MESSAGES_TABLE;
        $bulk_messages_columns  = array(
            'id BIGINT AUTO_INCREMENT PRIMARY KEY',
            'message_id VARCHAR(64) UNIQUE',
            'subject VARCHAR(255) NOT NULL',
            'body TEXT DEFAULT NULL',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'is_read TINYINT(1) DEFAULT 0',
            'INDEX smliser_bulk_msg_created_at (created_at)',
            'INDEX smliser_bulk_msg_updated_at (updated_at)',
        );

        self::run_db_delta( $bulk_messages_table, $bulk_messages_columns );

        /**
         * Message-to-App mapping table
         */
        $bulk_messages_apps_table   = SMLISER_BULK_MESSAGES_APPS_TABLE;
        $bulk_messages_apps_columns = array(
            'id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'message_id VARCHAR(64) DEFAULT NULL',
            'app_type VARCHAR(64) NOT NULL',
            'app_slug VARCHAR(191) NOT NULL',
            'UNIQUE KEY smliser_unique_message_app (message_id, app_type, app_slug)',
            'INDEX smliser_msg_app_lookup (app_type, app_slug)'
        );

        self::run_db_delta( $bulk_messages_apps_table, $bulk_messages_apps_columns );

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
    
        
        $query			= $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name );
        $table_exists 	= $wpdb->get_var( $query );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
        if ( $table_exists !== $table_name ) {
            $charset_collate = self::charset_collate();
    
            $sql = "CREATE TABLE $table_name (";
            foreach ( $columns as $column ) {
                $sql .= "$column, ";
            }
    
            $sql  = rtrim( $sql, ', ' );
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
     * Initialized the repository directory.
     *
     * @return bool|Exception True on success, Exception on failure.
     */
    private static function init_repo_dir() {
        include_once SMLISER_PATH . 'includes/exceptions/exception.php';
        include_once SMLISER_PATH . 'includes/utils/functions.php';
        include_once SMLISER_PATH . 'includes/utils/sanitization-functions.php';
        include_once SMLISER_PATH . 'includes/utils/conditional-functions.php';
        include_once SMLISER_PATH . 'includes/filesystem/class-filesystem.php';
        include_once SMLISER_PATH . 'includes/filesystem/class-repository.php';
    
        return SmartLicenseServer\Repository::create_repository_directories();
    }

    /**
     * Add status column to the plugins table
     * 
     * @version 0.0.6
     */
    public static function migration_006() {
        global $wpdb;

        $plugin_table = SMLISER_PLUGIN_ITEM_TABLE;

        // Check if 'status' column already exists
        $column = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$plugin_table} LIKE %s",
                'status'
            )
        );

        if ( empty( $column ) ) {
            // Add 'status' column
            $wpdb->query(
                "ALTER TABLE {$plugin_table} 
                ADD COLUMN status VARCHAR(55) NOT NULL DEFAULT 'active' 
                AFTER download_link"
            );
        }
    }

    /**
     * Migration of the license table to support multiple hosted applications.
     * Before now this table only supported hosted plugins, but now needs to support other hosted apps.
     *
     * @version 0.1.1
     */
    public static function migration_011() {
        $db    = smliser_dbclass();
        $table = SMLISER_LICENSE_TABLE;

        $results = $db->get_results( "SELECT `id`, `item_id` FROM {$table}" );

        $plugin_ids = [];
        foreach ( $results as $row ) {
            $plugin_ids[ $row['id'] ] = $row['item_id'];
        }

        set_transient( 'smliser_db_migrate_011', $plugin_ids, WEEK_IN_SECONDS );

        // --- Alter item_id to app_prop if column exists ---
        $column_exists = $db->get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, 'item_id']
        );

        if ( $column_exists ) {
            $sql = "ALTER TABLE `{$table}` CHANGE COLUMN `item_id` `app_prop` VARCHAR(600) DEFAULT NULL";
            $db->query( $sql );

            // Move plugin data to new column
            foreach ( $plugin_ids as $row_id => $plugin_id ) {
                $plugin = Smliser_Plugin::get_plugin( $plugin_id );
                if ( ! $plugin ) {
                    continue;
                }
                $app_prop = sprintf( '%s/%s', $plugin->get_type(), $plugin->get_slug() );
                $db->update( $table, [ 'app_prop' => $app_prop ], [ 'id' => $row_id ] );
            }
        }

        // --- Alter allowed_sites to max_allowed_domains if column exists ---
        $column_exists = $db->get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            [$table, 'allowed_sites']
        );

        if ( $column_exists ) {
            $sql = "ALTER TABLE `{$table}` CHANGE COLUMN `allowed_sites` `max_allowed_domains` VARCHAR(600) DEFAULT NULL";
            $db->query( $sql );
        }
    }



    /**
     * Handle ajax update
     */
    public static function ajax_update() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        $repo_version = get_option( 'smliser_repo_version', 0 );
        if ( SMLISER_VER === $repo_version ) {
            smliser_send_json_error( array( 'message' => 'No upgrade needed' ) );
        }

        if ( self::install() )  {
            $all_func = self::$db_versions[SMLISER_DB_VER] ?? [];

            foreach ( $all_func as $func ) {
                if ( is_callable( $func ) ) {
                    call_user_func( $func );
                }
            }
           
        }
        update_option( 'smliser_repo_version', SMLISER_VER );

        smliser_send_json_success( array( 'message' => 'The repository has been migrated from version "' . $repo_version . '" to version "' . SMLISER_VER ) );
    }

}
