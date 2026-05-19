<?php
/**
 * Role Capabilities Table Schema definition file.
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Database\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Schema\Column;
use SmartLicenseServer\Database\Schema\Constraint;
use SmartLicenseServer\Database\Schema\Helpers\ColumnType;

/**
 * Stores role capabilities.
 */
class RoleCapabilitiesSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Role Capabilities';
    }

    public static function get_description() : string {
        return 'Stores role capabilities.';
    }

    public static function get_table_name() : string {
        return SMLISER_ROLE_CAPABILITIES_TABLE;
    }

    public static function get_columns() : array {
        return [
            Column::make( 'id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->auto_increment()
                ->required(),

            Column::make( 'role_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

            Column::make( 'capabilities' )
                ->type( ColumnType::LONG_TEXT ),
        ];
    }

    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_capsschema_';
    }
}