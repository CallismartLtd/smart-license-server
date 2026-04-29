<?php
/**
 * License Meta Table Schema definition file.
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
 * Stores metadata for licenses.
 * 
 * @since 0.2.0
 */
class LicenseMetaSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'License Meta';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores arbitrary key-value metadata for licenses.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_LICENSE_META_TABLE;
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

            Column::make( 'license_id' )
                ->type( 'bigint' )
                ->unsigned()
                ->required(),

            Column::make( 'meta_key' )
                ->type( 'varchar' )
                ->size( 255 )
                ->required(),

            Column::make( 'meta_value' )
                ->type( 'longtext' )
                ->default( null ),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'index' )->name( 'license_id_index' )->on( 'license_id' ),
            Constraint::make( 'index' )->name( 'meta_key_index' )->on( 'meta_key' ),
        ];
    }
}