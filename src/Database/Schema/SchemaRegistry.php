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
 * Database Schema Registry
 *
 * Pluggable schema registry for all database table definitions.
 *
 * @method class-string<DatabaseSchemaInterface>|null get(string $table_name)
 * @method bool has(string $table_name)
 * @method self add(class-string<DatabaseSchemaInterface> $class_string)
 * @method bool remove(string $table_name)
 * @method array<int, class-string<DatabaseSchemaInterface>|DatabaseSchemaInterface> all(bool $assoc = true, bool $objects = false)
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */
class SchemaRegistry extends AbstractRegistry {

    /**
     * Singleton instance.
     *
     * @var self|null
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
     * Retrieve all database schemas as structured definitions.
     *
     * Each array key is the schema ID, and the value is a fully
     * resolved schema descriptor containing portable metadata.
     *
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     table_name: string,
     *     columns: array<int, array{
     *         name: string,
     *         type: string,
     *         length?: int|null,
     *         precision?: int|null,
     *         scale?: int|null,
     *         unsigned?: bool,
     *         nullable?: bool,
     *         auto_increment?: bool,
     *         default?: mixed,
     *         comment?: string
     *     }>,
     *     constraints: array<int, array{
     *         type: string,
     *         name?: string,
     *         columns?: array<int, string>,
     *         references_table?: string,
     *         references_columns?: array<int, string>,
     *         on_delete?: string,
     *         on_update?: string
     *     }>,
     *     options: array{
     *         engine?: string,
     *         charset?: string,
     *         collation?: string,
     *         row_format?: string,
     *         temporary?: bool
     *     }
     * }>
     */
    public function get_all_schemas() : array {
        $schemas   = [];
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {

            $id = $class_string::get_id();

            $schemas[ $id ] = [
                'id'          => $id,
                'label'       => $class_string::get_label(),
                'description' => $class_string::get_description(),
                'table_name'  => $class_string::get_table_name(),

                'columns'     => $class_string::get_columns(),
                'constraints' => $class_string::get_constraints(),
                'options'     => $class_string::get_options(),
            ];
        }

        return $schemas;
    }

    /**
     * Get all registered schemas as structured definitions.
     *
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     table_name: string,
     *     columns: array<int, array{
     *         name: string,
     *         type: string,
     *         length?: int|null,
     *         precision?: int|null,
     *         scale?: int|null,
     *         unsigned?: bool,
     *         nullable?: bool,
     *         auto_increment?: bool,
     *         default?: mixed,
     *         comment?: string
     *     }>,
     *     constraints: array<int, array{
     *         type: string,
     *         name?: string,
     *         columns?: array<int, string>,
     *         references_table?: string,
     *         references_columns?: array<int, string>,
     *         on_delete?: string,
     *         on_update?: string
     *     }>,
     *     options: array{
     *         engine?: string,
     *         charset?: string,
     *         collation?: string,
     *         row_format?: string,
     *         temporary?: bool
     *     },
     *     class: class-string<DatabaseSchemaInterface>
     * }>
     */
    public function get_all_with_metadata() : array {
        $schemas   = [];
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {

            $id = $class_string::get_id();

            $schemas[ $id ] = [
                'id'          => $id,
                'label'       => $class_string::get_label(),
                'description' => $class_string::get_description(),
                'table_name'  => $class_string::get_table_name(),

                'columns'     => $class_string::get_columns(),
                'constraints' => $class_string::get_constraints(),
                'options'     => $class_string::get_options(),

                'class'       => $class_string,
            ];
        }

        return $schemas;
    }

    /**
     * Get schema class by ID.
     *
     * @param string $table_name
     * @return class-string<DatabaseSchemaInterface>|null
     */
    public function get_schema_class( string $table_name ) : ?string {
        return $this->get( $table_name );
    }

    /**
     * Get the instance of a table schema class.
     * 
     * @param string $table_name
     * @return DatabaseSchemaInterface|null
     */
    public function get_schema_instance( string $table_name ) : ?DatabaseSchemaInterface {
        $class_string = $this->get( $table_name );

        if ( $class_string === null ) {
            return null;
        }

        return new $class_string();
    }

    /**
     * Get structured schema for a table.
     *
     * @param string $table_name
     * @return array{
     *     columns: array,
     *     constraints: array,
     *     options: array
     * }|null
     */
    public function get_schema( string $table_name ) : ?array {
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {

            if ( $class_string::get_table_name() === $table_name ) {
                return [
                    'columns'     => $class_string::get_columns(),
                    'constraints' => $class_string::get_constraints(),
                    'options'     => $class_string::get_options(),
                ];
            }
        }

        return null;
    }

    /**
     * Return all table names.
     *
     * @return array<int, string>
     */
    public function table_names() : array {
        $names     = [];
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

        /** @var class-string<DatabaseSchemaInterface>[] $core */
        $core = [
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

        foreach ( $core as $schema_class ) {
            $this->core[ $schema_class::get_table_name() ] = $schema_class;
        }

        $this->core_loaded = true;
    }

    /**
     * Assert schema interface compliance.
     *
     * @param string $class_string
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