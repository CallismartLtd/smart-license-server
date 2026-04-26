<?php
/**
 * Owners Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores owners.
 */
class OwnersSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_OWNERS_TABLE';
    }

    public static function get_columns() : array {
        return [
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
        ];
    }

    public static function get_label() : string { return 'Owners'; }
    public static function get_description() : string { return 'Stores resource owners.'; }
}