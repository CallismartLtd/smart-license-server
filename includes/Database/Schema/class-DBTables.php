<?php
/**
 * The Database table definition class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

use const SMLISER_LICENSE_TABLE, SMLISER_LICENSE_META_TABLE, SMLISER_PLUGINS_TABLE,
SMLISER_PLUGINS_META_TABLE, SMLISER_THEMES_TABLE, SMLISER_THEMES_META_TABLE, SMLISER_SOFTWARE_TABLE,
SMLISER_SOFTWARE_META_TABLE, SMLISER_ANALYTICS_LOGS_TABLE, SMLISER_ANALYTICS_DAILY_TABLE, SMLISER_APP_DOWNLOAD_TOKEN_TABLE,
SMLISER_MONETIZATION_TABLE, SMLISER_PRICING_TIER_TABLE, SMLISER_BULK_MESSAGES_TABLE, SMLISER_BULK_MESSAGES_APPS_TABLE,
SMLISER_OPTIONS_TABLE, SMLISER_OWNERS_TABLE, SMLISER_USERS_TABLE, SMLISER_ORGANIZATIONS_TABLE, SMLISER_ORGANIZATION_MEMBERS_TABLE,
SMLISER_SERVICE_ACCOUNTS_TABLE, SMLISER_ROLES_TABLE, SMLISER_ROLE_CAPABILITIES_TABLE, SMLISER_ROLE_ASSIGNMENT_TABLE;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Database table schema registry.
 *
 * Provides column definitions for all Smart License Server
 * database tables used during installation and upgrades.
 *
 * This class is intentionally static and immutable.
 *
 * @since 0.2.0
 */

