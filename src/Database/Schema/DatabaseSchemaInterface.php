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
 * Implementations should return normalized metadata arrays that are:
 * - self-documenting
 * - renderer-friendly
 * - cross-engine portable
 * - migration safe
 *
 * @since 0.2.0
 */
interface DatabaseSchemaInterface {

    /**
     * Unique schema registry identifier.
     *
     * Example:
     * - users
     * - licenses
     * - service_accounts
     *
     * @return string
     */
    public static function get_id() : string;

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
     * Keys:
     * - name            string   Required column name
     * - type            string   Required logical type
     * - length          int|null Optional scalar length
     * - precision       int|null Optional numeric precision
     * - scale           int|null Optional decimal scale
     * - unsigned        bool     Optional
     * - nullable        bool     Optional
     * - auto_increment  bool     Optional
     * - default         mixed    Optional
     * - comment         string   Optional
     *
     * Example:
     * [
     *   [
     *     'name' => 'id',
     *     'type' => 'bigint',
     *     'unsigned' => true,
     *     'auto_increment' => true,
     *     'nullable' => false,
     *   ]
     * ]
     *
     * @return array<int, array{
     *     name: string,
     *     type: string,
     *     length?: int|null,
     *     precision?: int|null,
     *     scale?: int|null,
     *     unsigned?: bool,
     *     nullable?: bool,
     *     auto_increment?: bool,
     *     default?: mixed,
     *     comment?: string
     * }>
     */
    public static function get_columns() : array;

    /**
     * Table constraints and indexes.
     *
     * Supported types:
     * - primary
     * - unique
     * - index
     * - foreign
     * - fulltext
     *
     * Common keys:
     * - type       string        Required
     * - name       string        Optional constraint/index name
     * - columns    string[]      Required for most types
     *
     * Foreign key keys:
     * - references_table   string
     * - references_columns string[]
     * - on_delete          string
     * - on_update          string
     *
     * @return array<int, array{
     *     type: string,
     *     name?: string,
     *     columns?: array<int, string>,
     *     references_table?: string,
     *     references_columns?: array<int, string>,
     *     on_delete?: string,
     *     on_update?: string
     * }>
     */
    public static function get_constraints() : array;

    /**
     * Engine/runtime options.
     *
     * Typical keys:
     * - engine
     * - charset
     * - collation
     * - row_format
     * - temporary
     *
     * Renderers may ignore unsupported options.
     *
     * @return array{
     *     engine?: string,
     *     charset?: string,
     *     collation?: string,
     *     row_format?: string,
     *     temporary?: bool
     * }
     */
    public static function get_options() : array;
}