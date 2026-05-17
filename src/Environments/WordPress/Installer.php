<?php
/**
 * Installation management class file
 * Handles plugin activation actions.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 * @since 0.2.0
 */
namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Database\Inspection\SchemaInspector;
use SmartLicenseServer\Database\Schema\SchemaRegistry;
use SmartLicenseServer\Database\Schema\Table;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

class Installer {

    /**
     * Database version migration callbacks
     * 
     * @var array $db_versions
     */
    private static $db_versions = array(
        '0.0.6' => array(
            [__CLASS__, 'migration_006' ],

        ),
        '0.1.1' => array(
            [__CLASS__, 'migration_011']
        ),
        '0.2.0' => array(
            
        )

    );

    /**
     * Handle installation.
     * 
     * @return \SmartLicenseServer\Exceptions\Exception|null|true
     */
    public static function install() {
        $result = self::init_repo_dir();

        if ( is_smliser_error( $result ) ) {
            \smliser_settings()->set( 'smliser_directory_error', $result->get_error_message() );
        } else {
            \smliser_settings()->delete( 'smliser_directory_error' );
        }

        self::maybe_create_tables();
        self::install_default_roles();
        self::maybe_auto_provision_wp_admin();
        
        return true;
       
    }

    /**
     * Create Database table
     */
    private static function maybe_create_tables(){
        $db     = \smliser_db();
        $schema = SchemaRegistry::instance();
        $tables = $schema->table_names();
        $inspector  = new SchemaInspector( $db );

        foreach( $tables as $table ) {

            if ( $inspector->table_exists( $table ) ) {
                continue;
            }

            static::create_table( $schema->get_table( $table ) );
        }
    }

    /**
     * Creates a database table.
     * 
     * @param Table $table    The table name
     */
    private static function create_table( Table $table ) {
        $db                 = smliser_db();
        $charset_collate    = $db->get_charset_collate();

        $query  = \smliserQueryBuilder()
            ->create_table( $table->get_name() )
            ->add_columns( $table->get_columns() )
            ->add_constraints( $table->get_constraints() );
        $sql    = $query->build() . '' . $charset_collate;
        
        $db->exec( $sql );
        
    }

    /**
     * Initialized the repository directory.
     *
     * @return bool|Exception True on success, Exception on failure.
     */
    private static function init_repo_dir() {    
        return Repository::make_default_directories();
    }

    /**
     * Save or update missing default role in the database.
     */
    public static function install_default_roles() {
        $default_roles  = DefaultRoles::all();

        foreach ( $default_roles as $slug => $roledata ) {
            $role   = new Role;

            $role->set_capabilities( $roledata['capabilities'] );   
            $role->set_label( $roledata['label'] );
            $role->set_is_canonical( $roledata['is_canonical'] );         
            $role->set_slug( $slug );

            try {
                $role->save();
            } catch ( Exception $e ) {
                return $e;
            }
        }
    }

    /**
     * Attempt to auto provision WordPress admin to have access to this application.
     */
    private static function maybe_auto_provision_wp_admin() : void {
        ( new IdentityService )->auto_provision();
    }

    /**
     * Perform database migrations.
     * 
     * @param $base_version The version to perform the migration from.
     */
    public static function db_migrate( string $base_version ) {

        if ( empty( $base_version ) ) {
            return;
        }

        $versions = array_keys( self::$db_versions );
        usort( $versions, 'version_compare' );

        foreach ( $versions as $version ) {
            if ( version_compare( $base_version, $version, '<=' ) ) {
                foreach ( self::$db_versions[ $version ] as $callback ) {
                    if ( is_callable( $callback ) ) {
                        call_user_func( $callback );
                    }
                }
            }
        }

        \smliser_cache()->clear();
    }
}
