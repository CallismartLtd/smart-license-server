<?php
/**
 * The Database table definition class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The database table blueprint
 */
final class Tables {

    /**
     * Retrieve all database tables
     * 
     * @return array
     */
    public static function tables() : array {
        return array(
            /**
             * The licenses table
             */
            \SMLISER_LICENSE_TABLE   => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'user_id MEDIUMINT(9) DEFAULT NULL',
                'license_key VARCHAR(300) NOT NULL UNIQUE',
                'service_id VARCHAR(300) NOT NULL',
                'app_prop VARCHAR(600) DEFAULT NULL',
                'max_allowed_domains MEDIUMINT(9) DEFAULT NULL',
                'status VARCHAR(30) DEFAULT NULL',
                'start_date DATE DEFAULT NULL',
                'end_date DATE DEFAULT NULL',
                'INDEX service_id_index (service_id)',
                'INDEX status_index (status)',
                'INDEX user_id_index (user_id)',
            ),

            /**
             * License meta table
             */
            \SMLISER_LICENSE_META_TABLE     => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'license_id BIGINT(20) UNSIGNED NOT NULL',
                'meta_key VARCHAR(255) NOT NULL',
                'meta_value LONGTEXT DEFAULT NULL',
                'INDEX license_id_index (license_id)',
                'INDEX meta_key_index (meta_key)',
            ),

            /**
             * The plugins table
             */
            \SMLISER_PLUGIN_ITEM_TABLE   => array(
                'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'name VARCHAR(255) NOT NULL',
                'slug VARCHAR(300) DEFAULT NULL',
                'status VARCHAR(300) DEFAULT \'active\'',
                'author VARCHAR(255) DEFAULT NULL',
                'author_profile VARCHAR(255) DEFAULT NULL',
                'download_link VARCHAR(400) DEFAULT NULL',
                'created_at DATETIME DEFAULT NULL',
                'last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX plugin_download_link_index (download_link)',
                'INDEX plugin_slug_index (slug)',
                'INDEX plugin_author_index (author)',
                'INDEX plugin_status_index (status)',
            ),

            /**
             * The plugins meta table
             */
            \SMLISER_PLUGIN_META_TABLE   => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'plugin_id BIGINT(20) UNSIGNED NOT NULL',
                'meta_key VARCHAR(255) NOT NULL',
                'meta_value LONGTEXT DEFAULT NULL',
                'INDEX plugin_id_index (plugin_id)',
                'INDEX meta_key_index (meta_key)',
            ),

            /**
             * The themes table
             */
            \SMLISER_THEME_ITEM_TABLE    => array(
                'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'name VARCHAR(255) NOT NULL',
                'slug VARCHAR(300) DEFAULT NULL',
                'author VARCHAR(255) DEFAULT NULL',
                'status VARCHAR(55) DEFAULT \'active\'',
                'download_link VARCHAR(400) DEFAULT NULL', // Could be external URL.
                'created_at DATETIME DEFAULT NULL',
                'last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX theme_slug_index (slug)',
                'INDEX theme_author_index (author)',
                'INDEX theme_download_link_index (download_link)',
                'INDEX theme_status_index (status)',
            ),

            /**
             * Theme meta table
             */
            \SMLISER_THEME_META_TABLE   => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'theme_id BIGINT(20) UNSIGNED NOT NULL',
                'meta_key VARCHAR(255) NOT NULL',
                'meta_value LONGTEXT DEFAULT NULL',
                'INDEX theme_id_index (theme_id)',
                'INDEX meta_key_index (meta_key)',
            ),

            /**
             * Other hosted application(software) table.
             */
            \SMLISER_SOFTWARE_TABLE     => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'name VARCHAR(255) NOT NULL',
                'slug VARCHAR(300) UNIQUE NOT NULL',
                'status VARCHAR(55) DEFAULT \'active\'',
                'author VARCHAR(255) DEFAULT NULL',
                'download_link VARCHAR(400) DEFAULT NULL',
                'created_at DATETIME DEFAULT NULL',
                'last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX software_slug_index (slug)',
                'INDEX software_author_index (author)',
            ),

            /**
             * Meta table for other hosted application types.
             */
            \SMLISER_SOFTWARE_META_TABLE    => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'software_id BIGINT(20) UNSIGNED NOT NULL',
                'meta_key VARCHAR(255) NOT NULL',
                'meta_value LONGTEXT DEFAULT NULL',
                'INDEX app_id_index (software_id)',
                'INDEX meta_key_index (meta_key)',
            ),

            /**
             * Unified Analytics Logs Table (The "Source of Truth")
             * Optimized for high-speed writes and indexed for on-the-fly filtering.
             */
            \SMLISER_ANALYTICS_LOGS_TABLE => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'app_type VARCHAR(20) NOT NULL',
                'app_slug VARCHAR(100) NOT NULL',
                'event_type VARCHAR(50) NOT NULL',
                'fingerprint CHAR(64) DEFAULT NULL',
                'created_at DATETIME DEFAULT CURRENT_TIMESTAMP',
                'INDEX app_identity_idx (app_type, app_slug)',
                'INDEX event_type_idx (event_type)',
                'INDEX lookup_idx (app_slug, event_type, created_at)',
                'INDEX cleanup_idx (created_at)',
            ),

            /**
             * Daily Summary Table (The "Calculator")
             * Stores pre-aggregated totals to ensure the dashboard never slows down.
             */
            \SMLISER_ANALYTICS_DAILY_TABLE => array(
                'app_type VARCHAR(20) NOT NULL',
                'app_slug VARCHAR(100) NOT NULL',
                'stats_date DATE NOT NULL',
                'event_type VARCHAR(50) NOT NULL',
                'total_count INT(10) UNSIGNED DEFAULT 0',
                'unique_count INT(10) UNSIGNED DEFAULT 0',
                'PRIMARY KEY (app_type, app_slug, stats_date, event_type)',
                'INDEX date_lookup (stats_date)',
            ),
            
            \SMLISER_APP_DOWNLOAD_TOKEN_TABLE   => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'app_prop VARCHAR(255) DEFAULT NULL',
                'license_key VARCHAR(255) DEFAULT NULL',
                'token VARCHAR(255) DEFAULT NULL',
                'expiry INT',
                'INDEX expiry_index(expiry)',
                'INDEX dtoken_index(token)',
            ),

            \SMLISER_MONETIZATION_TABLE     => array(
                'id BIGINT AUTO_INCREMENT PRIMARY KEY',
                'app_type VARCHAR(50) NOT NULL',
                'app_id BIGINT NOT NULL',
                'enabled TINYINT(1) DEFAULT 0',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'UNIQUE KEY unique_app_monetization (app_type, app_id)'
            )

        );
    }

    /**
     * Get a single table schema.
     *
     * @param string $table_name
     * @return array|null
     */
    public static function get( string $table_name ) {
        $tables = self::tables();
        return $tables[ $table_name ] ?? null;
    }

    /**
     * Return all table names.
     *
     * @return string[]
     */
    public static function table_names() {
        return array_keys( self::tables() );
    }
}