<?php
/**
 * User Options Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores user options.
 */
class UserOptionsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_USER_OPTIONS_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'user_id BIGINT(20) UNSIGNED NOT NULL',
            'option_key VARCHAR(255) NOT NULL',
            'option_value LONGTEXT DEFAULT NULL',
            'UNIQUE KEY smliser_user_options_unique (user_id, option_key)',
            'INDEX option_key_index (option_key)',
        ];
    }

    public static function get_label() : string { return 'User Options'; }
    public static function get_description() : string { return 'Stores user preferences.'; }
}