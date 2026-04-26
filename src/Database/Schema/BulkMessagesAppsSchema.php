<?php
/**
 * Bulk Messages Apps Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Maps messages to apps.
 */
class BulkMessagesAppsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_BULK_MESSAGES_APPS_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'message_id VARCHAR(64) DEFAULT NULL',
            'app_type VARCHAR(64) NOT NULL',
            'app_slug VARCHAR(191) NOT NULL',
            'UNIQUE KEY smliser_unique_message_app (message_id, app_type, app_slug)',
            'INDEX smliser_msg_app_lookup (app_type, app_slug)'
        ];
    }

    public static function get_label() : string { return 'Bulk Messages Apps'; }
    public static function get_description() : string { return 'Maps messages to applications.'; }
}