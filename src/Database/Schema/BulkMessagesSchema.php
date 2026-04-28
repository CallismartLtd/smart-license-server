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
        return SMLISER_BULK_MESSAGES_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name'            => 'id',
                'type'            => 'bigint',
                'auto_increment'  => true,
                'nullable'        => false,
            ],
            [
                'name'      => 'message_id',
                'type'      => 'varchar',
                'length'    => 64,
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'subject',
                'type'      => 'varchar',
                'length'    => 255,
                'nullable'  => false,
            ],
            [
                'name'      => 'body',
                'type'      => 'longtext',
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'created_at',
                'type'      => 'datetime',
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'updated_at',
                'type'      => 'datetime',
                'nullable'  => false,
                'default'   => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
            [
                'name'      => 'is_read',
                'type'      => 'tinyint',
                'length'    => 1,
                'nullable'  => true,
                'default'   => 0,
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
                'columns' => [ 'message_id' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'smliser_bulk_msg_created_at',
                'columns' => [ 'created_at' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'smliser_bulk_msg_updated_at',
                'columns' => [ 'updated_at' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'smliser_msg_id_lookup',
                'columns' => [ 'message_id' ],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Bulk Messages';
    }

    public static function get_description() : string {
        return 'Stores bulk messages.';
    }
}