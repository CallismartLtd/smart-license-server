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
use SmartLicenseServer\Database\Schema\Definitions\{
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

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Database Schema Registry
 *
 * Manages the registration and instantiation of database table schemas.
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
     * Get all registered tables as Table instances.
     *
     * @return Table[]
     */
    public function get_all_tables() : array {
        $tables    = [];
        $providers = $this->all( false, false );

        foreach ( $providers as $class_string ) {
            $tables[] = $this->get_table( $class_string::get_table_name() );
        }

        return array_filter( $tables );
    }

    /**
     * Get a Table instance by its table name.
     * 
     * @param string $table_name
     * @return Table|null
     */
    public function get_table( string $table_name ) : ?Table {
        $class_string = $this->get( $table_name );

        if ( ! $class_string ) {
            return null;
        }

        return Table::make( $table_name )
            ->add_columns( $class_string::get_columns() )
            ->add_constraints( $class_string::get_constraints() );
    }

    /**
     * Return all registered table names.
     *
     * @return array<int, string>
     */
    public function table_names() : array {
        return array_keys( $this->all() );
    }

    /**
     * Load core database schemas.
     *
     * @return void
     */
    protected function load_core() : void {
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
            // Index by table name for easy retrieval via get()
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