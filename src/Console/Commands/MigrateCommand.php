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

use SmartLicenseServer\Console\CLIUtilsTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Database\Schema\SchemaRegistry;
use SmartLicenseServer\Database\Schema\Table;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Creates any missing database tables.
 */
class MigrateCommand implements CommandInterface {
    use CLIUtilsTrait;

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
        $options    = $this->parse_options( $args );
        $info       = (bool) ( $options['info'] ?? false );
        $confirmed  = (bool) ( $options['y'] ?? $options['yes'] ?? false );

        if ( $info ) {
            $this->info( $this->description() );
            $this->line( 'Database Adapter: '. smliser_db()->get_engine_type() );
            return;
        }

        $confirmed  = $confirmed ? true : $this->confirm( 'Are you sure you want to perform database migration?', true );

        if ( ! $confirmed ) {
            $this->done( 'Migration aborted' );
            return;
        }

        $this->migrate();
    }

    protected function migrate() : void {
        $this->start_timer();
        $this->info( 'Running database migrations...' );
        $this->newline();

        $db         = smliser_db();
        $schema     = SchemaRegistry::instance();
        $tables     = $schema->get_all_tables();
        $headers    = [ 'Table', 'Status' ];
        $rows       = [];

        $this->progress_start( count( $tables ), 'Checking' );

        foreach ( $tables as $table ) {
            $table_name = $table->get_name();

            $this->progress_update_label( "Checking {$table_name}" );
            
            $table_exists   = $db->table_exists( $table_name );

            if ( ! $table_exists ) {
                $this->progress_update_label( "Creating {$table_name}" );
                $created    = $this->create_table( $table, $db );
                $message    = $created ? '✔ Created' : '✖ ' . $db->get_last_error() ?? 'Unknown error occured';
                
                $rows[] = [ $table_name, $message ];
            } else {
                $this->progress_update_label( "Skipping {$table_name}" );
                $rows[] = [ $table_name, '— Already exists' ];
            }

            $this->progress_advance();
        }

        $this->progress_finish( 'Migration complete.' );
        $this->newline();
        $this->table( $headers, $rows );
        $this->newline();
        $this->done( 'Migration complete.' );
    }

    /**
     * Create a single database table from a column definition array.
     *
     * @param Table $table
     * @param Database $db
     * @return bool
     */
    private function create_table( Table $table, Database $db ): bool {
        $charset_collate = $db->get_charset_collate();

        $query  = \smliserQueryBuilder()
            ->create_table( $table->get_name() )
            ->add_columns( $table->get_columns() )
            ->add_constraints( $table->get_constraints() );
        $sql    = $query->build() . '' . $charset_collate;
        
        usleep( 10000 );
        return $db->exec( $sql );
    }
}