<?php
/**
 * Software Meta Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Software Meta Schema
 */
class SoftwareMetaSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Software Meta';
    }

    public static function get_description() : string {
        return 'Stores software metadata.';
    }

    public static function get_table_name() : string {
        return SMLISER_SOFTWARE_META_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'software_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

            Column::make( 'meta_key' )
                ->type( ColumnType::VARCHAR )
                ->size( 255 )
                ->required(),

            Column::make( 'meta_value' )
                ->type( ColumnType::LONG_TEXT ),
        ];
    }

    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}app_id_index" )->on( 'software_id' ),
            Constraint::index( "{$prefx}meta_key_index" )->on( 'meta_key' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_softwareshema_';
    }
}