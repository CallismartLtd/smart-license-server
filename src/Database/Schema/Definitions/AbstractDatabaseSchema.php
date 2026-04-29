<?php
/**
 * Abstract Database Schema Class
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Abstract base class for database schemas.
 *
 * Provides default implementations of interface methods to reduce
 * boilerplate in concrete schema classes.
 *
 * @since 0.2.0
 */
abstract class AbstractDatabaseSchema implements DatabaseSchemaInterface {
    /**
     * {@inheritDoc}.
     *
     * Default implementation derives ID from class name.
     * The database table name without the prefix is used as the ID.
     * Override for custom behavior.
     */
    public static function get_id() : string {
        return substr( static::get_table_name(), strlen( smliser_db_prefix() ) );
    }

    /**
     * {@inheritDoc}.
     *
     * Default implementation derives label from class name.
     * Override for custom behavior.
     */
    public static function get_label() : string {
        $class_name = static::class;
        $short_name = substr( strrchr( $class_name, '\\' ), 1 );
        // Remove 'Schema' suffix and convert to title case
        $label = str_replace( 'Schema', '', $short_name );
        return ucwords( preg_replace( '/([a-z])([A-Z])/', '$1 $2', $label ) );
    }

    /**
     * {@inheritDoc}.
     *
     * Default implementation returns empty string.
     * Override for custom behavior.
     */
    public static function get_description() : string {
        return '';
    }
}