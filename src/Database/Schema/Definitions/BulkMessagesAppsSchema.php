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
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

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
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'message_id' )
                ->type( ColumnType::VARCHAR )
                ->size( 64 )
                ->default( null ),

            Column::make( 'app_type' )
                ->type( ColumnType::VARCHAR )
                ->size( 64 )
                ->required(),

            Column::make( 'app_slug' )
                ->type( ColumnType::VARCHAR )
                ->size( 191 )
                ->required(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),

            Constraint::unique( "{$prefx}unique_app" )
                ->on( 'message_id', 'app_type', 'app_slug' ),

            Constraint::index( "{$prefx}app_lookup" )
                ->on( 'app_type', 'app_slug' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_bulkmsg_apps_schema_';
    }
}