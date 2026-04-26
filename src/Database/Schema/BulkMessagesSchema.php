<?php
/**
 * Bulk Messages Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores bulk messages.
 */
class BulkMessagesSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_BULK_MESSAGES_TABLE';
    }

    public static function get_columns() : array {
        return [
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
        ];
    }

    public static function get_label() : string { return 'Bulk Messages'; }
    public static function get_description() : string { return 'Stores bulk messages.'; }
}