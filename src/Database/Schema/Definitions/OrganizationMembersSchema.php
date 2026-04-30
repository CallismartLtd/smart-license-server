<?php
/**
 * Organization Members Schema definition file.
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
 * Maps members to organizations.
 * 
 * @since 0.2.0
 */
class OrganizationMembersSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Organization Members';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Maps members to organizations.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_ORGANIZATION_MEMBERS_TABLE;
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

            Column::make( 'organization_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

            Column::make( 'member_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

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
            Constraint::make( 'index' )->name( 'organization_member_id' )->on( 'member_id' ),
            Constraint::make( 'index' )->name( 'organization_id' )->on( 'organization_id' ),
        ];
    }
}