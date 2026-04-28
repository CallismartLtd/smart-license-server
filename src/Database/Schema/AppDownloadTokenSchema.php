<?php
/**
 * App Download Token Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores download tokens.
 */
class AppDownloadTokenSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_APP_DOWNLOAD_TOKEN_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name'            => 'id',
                'type'            => 'bigint',
                'unsigned'        => true,
                'nullable'        => false,
                'auto_increment'  => true,
            ],
            [
                'name'      => 'app_prop',
                'type'      => 'varchar',
                'length'    => 255,
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'license_key',
                'type'      => 'varchar',
                'length'    => 255,
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'token',
                'type'      => 'varchar',
                'length'    => 255,
                'nullable'  => true,
                'default'   => null,
            ],
            [
                'name'      => 'expiry',
                'type'      => 'int',
                'nullable'  => false,
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
                'name'    => 'expiry_index',
                'columns' => [ 'expiry' ],
            ],
            [
                'type'    => 'index',
                'name'    => 'dtoken_index',
                'columns' => [ 'token' ],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'App Download Tokens';
    }

    public static function get_description() : string {
        return 'Stores download tokens.';
    }
}