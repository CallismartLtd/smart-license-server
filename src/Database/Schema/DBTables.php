<?php
/**
 * Legacy Database Table Definition Helper
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 *
 * @deprecated Use SchemaRegistry instead for new code.
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Legacy static helper for backward compatibility.
 *
 * This class provides backward compatibility with code that uses the
 * original DBTables::tables() static interface. New code should use
 * SchemaRegistry instead.
 *
 * @since 0.2.0
 * @deprecated Use SchemaRegistry instead.
 */
final class DBTables {
    /**
     * Retrieve all database table schemas (legacy interface).
     *
     * Each array key represents a fully-qualified table name,
     * while the value is an ordered list of SQL column and index
     * definitions suitable for use with dbDelta().
     *
     * @return array<string, string[]>
     *
     * @deprecated Use SchemaRegistry::instance()->get_all_schemas() instead.
     */
    public static function tables() : array {
        return SchemaRegistry::instance()->get_all_schemas();
    }

    /**
     * Retrieve the schema definition for a single database table (legacy interface).
     *
     * @param string $table_name Fully-qualified table name constant.
     * @return string[]|null     Array of column definitions or null if not found.
     *
     * @deprecated Use SchemaRegistry::instance()->get_schema() instead.
     */
    public static function get( string $table_name ) : ?array {
        return SchemaRegistry::instance()->get_schema( $table_name );
    }

    /**
     * Return all table names (legacy interface).
     *
     * @return string[]
     *
     * @deprecated Use SchemaRegistry::instance()->table_names() instead.
     */
    public static function table_names() : array {
        return SchemaRegistry::instance()->table_names();
    }
}