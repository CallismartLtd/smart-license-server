<?php
/**
 * Service Accounts Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores service accounts.
 */
class ServiceAccountsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_SERVICE_ACCOUNTS_TABLE';
    }

    public static function get_columns() : array {
        return [
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
        ];
    }

    public static function get_label() : string { return 'Service Accounts'; }
    public static function get_description() : string { return 'Stores service accounts.'; }
}