final class DBTables {
    /**
     * Retrieve all database table schemas.
     *
     * Each array key represents a fully-qualified table name,
     * while the value is an ordered list of SQL column and index
     * definitions suitable for use with dbDelta().
     *
     * @return array<string, string[]>
     */
    public static function tables() : array {
        return array(
            /**
             * The licenses table
             */
            SMLISER_LICENSE_TABLE   => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'licensee_fullname VARCHAR(512) DEFAULT NULL',
                'license_key VARCHAR(300) NOT NULL UNIQUE',
                'service_id VARCHAR(300) NOT NULL',
                'app_prop VARCHAR(600) DEFAULT NULL',
                'max_allowed_domains MEDIUMINT(9) DEFAULT NULL',
                'status VARCHAR(30) DEFAULT NULL',
                'start_date DATETIME DEFAULT NULL',
                'end_date DATETIME DEFAULT NULL',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX service_id_index (service_id)',
                'INDEX status_index (status)',
                'INDEX user_id_index (user_id)',
            ),

            /**
             * License meta table
             */
            SMLISER_LICENSE_META_TABLE     => array(
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
            SMLISER_PLUGINS_TABLE   => array(
                'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'name VARCHAR(255) NOT NULL',
                'slug VARCHAR(300) DEFAULT NULL',
                'status VARCHAR(300) DEFAULT \'active\'',
                'author VARCHAR(255) DEFAULT NULL',
                'author_profile VARCHAR(255) DEFAULT NULL',
                'download_link VARCHAR(400) DEFAULT NULL',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX plugin_download_link_index (download_link)',
                'INDEX plugin_slug_index (slug)',
                'INDEX plugin_author_index (author)',
                'INDEX plugin_status_index (status)',
            ),

            /**
             * The plugins meta table
             */
            SMLISER_PLUGINS_META_TABLE   => array(
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
            SMLISER_THEMES_TABLE    => array(
                'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'name VARCHAR(255) NOT NULL',
                'slug VARCHAR(300) DEFAULT NULL',
                'author VARCHAR(255) DEFAULT NULL',
                'status VARCHAR(55) DEFAULT \'active\'',
                'download_link VARCHAR(400) DEFAULT NULL', // Could be external URL.
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX theme_slug_index (slug)',
                'INDEX theme_author_index (author)',
                'INDEX theme_download_link_index (download_link)',
                'INDEX theme_status_index (status)',
            ),

            /**
             * Theme meta table
             */
            SMLISER_THEMES_META_TABLE   => array(
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
            SMLISER_SOFTWARE_TABLE     => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'name VARCHAR(255) NOT NULL',
                'slug VARCHAR(300) UNIQUE NOT NULL',
                'status VARCHAR(55) DEFAULT \'active\'',
                'author VARCHAR(255) DEFAULT NULL',
                'download_link VARCHAR(400) DEFAULT NULL',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX software_slug_index (slug)',
                'INDEX software_author_index (author)',
            ),

            /**
             * Meta table for other hosted application types.
             */
            SMLISER_SOFTWARE_META_TABLE    => array(
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
            SMLISER_ANALYTICS_LOGS_TABLE => array(
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
            SMLISER_ANALYTICS_DAILY_TABLE => array(
                'app_type VARCHAR(20) NOT NULL',
                'app_slug VARCHAR(100) NOT NULL',
                'stats_date DATE NOT NULL',
                'event_type VARCHAR(50) NOT NULL',
                'total_count INT(10) UNSIGNED DEFAULT 0',
                'unique_count INT(10) UNSIGNED DEFAULT 0',
                'PRIMARY KEY (app_type, app_slug, stats_date, event_type)',
                'INDEX date_lookup (stats_date)',
            ),
            
            /**
             * Download token table
             */
            SMLISER_APP_DOWNLOAD_TOKEN_TABLE   => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'app_prop VARCHAR(255) DEFAULT NULL',
                'license_key VARCHAR(255) DEFAULT NULL',
                'token VARCHAR(255) DEFAULT NULL',
                'expiry INT',
                'INDEX expiry_index(expiry)',
                'INDEX dtoken_index(token)',
            ),

            /**
             * Monetization table
             */
            SMLISER_MONETIZATION_TABLE     => array(
                'id BIGINT AUTO_INCREMENT PRIMARY KEY',
                'app_type VARCHAR(50) NOT NULL',
                'app_id BIGINT NOT NULL',
                'enabled TINYINT(1) DEFAULT 0',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'UNIQUE KEY unique_app_monetization (app_type, app_id)'
            ),

            /**
             * Pricing tier table
             */
            SMLISER_PRICING_TIER_TABLE     => array(
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
            ),

            /**
             * Bulk messages table
             */
            SMLISER_BULK_MESSAGES_TABLE    => array(
                'id BIGINT AUTO_INCREMENT PRIMARY KEY',
                'message_id VARCHAR(64) UNIQUE',
                'subject VARCHAR(255) NOT NULL',
                'body LONGTEXT DEFAULT NULL',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'is_read TINYINT(1) DEFAULT 0',
                'INDEX smliser_bulk_msg_created_at (created_at)',
                'INDEX smliser_bulk_msg_updated_at (updated_at)',
                'INDEX smliser_msg_id_lookup (message_id)',
            ),

            /**
             * Bulk messages to app map table.
             */
            SMLISER_BULK_MESSAGES_APPS_TABLE   => array(
                'id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
                'message_id VARCHAR(64) DEFAULT NULL',
                'app_type VARCHAR(64) NOT NULL',
                'app_slug VARCHAR(191) NOT NULL',
                'UNIQUE KEY smliser_unique_message_app (message_id, app_type, app_slug)',
                'INDEX smliser_msg_app_lookup (app_type, app_slug)'
            ),

            /**
             * The default settings table.
             */
            SMLISER_OPTIONS_TABLE      => array(
                'option_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'option_name VARCHAR(255) NOT NULL',
                'option_value TEXT DEFAULT NULL',
                'INDEX smliser_option_key (option_name)'
            ),

            /**
             * Resource owners table.
             */
            SMLISER_OWNERS_TABLE       => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'subject_id BIGINT(20) NOT NULL',
                'type ENUM(\'individual\', \'organization\', \'platform\') NOT NULL DEFAULT \'platform\'',
                'name VARCHAR(255) NOT NULL',
                'status ENUM(\'active\',\'suspended\',\'disabled\') NOT NULL DEFAULT \'active\'',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX smliser_owners_subject_id (subject_id)',
                'INDEX smliser_owners_created_at (created_at)',
                'INDEX smliser_owners_updated_at (updated_at)',
            ),

            /**
             * Human users table.
             */
            SMLISER_USERS_TABLE     => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'display_name VARCHAR(255) NOT NULL',
                'email VARCHAR(255) NOT NULL',
                'password_hash VARCHAR(300) NOT NULL',
                'status ENUM(\'active\',\'suspended\',\'disabled\') NOT NULL DEFAULT \'active\'',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX smliser_users_email (email)',
                'INDEX smliser_users_created_at (created_at)',
                'INDEX smliser_users_updated_at (updated_at)',
            ),

            /**
             * Non-human users table.
             */
            SMLISER_SERVICE_ACCOUNTS_TABLE  => array(
                'id INT AUTO_INCREMENT PRIMARY KEY',
                'identifier VARCHAR(255) NOT NULL',
                'owner_id INT NOT NULL',
                'display_name VARCHAR(255) NOT NULL',
                'description TEXT DEFAULT NULL',
                'api_key_hash VARCHAR(512) NOT NULL',
                'status ENUM(\'active\',\'suspended\',\'disabled\') NOT NULL DEFAULT \'active\'',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'last_used_at DATETIME NULL',
                'INDEX smliser_service_acct_owner_id (owner_id)',
                'INDEX smliser_service_acct_api_key_hash (api_key_hash)',
                'INDEX smliser_service_acct_status (status)',
                'INDEX smliser_service_acct_created_at (created_at)',
                'INDEX smliser_service_acct_updated_at (updated_at)',
            ),

            /**
             * Roles table where resource owners roles are stored.
             */
            SMLISER_ROLES_TABLE     => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'slug VARCHAR(64) NOT NULL',
                'label VARCHAR(190) NOT NULL',
                'is_canonical TINYINT(1) DEFAULT 0',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'UNIQUE KEY smliser_owner_role_unique (slug)',
                'INDEX smliser_roles_name (slug)',
            ),

            SMLISER_ROLE_CAPABILITIES_TABLE => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'role_id BIGINT UNSIGNED NOT NULL',
                'capabilities LONGTEXT DEFAULT NULL'
            ),

