<?php
/**
 * Database Schema Registry
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Schema;

use Callismart\DBPrism\Utils\Column;
use SmartLicenseServer\Contracts\AbstractRegistry;
use SmartLicenseServer\Schema\Definitions\{
    LicenseSchema, LicenseMetaSchema, PluginSchema, PluginMetaSchema,
    ThemeSchema, ThemeMetaSchema, SoftwareSchema, SoftwareMetaSchema,
    AnalyticsLogsSchema, AnalyticsDailySchema, AppDownloadTokenSchema,
    MonetizationSchema, PricingTierSchema, BulkMessagesSchema,
    BulkMessagesAppsSchema, OptionsSchema, OwnersSchema, UsersSchema,
    UserOptionsSchema, ServiceAccountsSchema, RolesSchema,
    RoleCapabilitiesSchema, RoleAssignmentSchema, OrganizationsSchema,
    OrganizationMembersSchema, IdentityFederationSchema,
    BackgroundJobsSchema, FailedJobsSchema
};
use InvalidArgumentException;
use Callismart\DBPrism\Utils\Table;

/**
 * Database Schema Registry
 *
 * Manages the registration and instantiation of database table schemas,
 * and resolves table names: schemas are registered by logical (unprefixed)
 * name, and the active prefix is added when a name is resolved.
 *
 * @method class-string<DatabaseSchemaInterface>|null get( string $table_name )
 * @method array<string, class-string<DatabaseSchemaInterface>|DatabaseSchemaInterface> all( bool $assoc = true, bool $objects = false)
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
     * Prefix added to every table name.
     *
     * Null until set_prefix() is called.
     *
     * @var string|null
     */
    private ?string $prefix = null;

    /**
     * Set the database table prefix.
     *
     * Set when the container registers the registry, and called again to
     * override it (e.g. by the installer when the prefix changes), so table
     * names resolved afterwards use the new prefix in the same process.
     *
     * @param string $prefix Letters, digits and underscores; may be empty.
     * @return void
     * @throws InvalidArgumentException When the prefix contains other characters.
     */
    public function set_prefix( string $prefix ) : void {
        if ( 1 !== preg_match( '/^[A-Za-z0-9_]*$/', $prefix ) ) {
            throw new InvalidArgumentException(
                sprintf( 'SchemaRegistry: invalid table prefix "%s". Use letters, digits and underscores only.', $prefix )
            );
        }

        $this->prefix = $prefix;
    }

    /**
     * Get the database table prefix.
     *
     * @return string
     * @throws \LogicException When the prefix has not been set.
     */
    public function prefix() : string {
        if ( null === $this->prefix ) {
            throw new \LogicException( 'SchemaRegistry: the table prefix has not been set.' );
        }

        return $this->prefix;
    }

    /**
     * Get the real name of a table: the active prefix plus its logical name.
     *
     * @param TableName|string $table A TableName, or a logical name registered by an extension.
     * @return string E.g. "smliser_users".
     * @throws \LogicException When the prefix has not been set.
     */
    public function table_name( TableName|string $table ) : string {
        return $this->prefix() . ( $table instanceof TableName ? $table->value : $table );
    }

    /**
     * Get the logical (unprefixed) name of a registered table.
     *
     * Accepts a TableName, a logical name, or a real (prefixed) name, so
     * code that already holds a real name can still look up its schema.
     *
     * @param TableName|string $table
     * @return string|null Null when no such table is registered.
     */
    public function logical_name( TableName|string $table ) : ?string {
        $name = $table instanceof TableName ? $table->value : $table;

        if ( null !== $this->get( $name ) ) {
            return $name;
        }

        $prefix = $this->prefix ?? '';

        if ( '' !== $prefix && str_starts_with( $name, $prefix ) ) {
            $name = substr( $name, strlen( $prefix ) );

            return null !== $this->get( $name ) ? $name : null;
        }

        return null;
    }

    /**
     * Get all registered tables as Table instances, named with the active prefix.
     *
     * @return Table[]
     */
    public function get_all_tables() : array {
        $tables = [];

        foreach ( array_keys( $this->all() ) as $name ) {
            $tables[] = $this->get_table( $name );
        }

        return array_filter( $tables );
    }

    /**
     * Get a Table instance, named with the active prefix.
     *
     * @param TableName|string $table A TableName, a logical name, or a real (prefixed) name.
     * @return Table|null Null when no such table is registered.
     */
    public function get_table( TableName|string $table ) : ?Table {
        $name = $this->logical_name( $table );

        if ( null === $name ) {
            return null;
        }

        $class_string = $this->get( $name );

        return Table::make( $this->table_name( $name ) )
            ->add_columns( $class_string::get_columns() )
            ->add_constraints( $class_string::get_constraints() );
    }

    /**
     * Get the columns of the given table.
     *
     * @param TableName|string $table A TableName, a logical name, or a real (prefixed) name.
     * @return null|Column[]
     */
    public function get_table_columns( TableName|string $table ) : ?array {
        return $this->get_table( $table )?->get_columns();
    }

    /**
     * Get table column names as array.
     *
     * @param TableName|string $table A TableName, a logical name, or a real (prefixed) name.
     * @return string[]
     */
    public function get_table_column_names( TableName|string $table ) : array {
        $names = [];

        foreach ( $this->get_table_columns( $table ) ?? [] as $column ) {
            $names[] = $column->name;
        }

        return $names;
    }

    /**
     * Return the real (prefixed) names of all registered tables.
     *
     * @return array<int, string>
     */
    public function table_names() : array {
        return array_map( [ $this, 'table_name' ], $this->logical_names() );
    }

    /**
     * Return the logical (unprefixed) names of all registered tables.
     *
     * @return array<int, string>
     */
    public function logical_names() : array {
        return array_map( 'strval', array_keys( $this->all() ) );
    }

    /**
     * Load core database schemas.
     *
     * @return void
     */
    protected function load_core() : void {
        if ( $this->core_loaded ) {
            return;
        }

        /** @var class-string<DatabaseSchemaInterface>[] */
        $schemas = [
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

        foreach ( $schemas as $schema ) {
            // Indexed by logical (unprefixed) name; the prefix is added when a name is resolved.
            $this->core[ $this->schema_table_name( $schema ) ] = $schema;
        }

        $this->core_loaded = true;
    }

    /**
     * The logical (unprefixed) table name a core schema declares.
     *
     * Core schemas must return a TableName value (TableName::X->value) or the
     * case itself. Anything else, typically a prefixed name from
     * TableName::X->table(), would be prefixed a second time when resolved
     * and point at a table that does not exist, so it is rejected here.
     *
     * @param class-string<DatabaseSchemaInterface> $schema
     * @return string
     * @throws \LogicException When the schema does not return a TableName value.
     */
    protected function schema_table_name( string $schema ) : string {
        $name = $schema::get_table_name();

        if ( $name instanceof TableName ) {
            return $name->value;
        }

        if ( null === TableName::tryFrom( (string) $name ) ) {
            throw new \LogicException(
                sprintf(
                    'SchemaRegistry: %s::get_table_name() returned "%s", which is not a TableName value. Return the logical name without the prefix, e.g. TableName::USERS->value.',
                    $schema,
                    $name
                )
            );
        }

        return (string) $name;
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
                sprintf( 'SchemaRegistry: Class "%s" does not exist.', $class_string )
            );
        }

        $interfaces = class_implements( $class_string ) ?: [];
        if ( ! in_array( DatabaseSchemaInterface::class, $interfaces, true ) ) {
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