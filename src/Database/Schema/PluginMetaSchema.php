<?php
/**
 * Plugin Meta Table Schema
 *
 * Defines the structure for storing plugin metadata.
 *
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Schema definition for the plugin meta table.
 *
 * Stores arbitrary key-value metadata associated with plugins.
 */
class PluginMetaSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_PLUGINS_META_TABLE;
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'plugin_id BIGINT(20) UNSIGNED NOT NULL',
            'meta_key VARCHAR(255) NOT NULL',
            'meta_value LONGTEXT DEFAULT NULL',
            'INDEX plugin_id_index (plugin_id)',
            'INDEX meta_key_index (meta_key)',
        ];
    }

    public static function get_label() : string {
        return 'Plugin Meta';
    }

    public static function get_description() : string {
        return 'Stores arbitrary key-value metadata for plugins.';
    }
}