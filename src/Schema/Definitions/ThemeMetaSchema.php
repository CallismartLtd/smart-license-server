<?php
/**
 * Theme Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

/**
 * Theme Meta Schema
 */
class ThemeMetaSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Theme Meta';
    }

    public static function get_description() : string {
        return 'Stores theme metadata.';
    }

    public static function get_table_name() : string {
        return SMLISER_THEMES_META_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'theme_id' )
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
            Constraint::index( "{$prefx}theme_id_index" )->on( 'theme_id' ),
            Constraint::index( "{$prefx}meta_key_index" )->on( 'meta_key' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_thememeta_';
    }
}