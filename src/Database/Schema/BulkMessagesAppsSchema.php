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
        return SMLISER_BULK_MESSAGES_APPS_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name'            => 'id',
                'type'            => 'bigint',
                'unsigned'        => true,
                'nullable'        => false,
                'auto_increment'  => true,
            ],
            [
                'name'      => 'message_id',
                'type'      => 'varchar',
                'length'    => 64,
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'app_type',
                'type'      => 'varchar',
                'length'    => 64,
                'nullable'  => false,
            ],
            [
                'name'      => 'app_slug',
                'type'      => 'varchar',
                'length'    => 191,
                'nullable'  => false,
            ],
        ];
    }

    public static function get_constraints() : array {
        return [
            [
                'type'    => 'primary',
                'columns' => [ 'id' ],
            ],
            [
                'type'    => 'unique',
                'name'    => 'smliser_unique_message_app',
                'columns' => [ 'message_id', 'app_type', 'app_slug' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'smliser_msg_app_lookup',
                'columns' => [ 'app_type', 'app_slug' ],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Bulk Messages Apps';
    }

    public static function get_description() : string {
        return 'Maps messages to applications.';
    }
}