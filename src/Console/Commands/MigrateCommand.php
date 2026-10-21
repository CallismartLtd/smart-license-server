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

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Utils\Table;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Creates any missing database tables.
 */
class MigrateCommand extends AbstractCommand {
	public function __construct(
		protected Database $db,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		return parent::__construct( $io, $output, $script_name );
	}

	public static function name() : string {
		return 'migrate';
	}

	public function description() : string {
		return 'Create any missing database tables.';
	}

	public function help() : string {
		$prefix = $this->command_prefix();

		return implode( PHP_EOL, [
			'Creates every registered table that does not exist yet.',
			'Existing tables are left as they are.',
			'',
			'Options:',
			'  --info        Show the database adapter in use, without migrating.',
			'  --yes -y      Skip the confirmation prompt.',
			'',
			'Examples:',
			"  {$prefix}",
			"  {$prefix} --yes",
			"  {$prefix} --info",
		] );
	}

	public function run( CommandInput $input ) : int {
		$info      = $input->get_option( 'info', false );
		$confirmed = true === $input->get_option( 'y' ) || true === $input->get_option( 'yes' );

		if ( $info ) {
			$this->output->info( $this->description() );
			$this->output->writeln( 'Database Adapter: ' . $this->db->get_driver() );
			return 0;
		}

		$confirmed = $confirmed ? true : $this->io->confirm( 'Are you sure you want to perform database migration?', true );

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

		$schema    = SchemaRegistry::instance();
		$tables    = $schema->get_all_tables();
		$inspector = new Inspector( $this->db );
		
		$headers = [ 'Table', 'Status' ];
		$rows    = [];
		$failed  = 0;

		$this->output->progress_start( count( $tables ), 'Checking' );

		foreach ( $tables as $table ) {
			$table_name = $table->get_name();

			$this->output->progress_update_label( "Checking {$table_name}" );
			
			$table_exists = $inspector->table_exists( $table_name );

			if ( ! $table_exists ) {
				$this->output->progress_update_label( "Creating {$table_name}" );
				$created = $this->create_table( $table, $this->db );

				if ( $created ) {
					$message = '✔ Created';
				} else {
					$message = '✖ ' . ( $this->db->get_last_error() ?: 'Unknown error occurred' );
					$failed++;
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

		if ( $failed > 0 ) {
			$this->output->error( sprintf( '%d table(s) could not be created. Completed in %ss.', $failed, $stopwatch->elapsed() ) );
			return 1;
		}

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
	private function create_table( Table $table, Database $db ) : bool {
		$charset_collate = $db->get_charset_collate();

		$query = \smliserQueryBuilder( $this->db->get_driver() )
			->create_table( $table->get_name() )
			->add_columns( $table->get_columns() )
			->add_constraints( $table->get_constraints() );
		$sql = $query->build() . '' . $charset_collate;
		
		usleep( 10000 );
		$result = $db->exec( $sql );

		return $result;
		
	}
}
