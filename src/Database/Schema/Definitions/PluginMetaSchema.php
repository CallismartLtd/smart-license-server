<?php
/**
 * Plugin Meta Table Schema definition file.
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
 * Stores arbitrary key-value metadata for plugins.
 * 
 * @since 0.2.0
 */
class PluginMetaSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Plugin Meta';
    }

    public static function get_description() : string {
        return 'Stores arbitrary key-value metadata for plugins.';
    }

    public static function get_table_name() : string {
        return SMLISER_PLUGINS_META_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'plugin_id' )
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
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'index' )->name( 'plugin_id_index' )->on( 'plugin_id' ),
            Constraint::make( 'index' )->name( 'meta_key_index' )->on( 'meta_key' ),
        ];
    }
}