<?php
/**
 * Bulk Messages Apps Schema definition file.
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

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Maps bulk messages to specific applications.
 * 
 * @since 0.2.0
 */
class BulkMessagesAppsSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Bulk Messages Apps';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Maps messages to applications.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_BULK_MESSAGES_APPS_TABLE;
    }

    /**
     * @inheritDoc
     */
    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( 'bigint' )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'message_id' )
                ->type( 'varchar' )
                ->size( 64 )
                ->default( null ),

            Column::make( 'app_type' )
                ->type( 'varchar' )
                ->size( 64 )
                ->required(),

            Column::make( 'app_slug' )
                ->type( 'varchar' )
                ->size( 191 )
                ->required(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )
                ->on( 'id' ),

            Constraint::make( 'unique' )
                ->name( 'smliser_unique_message_app' )
                ->on( 'message_id', 'app_type', 'app_slug' ),

            Constraint::make( 'index' )
                ->name( 'smliser_msg_app_lookup' )
                ->on( 'app_type', 'app_slug' ),
        ];
    }
}