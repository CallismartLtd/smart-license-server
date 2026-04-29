<?php
/**
 * Database Schema Interface
 *
 * Defines portable, engine-agnostic schema metadata for tables.
 *
 * Schema classes describe structure intent only.
 * SQL generation is delegated to schema/query renderers.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Contract for all database schema definitions.
 *
 * Implementations should return normalized metadata objects that are:
 * - self-documenting
 * - renderer-friendly
 * - cross-engine portable
 * - migration safe
 *
 * @since 0.2.0
 */
interface DatabaseSchemaInterface {
    /**
     * Human readable schema name.
     *
     * Example:
     * - Users Table
     * - Licenses Table
     *
     * @return string
     */
    public static function get_label() : string;

    /**
     * Human readable schema purpose.
     *
     * @return string
     */
    public static function get_description() : string;

    /**
     * Fully resolved table name.
     *
     * Example:
     * - wp_smliser_users
     * - smliser_users
     *
     * @return string
     */
    public static function get_table_name() : string;

    /**
     * Portable ordered column definitions.
     *
     * @return Column[]
     */
    public static function get_columns() : array;

    /**
     * Table constraints and indexes.
     *
     * @return Constraint[]
     */
    public static function get_constraints() : array;
}