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

use SmartLicenseServer\Console\CLIAwareTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Database\Schema\DBTables;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Creates any missing database tables.
 */
class MigrateCommand implements CommandInterface {
    use CLIAwareTrait;

    public static function name(): string {
        return 'migrate';
    }

    public static function description(): string {
        return 'Create any missing database tables.';
    }
    public static function synopsis(): string {
        return 'smliser migrate';
    }

    public static function help(): string {
        return '';
    }


    public function execute( array $args = [] ): void {
        $this->start_timer();
        $this->info( 'Running database migrations...' );
        $this->newline();

        $db      = smliser_db();
        $tables  = DBTables::table_names();
        $headers = [ 'Table', 'Status' ];
        $rows    = [];

        $this->progress_start( count( $tables ), 'Checking' );

        foreach ( $tables as $table ) {
            $existing = $db->query( 'SHOW TABLES LIKE ?', [ $table ] );

            if ( $table !== $existing ) {
                $this->create_table( $table, DBTables::get( $table ) );
                $rows[] = [ $table, '✔ Created' ];
            } else {
                $rows[] = [ $table, '— Already exists' ];
            }

            $this->progress_advance();
        }

        $this->progress_finish();
        $this->newline();
        $this->table( $headers, $rows );
        $this->newline();
        $this->done( 'Migration complete.' );
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