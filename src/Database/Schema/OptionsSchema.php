<?php
/**
 * Options Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores global options.
 */
class OptionsSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_OPTIONS_TABLE';
    }

    public static function get_columns() : array {
        return [
            'option_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'option_name VARCHAR(255) NOT NULL',
            'option_value LONGTEXT DEFAULT NULL',
            'INDEX smliser_option_key (option_name)'
        ];
    }

    public static function get_label() : string { return 'Options'; }
    public static function get_description() : string { return 'Stores global configuration.'; }
}