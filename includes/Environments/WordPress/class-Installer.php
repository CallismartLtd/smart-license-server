<?php
/**
 * Installation management class file
 * Handles plugin activation actions.
 * 
 * @author Callistus
 * @package Smliser\classes
 * @since 1.0.0
 */
namespace SmartLicenseServer\Environments\WordPress;

use SmartLicenseServer\Config;
use SmartLicenseServer\Database\Schema\DBTables;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

defined( 'SMLISER_ABSPATH' ) || exit;

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
            [__CLASS__, 'monetization_table_upgrade_020'],
            [__CLASS__, 'migrate_bulk_message_table_020'],
            [__CLASS__, 'add_apps_owner_id_020'],
            [__CLASS__, 'change_last_updated_column_020'],
            [__CLASS__, 'licenses_table_modify_column_020']
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
            \smliser_settings_adapter()->set( 'smliser_directory_error', $result->get_error_message() );
        } else {
            \smliser_settings_adapter()->delete( 'smliser_directory_error' );
        }

        self::maybe_create_tables();
        self::install_default_roles();
        
        return true;
       
    }

    /**
     * Create Database table
     */
    private static function maybe_create_tables(){
        $db     = \smliser_dbclass();
        $tables = DBTables::table_names();

        foreach( $tables as $table ) {
            $sql        = "SHOW TABLES LIKE ?";
            $db_table   = $db->get_var( $sql, [$table] );

            if ( $table !== $db_table ) {
                self::create_table( $table, DBTables::get( $table ) );
            }
        }
    }

    /**
     * Creates a database table.
     * 
     * @param string $table_name    The table name
     * @param array $columns        The table columns.
     */
    private static function create_table( string $table_name, array $columns ) {
        $db                 = \smliser_dbclass();     
        $charset_collate    = self::charset_collate();

        $sql = "CREATE TABLE $table_name (";
        foreach ( $columns as $column ) {
            $sql .= "$column, ";
        }

        $sql  = rtrim( $sql, ', ' );
        $sql .= ") $charset_collate;";

        $db->query( $sql );
        
    }

    /**
     * Retrieve the database charset and collate settings.
     *
     * This function generates a string that includes the default character set and collate
     *
     * @return string The generated charset and collate settings string.
     */
    private static function charset_collate() {
        return \smliser_dbclass()->get_charset_collate();
    }

    /**
     * Initialized the repository directory.
     *
     * @return bool|Exception True on success, Exception on failure.
     */
    private static function init_repo_dir() {    
        Config::instance()->bootstrap_files();
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
     * Add status column to the plugins table
     * 
     * @version 0.0.6
     */
    public static function migration_006() {
        $db             = smliser_dbclass();
        $plugin_table   = SMLISER_PLUGINS_TABLE;

        // Check if 'status' column already exists
        $column = $db->get_results("SHOW COLUMNS FROM {$plugin_table} LIKE ?", 'status' );
 
        if ( empty( $column ) ) {
            // Add 'status' column
            $db->query(
                "ALTER TABLE {$plugin_table} 
                ADD COLUMN status VARCHAR(55) NOT NULL DEFAULT 'active'
                AFTER download_link"
            );
        }
    }

    /**
     * Migration of the license table to support multiple hosted applications.
     * Before now this table only supported hosted plugins, but now needs to support other hosted apps.
     *
     * @version 0.1.1
     */
    public static function migration_011() {
        $db    = smliser_dbclass();
        $table = SMLISER_LICENSE_TABLE;

        $results = $db->get_results( "SELECT `id`, `item_id` FROM {$table}" );

        $plugin_ids = [];
        foreach ( $results as $row ) {
            $plugin_ids[ $row['id'] ] = $row['item_id'];
        }

        \smliser_cache()->set( 'smliser_db_migrate_011', $plugin_ids, WEEK_IN_SECONDS );

        // Alter item_id to app_prop if column exists.
        $column_exists = $db->get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, 'item_id']
        );

        if ( $column_exists ) {
            $sql = "ALTER TABLE `{$table}` CHANGE COLUMN `item_id` `app_prop` VARCHAR(600) DEFAULT NULL";
            $db->query( $sql );

            // Move plugin data to new column
            foreach ( $plugin_ids as $row_id => $plugin_id ) {
                $plugin = HostedApplicationService::get_app_by_id( 'plugin', $plugin_id );
                if ( ! $plugin ) {
                    continue;
                }
                $app_prop = sprintf( '%s/%s', $plugin->get_type(), $plugin->get_slug() );
                $db->update( $table, [ 'app_prop' => $app_prop ], [ 'id' => $row_id ] );
            }
        }

        // Alter allowed_sites to max_allowed_domains if column exists.
        $column_exists = $db->get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            [$table, 'allowed_sites']
        );

        if ( $column_exists ) {
            $sql = "ALTER TABLE `{$table}` CHANGE COLUMN `allowed_sites` `max_allowed_domains` VARCHAR(600) DEFAULT NULL";
            $db->query( $sql );
        }
    }

    /**
     * Change the item_id and item_type columns of the monetization table to app_id and app_type
     */
    public static function monetization_table_upgrade_020() {
        $table  = \SMLISER_MONETIZATION_TABLE;
        $db     = \smliser_dbclass();

        $item_id_exists = $db->get_results( "SHOW COLUMNS FROM {$table} LIKE ?", ['item_id'] );

        if ( $item_id_exists ) {
            $sql    = "ALTER TABLE `{$table}` CHANGE COLUMN `item_id` `app_id` BIGINT NOT NULL";
            $db->query( $sql );
        }

        $item_type_exists   = $db->get_results( "SHOW COLUMNS FROM {$table} LIKE ?", ['item_type'] );

        if ( $item_type_exists ) {
            $sql    = "ALTER TABLE `{$table}` CHANGE COLUMN `item_type` `app_type` VARCHAR(50) NOT NULL";
            $db->query( $sql );
        }
    }

    /**
     * Change the body column of the bulk messages table to longtext
     */
    public static function migrate_bulk_message_table_020() {
        $db     = \smliser_dbclass();
        $table  = \SMLISER_BULK_MESSAGES_TABLE;

        $sql    = "ALTER TABLE `{$table}` CHANGE `body` `body` LONGTEXT DEFAULT NULL";

        $db->query( $sql );
    }

    /**
     * Change the last_updated column of the apps table to `updated_at`
     */
    public static function change_last_updated_column_020() {
        $db     = \smliser_dbclass();
        $tables = [\SMLISER_SOFTWARE_TABLE, \SMLISER_PLUGINS_TABLE, \SMLISER_THEMES_TABLE];

        foreach ( $tables as $table ) {
            $column_exists   = $db->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'last_updated'" );
            if ( ! $column_exists ) {
                continue;
            }

            $sql    = "ALTER TABLE `{$table}` CHANGE `last_updated` `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

            $db->query( $sql );            
        }


    }

    /**
     * Change the user_id column of the licenses table to `licensee_fullname`
     */
    public static function licenses_table_modify_column_020() {
        $db             = \smliser_dbclass();
        $table          = \SMLISER_LICENSE_TABLE;
        $user_id_exists = $db->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'user_id'" );
        
        if ( $user_id_exists ) {
            $sql    = "ALTER TABLE `{$table}` CHANGE `user_id` `licensee_fullname` VARCHAR(512) DEFAULT NULL";
            $db->query( $sql );
        }

        $has_created_at = $db->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'created_at'" );

        if ( ! $has_created_at ) {
            $sql    = "ALTER TABLE `{$table}` ADD `created_at` DATETIME DEFAULT NULL";
            $db->query( $sql );
        }
        
        $has_updated_at = $db->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'updated_at'" );

        if ( ! $has_updated_at ) {
            $sql    = "ALTER TABLE `{$table}` ADD `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            $db->query( $sql );
        }

        // Modify the start_date and end_date columns to become datetime.
        $sql    = "ALTER TABLE `{$table}` CHANGE `start_date` `start_date` DATETIME DEFAULT NULL";
        $db->query( $sql );

        $sql    = "ALTER TABLE `{$table}` CHANGE `end_date` `end_date` DATETIME DEFAULT NULL";
        $db->query( $sql );

    }

    /**
     * Add owner_id column to the apps tables
     */
    public static function add_apps_owner_id_020() {
        $db         = \smliser_dbclass();
        $tables     = [\SMLISER_PLUGINS_TABLE, \SMLISER_THEMES_TABLE, \SMLISER_SOFTWARE_TABLE];
        $new_column = 'owner_id';

        foreach ( $tables as $table ) {
            $column_exists  = $db->get_results( "SHOW COLUMNS FROM {$table} LIKE ?", [$new_column] );

            if ( $column_exists ) {
                continue;
            }

            $sql    = "ALTER TABLE `{$table}` ADD COLUMN {$new_column} BIGINT(20) NOT NULL AFTER `id`";

            $db->get_var( $sql );
        }
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
