<?php
/**
 * License Table Schema definition file.
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
 * Stores core license records.
 * 
 * @since 0.2.0
 */
class LicenseSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Licenses';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores license records with keys, service identifiers, and validity dates.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_LICENSE_TABLE;
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

            Column::make( 'licensee_fullname' )
                ->type( ColumnType::STRING )
                ->size( 512 ),

            Column::make( 'license_key' )
                ->type( ColumnType::STRING )
                ->size( 300 )
                ->required(),

            Column::make( 'service_id' )
                ->type( ColumnType::STRING )
                ->size( 300 )
                ->required(),

            Column::make( 'app_prop' )
                ->type( ColumnType::STRING )
                ->size( 600 ),

            Column::make( 'max_allowed_domains' )
                ->type( ColumnType::BIG_INT )
                ->size( 20 ),

            Column::make( 'status' )
                ->type( ColumnType::STRING )
                ->size( 30 ),

            Column::make( 'start_date' )
                ->type( ColumnType::DATETIME ),

            Column::make( 'end_date' )
                ->type( ColumnType::DATETIME ),

            Column::make( 'created_at' )
                ->type( ColumnType::DATETIME ),

            Column::make( 'updated_at' )
                ->type( ColumnType::DATETIME )
                ->required(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'unique' )->on( 'license_key' ),
            Constraint::make( 'index' )->name( 'service_id_index' )->on( 'service_id' ),
            Constraint::make( 'index' )->name( 'status_index' )->on( 'status' ),
        ];
    }
}