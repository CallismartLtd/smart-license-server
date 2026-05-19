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
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Database\Utils\Table;
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
     * @return Exception|true
     */
    public static function install() : Exception|true {
        wp_installing( true );
        $installation_error     = new Exception();
        $init_repo  = static::init_repo_dir();

        if ( $init_repo instanceof Exception ) {
            $installation_error->merge_from( $init_repo );
        }

        $create_tables   = static::maybe_create_tables();

        if ( $create_tables instanceof Exception ) {
            $installation_error->merge_from( $create_tables );
        }

        $roles_install  = static::install_default_roles();

        if ( $roles_install instanceof Exception ) {
            $installation_error->merge_from( $roles_install );
        }

        static::maybe_auto_provision_wp_admin();
        
        wp_installing( false );
        return $installation_error->has_errors() ? $installation_error : true;
       
    }

    /**
     * Create Database table
     * 
     * @return Exception|true
     */
    private static function maybe_create_tables() : Exception|true {
        $errors = new Exception();

        $db     = \smliser_db();
        $schema = SchemaRegistry::instance();
        $tables = $schema->table_names();
        $inspector  = new SchemaInspector( $db );

        foreach( $tables as $table ) {

            if ( $inspector->table_exists( $table ) ) {
                $errors->add( 'table_exists', sprintf( '%s table exists, skipping', $table ) );
                continue;
            }

            $created_table  = static::create_table( $schema->get_table( $table ) );

            if ( ! $created_table ) {
                $errors->add( 
                    'unable_to_create_table', 
                    sprintf( 'Unable to create tabe "%s", error: %s',
                        $table, $db->get_last_error()
                    )
                );
            }
        }

        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Creates a database table.
     * 
     * @param Table $table    The table name
     */
    private static function create_table( Table $table ) : bool {
        $db                 = smliser_db();
        $charset_collate    = $db->get_charset_collate();

        $query  = \smliserQueryBuilder()
            ->create_table( $table->get_name() )
            ->add_columns( $table->get_columns() )
            ->add_constraints( $table->get_constraints() );
        $sql    = $query->build() . '' . $charset_collate;
        
        return (bool) $db->exec( $sql );
    }

    /**
     * Initialized the repository directory.
     *
     * @return bool|Exception True on success, Exception on failure.
     */
    private static function init_repo_dir() : Exception|true {    
        return Repository::make_default_directories();
    }

    /**
     * Save or update missing default role in the database.
     */
    public static function install_default_roles() : Exception|true {
        $default_roles  = DefaultRoles::all();
        $base_error     = new Exception();

        foreach ( $default_roles as $slug => $roledata ) {
            $role   = new Role;

            $role->set_capabilities( $roledata['capabilities'] );   
            $role->set_label( $roledata['label'] );
            $role->set_is_canonical( $roledata['is_canonical'] );         
            $role->set_slug( $slug );

            try {
                $role->save();
            } catch ( Exception $e ) {
                $base_error->merge_from( $e );
            } catch ( \Throwable $e ) {
                $base_error->merge_from(
                    new Exception( 'role_save_error', $e->getMessage() )
                );
            }
        }

        return $base_error->has_errors() ? $base_error : true;
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

        $versions = array_keys( static::$db_versions );
        usort( $versions, 'version_compare' );

        foreach ( $versions as $version ) {
            if ( version_compare( $base_version, $version, '<=' ) ) {
                foreach ( static::$db_versions[ $version ] as $callback ) {
                    if ( is_callable( $callback ) ) {
                        call_user_func( $callback );
                    }
                }
            }
        }

        \smliser_cache()->clear();
    }
}