            /**
             * Role assignment table.
             */
            SMLISER_ROLE_ASSIGNMENT_TABLE   => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'role_id BIGINT(20) NOT NULL',
                'principal_type ENUM(\'individual\', \'service_account\', \'platform\') NOT NULL',
                'principal_id BIGINT(20) UNSIGNED NOT NULL',
                'owner_subject_type ENUM(\'platform\', \'individual\', \'organization\') NOT NULL',
                'owner_subject_id BIGINT UNSIGNED NOT NULL',
                'created_by BIGINT UNSIGNED DEFAULT NULL',
                'created_at DATETIME DEFAULT NULL'
            ),

            /**
             * Organizations table.
             */
            SMLISER_ORGANIZATIONS_TABLE => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'display_name VARCHAR(255) NOT NULL',
                'slug VARCHAR(255) NOT NULL',
                'status ENUM(\'active\',\'suspended\',\'disabled\') NOT NULL DEFAULT \'active\'',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX organization_name (display_name)',
                'INDEX organization_slug (slug)',
            ),

            /**
             * Organization members, roles mapping table.
             */
            SMLISER_ORGANIZATION_MEMBERS_TABLE      => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'organization_id BIGINT(20) UNSIGNED NOT NULL',
                'member_id BIGINT(20) UNSIGNED NOT NULL',
                'created_at DATETIME DEFAULT NULL',
                'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'INDEX organization_member_id (member_id)',
                'INDEX organization_id (organization_id)',
            ),

            /**
             * Identity federation lookup table.
             */
            SMLISER_IDENTITY_FEDERATION_TABLE       => array(
                'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'user_id BIGINT(20) UNSIGNED NOT NULL',
                'issuer VARCHAR(300) NOT NULL',
                'external_id VARCHAR(512) NOT NULL',
                'created_at DATETIME DEFAULT CURRENT_TIMESTAMP',
                'INDEX smliser_idfed_user_id (user_id)',
                'INDEX smliser_idfed_issuer (issuer)',
                'INDEX smliser_idfed_external_id (external_id)',

            )
        );
    }

    /**
     * Retrieve the schema definition for a single database table.
     *
     * @param string $table_name Fully-qualified table name constant.
     * @return string[]|null     Array of column definitions or null if not found.
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
    public static function table_names() : array {
        return array_keys( self::tables() );
    }
}