<?php
/**
 * Role Assignment Table Schema definition file.
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
        return [
            Constraint::make( 'primary' )->on( 'id' ),
        ];
    }
}