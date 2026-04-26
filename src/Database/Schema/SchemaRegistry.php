<?php
/**
 * Database Schema Registry
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

use SmartLicenseServer\Contracts\AbstractRegistry;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Pluggable database schema registry.
 *
 * Manages registration of database table schemas and provides methods
 * to retrieve schema definitions for installation and upgrades.
 *
 * Schemas are organized into core (built-in) and custom (plugin-provided)
 * categories, with core schemas taking precedence.
 *
 * @method class-string<DatabaseSchemaInterface>|null get( string $id )
 * @method array<string, class-string<DatabaseSchemaInterface>> all( bool $include_core = true, bool $include_custom = true )
 * @method bool has( string $id )
 * @method self remove( string $id )
 * @method self clear()
 * @since 0.2.0
 */
class SchemaRegistry extends AbstractRegistry {
    /**
     * Singleton instance.
     *
     * @var self $instance
     */
    private static $instance;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance() : self {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retrieve all database table schemas as column definition arrays.
     *
     * Each array key represents a fully-qualified table name,
     * while the value is an ordered list of SQL column and index
     * definitions suitable for use with dbDelta().
     *
     * @return array<string, string[]>
     */
    public function get_all_schemas() : array {
        $schemas = [];
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {
            $table_name = constant( $class_string::get_table_name() );
            $schemas[ $table_name ] = $class_string::get_columns();
        }

        return $schemas;
    }

    /**
     * Retrieve schema for a single table.
     *
     * @param string $table_name Fully-qualified table name constant name.
     * @return string[]|null    Array of column definitions or null if not found.
     */
    public function get_schema( string $table_name ) : ?array {
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {
            if ( $class_string::get_table_name() === $table_name ) {
                return $class_string::get_columns();
            }
        }

        return null;
    }

    /**
     * Retrieve a schema class by its ID.
     *
     * @param string $schema_id
     * @return class-string<DatabaseSchemaInterface>|null
     */
    public function get_schema_class( string $schema_id ) : ?string {
        return $this->get( $schema_id );
    }

    /**
     * Get all registered schemas with metadata.
     *
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     table_name: string,
     *     columns: string[],
     *     class: class-string<DatabaseSchemaInterface>
     * }>
     */
    public function get_all_with_metadata() : array {
        $schemas = [];
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {
            $id = $class_string::get_id();
            $schemas[ $id ] = [
                'id'          => $id,
                'label'       => $class_string::get_label(),
                'description' => $class_string::get_description(),
                'table_name'  => $class_string::get_table_name(),
                'columns'     => $class_string::get_columns(),
                'class'       => $class_string,
            ];
        }

        return $schemas;
    }

    /**
     * Return all table names.
     *
     * @return string[]
     */
    public function table_names() : array {
        $names = [];
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {
            $names[] = $class_string::get_table_name();
        }

        return $names;
    }

    /**
     * Load core database schemas.
     *
     * @return void
     */
    protected function load_core() : void {
        $core_schemas = [
            LicenseSchema::class,
            LicenseMetaSchema::class,
            PluginSchema::class,
            PluginMetaSchema::class,
            ThemeSchema::class,
            ThemeMetaSchema::class,
            SoftwareSchema::class,
            SoftwareMetaSchema::class,
            AnalyticsLogsSchema::class,
            AnalyticsDailySchema::class,
            AppDownloadTokenSchema::class,
            MonetizationSchema::class,
            PricingTierSchema::class,
            BulkMessagesSchema::class,
            BulkMessagesAppsSchema::class,
            OptionsSchema::class,
            OwnersSchema::class,
            UsersSchema::class,
            UserOptionsSchema::class,
            ServiceAccountsSchema::class,
            RolesSchema::class,
            RoleCapabilitiesSchema::class,
            RoleAssignmentSchema::class,
            OrganizationsSchema::class,
            OrganizationMembersSchema::class,
            IdentityFederationSchema::class,
            BackgroundJobsSchema::class,
            FailedJobsSchema::class,
        ];

        foreach ( $core_schemas as $schema_class ) {
            $id = $schema_class::get_id();
            $this->core[ $id ] = $schema_class;
        }

        $this->core_loaded = true;
    }

    /**
     * Override assert_implements_interface to check for DatabaseSchemaInterface.
     * 
     * @throws InvalidArgumentException
     */
    protected function assert_implements_interface( string $class_string ) : void {
        if ( ! class_exists( $class_string ) ) {
            throw new InvalidArgumentException(
                sprintf( '%s: class "%s" does not exist.', static::class, $class_string )
            );
        }

        if ( ! in_array( DatabaseSchemaInterface::class, class_implements( $class_string ) ?: [], true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'SchemaRegistry: "%s" must implement %s.',
                    $class_string,
                    DatabaseSchemaInterface::class
                )
            );
        }
    }
}