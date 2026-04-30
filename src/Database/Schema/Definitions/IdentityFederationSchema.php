<?php
/**
 * Identity Federation Schema definition file.
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
 * Stores federated identities (OAuth/SAML mappings).
 * 
 * @since 0.2.0
 */
class IdentityFederationSchema implements DatabaseSchemaInterface {

    /**
     * @inheritDoc
     */
    public static function get_label() : string {
        return 'Identity Federation';
    }

    /**
     * @inheritDoc
     */
    public static function get_description() : string {
        return 'Stores federated identity mappings.';
    }

    /**
     * @inheritDoc
     */
    public static function get_table_name() : string {
        return SMLISER_IDENTITY_FEDERATION_TABLE;
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

            Column::make( 'user_id' )
                ->type( ColumnType::BIG_INT )
                ->unsigned()
                ->required(),

            Column::make( 'issuer' )
                ->type( ColumnType::VARCHAR )
                ->size( 300 )
                ->required(),

            Column::make( 'external_id' )
                ->type( ColumnType::VARCHAR )
                ->size( 512 )
                ->required(),

            Column::make( 'created_at' )
                ->type( ColumnType::DATETIME )
                ->default( 'CURRENT_TIMESTAMP' ),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        return [
            Constraint::make( 'primary' )->on( 'id' ),
            Constraint::make( 'index' )->name( 'smliser_idfed_user_id' )->on( 'user_id' ),
            Constraint::make( 'index' )->name( 'smliser_idfed_issuer' )->on( 'issuer' ),
            Constraint::make( 'index' )->name( 'smliser_idfed_external_id' )->on( 'external_id' ),
        ];
    }
}