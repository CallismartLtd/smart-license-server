<?php
/**
 * Bulk Messages Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores bulk messages.
 * 
 * @since 0.2.0
 */
class BulkMessagesSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Bulk Messages';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores bulk messages.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_BULK_MESSAGES_TABLE;
    }

    /**
     * @inheritDoc
     */
    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->auto_increment()
                ->required(),

            Column::make( 'message_id' )
                ->type( ColumnType::VARCHAR )
                ->size( 64 )
                ->default( null ),

            Column::make( 'subject' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'body' )
                ->type( ColumnType::LONG_TEXT )
                ->default( null ),

            Column::make( 'created_at' )
                ->type( ColumnType::DATETIME )
                ->default( null ),

            Column::make( 'updated_at' )
                ->type( ColumnType::DATETIME )
                ->required()
                ->default( 'CURRENT_TIMESTAMP' )
                ->comment( 'on_update CURRENT_TIMESTAMP' ),

            Column::make( 'is_read' )
                ->type( ColumnType::INTEGER )
                ->size( 1 )
                ->default( 0 ),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'unique' )->on( 'message_id' ),
            Constraint::make( 'index' )->name( 'smliser_bulk_msg_created_at' )->on( 'created_at' ),
            Constraint::make( 'index' )->name( 'smliser_bulk_msg_updated_at' )->on( 'updated_at' ),
            Constraint::make( 'index' )->name( 'smliser_msg_id_lookup' )->on( 'message_id' ),
        ];
    }
}