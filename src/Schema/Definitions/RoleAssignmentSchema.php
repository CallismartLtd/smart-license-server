<?php
/**
 * Role Assignment Table Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package Callismart\DBPrism\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use Callismart\DBPrism\Utils\Column;
use Callismart\DBPrism\Utils\Constraint;
use Callismart\DBPrism\Utils\ColumnType;

/**
 * Maps roles to principals (users, service accounts, etc.).
 * 
 * @since 0.2.0
 */
class RoleAssignmentSchema implements DatabaseSchemaInterface {

    public static function get_label() : string {
        return 'Role Assignments';
    }

    public static function get_description() : string {
        return 'Maps roles to principals.';
    }

    public static function get_table_name() : string {
        return SMLISER_ROLE_ASSIGNMENT_TABLE;
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
                ->required(),

            Column::make( 'principal_type' )
                ->type( ColumnType::VARCHAR )
                ->size( 50 )
                ->required(),

            Column::make( 'principal_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

            Column::make( 'owner_subject_type' )
                ->type( ColumnType::VARCHAR )
                ->size( 50 )
                ->required(),

            Column::make( 'owner_subject_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

            Column::make( 'created_by' )
                ->type( ColumnType::BIG_INT )
                ->unsigned(),

            Column::make( 'created_at' )
                ->type( ColumnType::DATETIME ),
        ];
    }

    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_roleassign_';
    }
}