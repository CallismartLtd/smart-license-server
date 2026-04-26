<?php
/**
 * Users Table Schema
 *
 * Defines the structure for user account storage.
 *
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Schema definition for users table.
 *
 * Stores authentication credentials and user account details.
 */
class UsersSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_USERS_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'display_name VARCHAR(255) NOT NULL',
            'email VARCHAR(255) NOT NULL',
            'password_hash VARCHAR(300) NOT NULL',
            'status ENUM(\'active\',\'suspended\',\'disabled\') NOT NULL DEFAULT \'active\'',
            'created_at DATETIME DEFAULT NULL',
            'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'UNIQUE KEY smliser_users_email_unique (email)',
            'INDEX smliser_users_created_at (created_at)',
            'INDEX smliser_users_updated_at (updated_at)',
        ];
    }

    public static function get_label() : string {
        return 'Users';
    }

    public static function get_description() : string {
        return 'Stores human user accounts with credentials.';
    }
}