<?php
/**
 * Software Meta Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;

defined( 'SMLISER_ABSPATH' ) || exit;

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
                ->type( 'bigint' )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'software_id' )
                ->type( 'bigint' )
                ->unsigned()
                ->required(),

            Column::make( 'meta_key' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'meta_value' )
                ->type( 'longtext' ),
        ];
    }

    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'index' )->name( 'app_id_index' )->on( 'software_id' ),
            Constraint::make( 'index' )->name( 'meta_key_index' )->on( 'meta_key' ),
        ];
    }
}