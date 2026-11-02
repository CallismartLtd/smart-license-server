<?php
/**
 * Database migration console commands.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Migrations\Migrator;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;
use SmartLicenseServer\Environments\Application\Maintenance\MaintenanceMode;
use SmartLicenseServer\Schema\MigrationRegistry;
use SmartLicenseServer\Schema\SchemaRegistry;
use Throwable;

/** Inspect, apply and revert registered migrations. */
final class MigrateCommand extends AbstractCommand {
	private bool $cancelled = false;

	public function __construct(
		protected Database $db,
		protected SchemaRegistry $schema,
		protected MaintenanceMode $maintenance,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $io, $output, $script_name );
	}

	public static function name() : string {
		return 'migrate';
	}

	public function description() : string {
		return 'Inspect, apply and revert registered database migrations.';
	}

	public function synopsis() : string {
		return sprintf( '%s <status|run|revert|help> [--yes, -y]', $this->command_prefix() );
	}

	public function help() : string {
		$prefix = $this->command_prefix();
		return implode( PHP_EOL, [
			'Subcommands:',
			'  status    Show applied and pending migrations without changing the database.',
			'  run       Apply pending registered migrations only.',
			'  revert    Revert the latest migration run, if every migration in it is reversible.',
			'  help      Show this help.',
			'',
			'`run` and `revert` enable maintenance mode and pause background jobs first.',
			'`run` turns maintenance off after success only if it enabled it. If a migration',
			'fails, maintenance stays on. A successful `revert` also leaves maintenance on',
			'so you can restore or verify code that matches the reverted schema.',
			'',
			'Creating missing application tables is a separate installer operation; `run`',
			'does not create or repair tables outside the migrations themselves.',
			'',
			'Options:',
			'  --yes, -y    Do not ask for confirmation.',
			'',
			'Examples:',
			"  {$prefix} status",
			"  {$prefix} run --yes",
			"  {$prefix} revert",
		] );
	}

	public function get_subcommands() : array {
		return [
			'status' => [ $this, 'handle_status' ],
			'run'    => [ $this, 'handle_run' ],
			'revert' => [ $this, 'handle_revert' ],
			'help'   => [ $this, 'handle_help' ],
		];
	}

	/** Require an explicit subcommand so the command never changes the database implicitly. */
	public function run( CommandInput $input ) : int {
		$this->output->info( 'Choose a migration action:' );
		$this->output->writeln( sprintf( '`%s`', $this->command_prefix( 'status' ) ) );
		$this->output->writeln( sprintf( '`%s`', $this->command_prefix( 'run' ) ) );
		$this->output->writeln( sprintf( '`%s`', $this->command_prefix( 'revert' ) ) );
		return 0;
	}

	/** Display migration status without enabling maintenance or creating the ledger. */
	public function handle_status( CommandInput $input ) : int {
		try {
			$migrator = $this->migrator();
			$has_ledger = $migrator->has_ledger();
			$status = $migrator->status();
		} catch ( Throwable $e ) {
			$this->output->error( sprintf( 'Could not read migration status: %s', $e->getMessage() ) );
			return 1;
		}

		$this->output->info( sprintf( 'Database adapter: %s', $this->db->get_driver() ) );
		$this->output->info( $has_ledger ? 'Migration ledger: present.' : 'Migration ledger: not initialized.' );
		$rows = [];
		$pending = 0;
		$applied = 0;
		foreach ( $status as $class => $entry ) {
			if ( $entry['applied'] ) {
				++$applied;
			} elseif ( $entry['registered'] ) {
				++$pending;
			}
			$rows[] = [
				$class,
				$entry['applied'] ? 'Applied' : ( $entry['registered'] ? 'Pending' : 'Unregistered' ),
				null === $entry['run'] ? '—' : (string) $entry['run'],
				$entry['applied_at'] ?? '—',
			];
		}

		if ( [] === $rows ) {
			$this->output->info( 'No migrations are registered.' );
		} else {
			$this->output->table( [ 'Migration', 'Status', 'Run', 'Applied at (UTC)' ], $rows );
		}
		$this->output->info( sprintf( '%d applied; %d pending.', $applied, $pending ) );
		return 0;
	}

	/** Apply only the migrations returned by the Migrator API. */
	public function handle_run( CommandInput $input ) : int {
		try {
			$migrator = $this->migrator();
			if ( ! $migrator->has_ledger() && ! $this->has_registered_tables() ) {
				$this->output->error( sprintf(
					'No migration ledger or application tables exist. This looks like a fresh database; create its schema through `%s` first.',
					$this->other_command_prefix( 'installer make:tables' )
				) );
				return 1;
			}
		} catch ( Throwable $e ) {
			$this->output->error( sprintf( 'Could not inspect the database before migrating: %s', $e->getMessage() ) );
			return 1;
		}

		$maintenance = $this->begin_maintenance( $input, 'run' );
		if ( null === $maintenance ) {
			return $this->cancelled ? 0 : 1;
		}

		$timer = microtime( true );
		try {
			$applied = $migrator->migrate();
		} catch ( Throwable $e ) {
			$this->output->error( sprintf( 'Migration failed: %s', $e->getMessage() ) );
			$this->output->warning( 'Maintenance mode has been left on. Review the database before reopening the site.' );
			return 1;
		}

		if ( [] === $applied ) {
			$this->output->success( sprintf( 'No migrations were pending. Completed in %.3f seconds.', microtime( true ) - $timer ) );
		} else {
			$this->output->success( sprintf(
				'Applied %d %s: %s. Completed in %.3f seconds.',
				count( $applied ),
				1 === count( $applied ) ? 'migration' : 'migrations',
				implode( ', ', $this->short_names( $applied ) ),
				microtime( true ) - $timer
			) );
		}

		return $this->finish_successful_run( $maintenance );
	}

	/** Revert the latest migration run using Migrator::revert(). */
	public function handle_revert( CommandInput $input ) : int {
		try {
			$migrator = $this->migrator();
			if ( ! $migrator->has_ledger() ) {
				$this->output->info( 'No migration ledger exists; there is no migration run to revert.' );
				return 0;
			}
			$status = $migrator->status();
			$runs = array_filter( array_column( $status, 'run' ), static fn ( mixed $run ) : bool => null !== $run );
			if ( [] === $runs || 0 === max( $runs ) ) {
				$this->output->info( 'There is no migration run to revert. The initial baseline is not reversible.' );
				return 0;
			}
		} catch ( Throwable $e ) {
			$this->output->error( sprintf( 'Could not inspect migration history: %s', $e->getMessage() ) );
			return 1;
		}

		$maintenance = $this->begin_maintenance( $input, 'revert' );
		if ( null === $maintenance ) {
			return $this->cancelled ? 0 : 1;
		}

		try {
			$reverted = $migrator->revert();
		} catch ( Throwable $e ) {
			$this->output->error( sprintf( 'Could not revert the latest migration run: %s', $e->getMessage() ) );
			$this->output->warning( 'Maintenance mode has been left on. Check the database before reopening the site.' );
			return 1;
		}

		if ( [] === $reverted ) {
			$this->output->info( 'The latest recorded run was a baseline; nothing was reverted.' );
			return $this->finish_successful_run( $maintenance );
		}

		$this->output->success( sprintf( 'Reverted %d %s: %s.', count( $reverted ), 1 === count( $reverted ) ? 'migration' : 'migrations', implode( ', ', $this->short_names( $reverted ) ) ) );
		$this->output->warning( sprintf( 'Maintenance mode remains on. Restore or verify matching application code, then run `%s` when it is safe to reopen the site.', $this->other_command_prefix( 'maintenance off' ) ) );
		return 0;
	}

	/** Start maintenance before database mutation, preserving any existing operator state. */
	private function begin_maintenance( CommandInput $input, string $operation ) : ?array {
		$this->cancelled = false;
		$before = $this->maintenance->status();
		if ( $before['active'] && MaintenanceFlag::REASON_MAINTENANCE !== $before['reason'] ) {
			$this->output->error( 'An installation or update owns maintenance mode. Wait for it to finish before changing migration state.' );
			return null;
		}

		$renew = $before['active'] && ! $before['background_paused'];
		if ( ! $this->confirmed( $input, $operation, $renew ) ) {
			$this->output->info( 'Nothing was changed.' );
			$this->cancelled = true;
			return null;
		}

		if ( $before['active'] && ! $renew ) {
			return [ 'owns' => false, 'since' => null ];
		}

		try {
			$token = $this->maintenance->enable(
				$before['active'] ? $before['message'] : sprintf( 'Database migration %s is running. Please try again shortly.', $operation ),
				$before['active'] ? (int) $before['retry_after'] : 300,
				true,
				sprintf( 'smliser migrate %s', $operation )
			);
			$status = $this->maintenance->status();
			if ( $renew ) {
				$this->output->warning( 'Background jobs were running, so maintenance was renewed with jobs paused. The previous bypass link is invalid.' );
				$this->output->writeln( 'New maintenance bypass link: ' . $this->maintenance->bypass_url( $token ) );
			}
			$this->output->info( 'Maintenance mode is active and background jobs are paused.' );
			return [ 'owns' => ! $before['active'], 'since' => $status['since'] ];
		} catch ( Throwable $e ) {
			$this->output->error( sprintf( 'Could not enable maintenance mode; no migration changes were made: %s', $e->getMessage() ) );
			return null;
		}
	}

	/** Ask before database changes and preserve both long and short yes flags. */
	private function confirmed( CommandInput $input, string $operation, bool $renew ) : bool {
		if ( true === $input->get_option( 'yes' ) || true === $input->get_option( 'y' ) ) {
			return true;
		}

		$question = 'revert' === $operation
			? 'Revert the latest migration run? The database may no longer match the current application code.'
			: 'Apply pending migrations now? Visitors and background jobs will be paused.';
		if ( $renew ) {
			$question .= ' This will also renew maintenance and invalidate the previous bypass link.';
		}

		return $this->io->confirm( $question, false );
	}

	/** Turn maintenance off after a successful no-op/run only when this command enabled it. */
	private function finish_successful_run( array $maintenance ) : int {
		if ( ! $maintenance['owns'] ) {
			$this->output->info( 'Maintenance was already on and remains on.' );
			return 0;
		}

		try {
			$current = $this->maintenance->status();
			if ( MaintenanceFlag::REASON_MAINTENANCE !== $current['reason'] || $current['since'] !== $maintenance['since'] ) {
				$this->output->warning( 'Maintenance ownership changed during the operation; the current maintenance mode was left untouched.' );
				return 1;
			}
			$this->maintenance->disable();
			$this->output->success( 'Maintenance mode is off; the site and background jobs can resume.' );
			return 0;
		} catch ( Throwable $e ) {
			$this->output->error( sprintf( 'Operation finished, but maintenance mode could not be cleared: %s', $e->getMessage() ) );
			return 1;
		}
	}

	private function migrator() : Migrator {
		return new Migrator( $this->db, MigrationRegistry::all(), $this->schema->prefix() );
	}

	private function other_command_prefix( string $command ) : string {
		return \is_interactive_shell() ? $command : sprintf( '%s %s', $this->script_name, $command );
	}

	/** Fresh databases are initialized by the installer, which baselines before table creation. */
	private function has_registered_tables() : bool {
		$inspector = new Inspector( $this->db );
		foreach ( $this->schema->get_all_tables() as $table ) {
			if ( $inspector->table_exists( $table->get_name() ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param string[] $classes @return string[] */
	private function short_names( array $classes ) : array {
		return array_map( static fn ( string $class ) : string => substr( strrchr( '\\' . $class, '\\' ), 1 ), $classes );
	}
}
