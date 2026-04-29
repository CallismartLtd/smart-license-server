<?php
/**
 * Identity Federation Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores federated identities.
 */
class IdentityFederationSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_IDENTITY_FEDERATION_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name'            => 'id',
                'type'            => 'bigint',
                'unsigned'        => true,
                'auto_increment'  => true,
                'nullable'        => false,
            ],
            [
                'name'      => 'user_id',
                'type'      => 'bigint',
                'unsigned'  => true,
                'nullable'  => false,
            ],
            [
                'name'      => 'issuer',
                'type'      => 'varchar',
                'length'    => 300,
                'nullable'  => false,
            ],
            [
                'name'      => 'external_id',
                'type'      => 'varchar',
                'length'    => 512,
                'nullable'  => false,
            ],
            [
                'name'      => 'created_at',
                'type'      => 'datetime',
                'nullable'  => true,
                'default'   => 'CURRENT_TIMESTAMP',
            ],
        ];
    }

    public static function get_constraints() : array {
        return [
            [
                'type'    => 'primary',
                'columns' => [ 'id' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'smliser_idfed_user_id',
                'columns' => [ 'user_id' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'smliser_idfed_issuer',
                'columns' => [ 'issuer' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'smliser_idfed_external_id',
                'columns' => [ 'external_id' ],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Identity Federation';
    }

    public static function get_description() : string {
        return 'Stores federated identity mappings.';
    }
}