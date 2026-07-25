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

use SmartLicenseServer\Console\Traits\CLIUtilsTrait;
use Callismart\DBPrism\Database;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Utils\Table;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Creates any missing database tables.
 */
class MigrateCommand extends AbstractCommand {

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


    public function run( CommandInput $input ): int {
        $info       = $input->get_option( 'info', false );
        $confirmed  = (bool) ( $options['y'] ?? $options['yes'] ?? false );

        if ( $info ) {
            $this->output->info( $this->description() );
            $this->output->writeln( 'Database Adapter: '. smliser_db()->get_driver() );
            return 0;
        }

        $confirmed  = $confirmed ? true : $this->io->confirm( 'Are you sure you want to perform database migration?', true );

        if ( ! $confirmed ) {
            $this->output->success( 'Migration aborted' );
            return 0;
        }

        return $this->migrate();
    }

    protected function migrate() : int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();
        $this->output->info( 'Running database migrations...' );

        $db         = smliser_db();
        $schema     = SchemaRegistry::instance();
        $tables     = $schema->get_all_tables();
        $inspector  = new Inspector( $db );
        
        $headers    = [ 'Table', 'Status' ];
        $rows       = [];

        $this->output->progress_start( count( $tables ), 'Checking' );

        foreach ( $tables as $table ) {
            $table_name = $table->get_name();

            $this->output->progress_update_label( "Checking {$table_name}" );
            
            $table_exists   = $inspector->table_exists( $table_name );

            if ( ! $table_exists ) {
                $this->output->progress_update_label( "Creating {$table_name}" );
                $created    = $this->create_table( $table, $db );

                if ( $created ) {
                    $message    = '✔ Created';
                } else {
                    $message    = '✖ ' . $db->get_last_error() ?? 'Unknown error occured';
                }
                
                
                $rows[] = [ $table_name, $message ];
            } else {
                $this->output->progress_update_label( "Skipping {$table_name}" );
                $rows[] = [ $table_name, '— Already exists' ];
            }

            $this->output->progress_advance();
        }

        $this->output->progress_finish( 'Migration complete.' );
        $this->output->table( $headers, $rows );

        $this->output->success( sprintf( 'All migrations processed. Completed in %ss.', $stopwatch->elapsed() ) );

        return 0;
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
        $result = $db->exec( $sql );

        return $result;
        
    }
}