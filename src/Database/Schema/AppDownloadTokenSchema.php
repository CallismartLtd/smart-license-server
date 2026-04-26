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
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'app_prop VARCHAR(255) DEFAULT NULL',
            'license_key VARCHAR(255) DEFAULT NULL',
            'token VARCHAR(255) DEFAULT NULL',
            'expiry INT',
            'INDEX expiry_index(expiry)',
            'INDEX dtoken_index(token)',
        ];
    }

    public static function get_label() : string { return 'App Download Tokens'; }
    public static function get_description() : string { return 'Stores download tokens.'; }
}