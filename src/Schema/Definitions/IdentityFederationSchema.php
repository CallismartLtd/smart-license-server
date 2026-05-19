<?php
/**
 * Identity Federation Schema definition file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema\Definitions
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema\Definitions;

use SmartLicenseServer\Schema\DatabaseSchemaInterface;
use SmartLicenseServer\Database\Utils\Column;
use SmartLicenseServer\Database\Utils\Constraint;
use SmartLicenseServer\Database\Utils\ColumnType;
use SmartLicenseServer\Database\Utils\DefaultColumnValue;

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
                ->default( DefaultColumnValue::expression( 'CURRENT_TIMESTAMP' ) ),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function get_constraints() : array {
        $prefx  = static::constraintPrefix();
        return [
            Constraint::primary( "{$prefx}primary" )->on( 'id' ),
            Constraint::index( "{$prefx}user_id" )->on( 'user_id' ),
            Constraint::index( "{$prefx}issuer" )->on( 'issuer' ),
            Constraint::index( "{$prefx}external_id" )->on( 'external_id' ),
        ];
    }

    protected static function constraintPrefix() {
        return 'smliser_idfederation_';
    }
}