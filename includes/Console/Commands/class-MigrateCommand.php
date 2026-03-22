<?php
/**
 * Migrate command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Config;
use SmartLicenseServer\Database\Schema\DBTables;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Creates any missing database tables by delegating to the environment
 * provider's install_tables() method.
 */
class MigrateCommand implements CommandInterface {

    public static function name(): string {
        return 'migrate';
    }

    public static function description(): string {
        return 'Create any missing database tables.';
    }

    public function execute( array $args = [] ): void {
        $provider = Config::env_provider();

                $db     = smliser_db();
        $tables = DBTables::table_names();

        foreach ( $tables as $table ) {
            $existing = $db->get_var( 'SHOW TABLES LIKE ?', [ $table ] );

            if ( $table !== $existing ) {
                $this->create_table( $table, DBTables::get( $table ) );
                echo sprintf( '  Created table: %s' . PHP_EOL, $table );
            } else {
                echo sprintf( '  Already exists: %s' . PHP_EOL, $table );
            }
        }

        echo 'Done.' . PHP_EOL;
    }

    /**
     * Create a single database table from a column definition array.
     *
     * @param string   $table_name Table name constant.
     * @param string[] $columns    Column definition strings from DBTables.
     * @return void
     */
    private function create_table( string $table_name, array $columns ): void {
        $db              = smliser_db();
        $charset_collate = $db->get_charset_collate();

        $sql  = "CREATE TABLE {$table_name} (";
        $sql .= implode( ', ', $columns );
        $sql .= ") {$charset_collate};";

        $db->query( $sql );
    }
}