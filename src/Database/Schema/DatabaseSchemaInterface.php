<?php
/**
 * Database Schema Interface
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Interface that all database table schema classes must implement.
 *
 * Each schema class represents a single database table with its column
 * definitions and indexes suitable for use with dbDelta().
 *
 * @since 0.2.0
 */
interface DatabaseSchemaInterface {
    /**
     * Get the fully-qualified table name constant.
     *
     * This should be a table name constant defined elsewhere in the application,
     * e.g., SMLISER_LICENSE_TABLE.
     *
     * @return string The table name constant name (not the value).
     */
    public static function get_table_name() : string;

    /**
     * Get the column and index definitions for this table.
     *
     * Returns an ordered array of SQL column and index definitions
     * suitable for use with WordPress dbDelta() or equivalent.
     *
     * @return string[] Array of SQL column/index definitions.
     */
    public static function get_columns() : array;

    /**
     * Get a unique identifier for this schema.
     *
     * Used for registering and looking up schemas in the registry.
     *
     * @return string Unique schema identifier.
     */
    public static function get_id() : string;

    /**
     * Get a human-readable name for this schema.
     *
     * @return string
     */
    public static function get_label() : string;

    /**
     * Get a description of what this table stores.
     *
     * @return string
     */
    public static function get_description() : string;
}