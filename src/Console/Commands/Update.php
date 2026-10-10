<?php
/**
 * Update command class file.
 *
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Environments\Application\Update\UpdateException;
use SmartLicenseServer\Environments\Application\Update\Updater;
use SmartLicenseServer\Environments\Application\Update\UpdateRunner;
use SmartLicenseServer\Environments\Application\Update\UpdateService;

/**
 * Updates the application from the update server or a local package.
 *
 * A console front end to UpdateRunner, the same update API the admin page
 * and the queue worker use.
 */
class Update extends AbstractCommand {

	public function __construct(
		protected UpdateService $updates,
		protected UpdateRunner $runner,
		protected Updater $updater,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $io, $output, $script_name );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function name() : string {
		return 'update';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description() : string {
		return sprintf( 'Update %s.', \SMLISER_APP_NAME );
	}

	public function help() : string {
		$lines = array(
			'check'         => 'Asks the update server for the latest version.',
			'dry-run'       => 'Downloads, verifies and checks the latest version without installing it, and lists anything blocking an update.',
			'run'           => 'Installs the latest version (or --package).',
			'finish'        => 'Completes an installed update. Runs automatically after `run`, or on the next request to the site.',
			'rollback'      => 'Restores the previous version, when no database migration has run.',
			'status'        => 'Shows the last check, the last update and the backup.',
			'auto'          => 'Shows or sets automatic updates: --mode=off|security|all.',
			'backup:delete' => 'Deletes the backup of the previous version (rollback is no longer possible).',
		);

		$out = sprintf( "Usage: %s <subcommand> [options]\n\nSubcommands:\n", $this->command_line( '' ) );

		foreach ( $lines as $name => $text ) {
			$out .= sprintf( "  %-14s %s\n", $name, $text );
		}

		return $out . implode(
			"\n",
			array(
				'',
				'Options for run:',
				'  --package=<zip>    Install this package instead of downloading. Its .sha256',
				'                     and .sha256.sig files must sit beside it.',
				'  --reinstall        Install the current version again, to repair its files.',
				'  --trust-unsigned   Accept a local package without a valid signature. Only for',
				'                     --package; downloads from the update server are always verified.',
				'  --yes, -y          Do not ask for confirmation.',
				'',
				'Options for auto:',
				'  --mode=<mode>      off, security (security releases only) or all.',
			)
		);
	}

	public function get_subcommands() : array {
		return array(
			'check'         => array( $this, 'check' ),
			'dry-run'       => array( $this, 'dry_run' ),
			'run'           => array( $this, 'run_update' ),
			'finish'        => array( $this, 'finish' ),
			'rollback'      => array( $this, 'rollback' ),
			'status'        => array( $this, 'status' ),
			'auto'          => array( $this, 'auto' ),
			'backup:delete' => array( $this, 'delete_backup' ),
			'help'          => array( $this, 'handle_help' ),
		);
	}

	public function run( CommandInput $input ) : int {
		$this->output->info( sprintf( '%s %s is installed.', \SMLISER_APP_NAME, \SMLISER_VER ) );
		$this->output->info( sprintf( 'Run `%s` to look for a newer version, or `%s` for every subcommand.', $this->command_line( 'check' ), $this->command_line( 'help' ) ) );

		return 0;
	}

	/*
	|-------------
	| Subcommands
	|-------------
	*/

	/**
	 * Report whether a newer version is published.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function check( CommandInput $input ) : int {
		$check = $this->updates->check( true, $this->progress() );

		if ( null !== $check['error'] ) {
			$this->output->error( $check['error'] );
			return 1;
		}

		if ( $check['available'] ) {
			$this->output->success( sprintf( 'Version %s is available%s (installed: %s). Run `%s` to update.', $check['latest'], $check['security'] ? ' (security release)' : '', \SMLISER_VER, $this->command_line( 'run' ) ) );
		} else {
			$this->output->success( sprintf( '%s %s is up to date.', \SMLISER_APP_NAME, \SMLISER_VER ) );
		}

		return 0;
	}

	/**
	 * Everything an update would do, except installing.
	 *
	 * @param CommandInput $input
	 * @return int 0 when an update could be installed (or none is needed), 1 otherwise.
	 */
	public function dry_run( CommandInput $input ) : int {
		$this->output->info( 'Checking, downloading and verifying without installing...' );

		try {
			$result = $this->runner->dry_run( $this->progress() );
		} catch ( UpdateException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		foreach ( $result['blockers'] as $blocker ) {
			$this->output->error( $blocker );
		}

		if ( null !== $result['error'] ) {
			$this->output->error( $result['error'] );
		}

		foreach ( $result['warnings'] as $warning ) {
			$this->output->warning( $warning );
		}

		if ( ! $result['ok'] ) {
			return 1;
		}

		if ( null === $result['version'] ) {
			$this->output->success( sprintf( '%s %s is up to date; nothing would be installed.', \SMLISER_APP_NAME, \SMLISER_VER ) );
		} else {
			$this->print_package( $result['package'] );
			$this->output->success( sprintf( 'Version %s is downloaded, verified and ready. Run `%s` to install it.', $result['version'], $this->command_line( 'run' ) ) );
		}

		return 0;
	}

	/**
	 * Download (or take a local package), verify, install, then finish.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function run_update( CommandInput $input ) : int {
		$package        = $input->get_option( 'package' );
		$package        = is_string( $package ) && '' !== $package ? $package : null;
		$reinstall      = (bool) $input->get_option( 'reinstall' );
		$trust_unsigned = (bool) $input->get_option( 'trust-unsigned' );
		$yes            = (bool) ( $input->get_option( 'yes' ) ?? $input->get_option( 'y' ) );

		if ( $trust_unsigned && null === $package ) {
			$this->output->error( '--trust-unsigned only applies to --package. Downloads from the update server are always verified.' );
			return 1;
		}

		if ( $trust_unsigned ) {
			$this->output->warning( '--trust-unsigned: a package without a valid signature will be accepted. Use this only for a package you built or received directly.' );
		}

		$this->output->info( null === $package ? 'Checking for the latest version...' : sprintf( 'Verifying %s...', basename( $package ) ) );

		$confirm = function ( array $prepared ) use ( $yes ) : bool {
			foreach ( $prepared['warnings'] as $warning ) {
				$this->output->warning( $warning );
			}

			$manifest = $prepared['manifest'];

			$this->print_package( $prepared['package'] );
			$this->output->success( sprintf( 'Package verified: %s %s for %s.', $manifest->name, $manifest->version, $manifest->target ) );

			if ( ! $yes && ! $this->io->confirm( sprintf( 'Update from %s to %s now? The site is unavailable for the few seconds it takes. Back up your database first.', \SMLISER_VER, $manifest->version ), false ) ) {
				return false;
			}

			$this->output->info( 'Installing...' );

			return true;
		};

		try {
			$result = $this->runner->install( $package, $reinstall, $trust_unsigned, $this->progress(), $confirm, true );
		} catch ( UpdateException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		return match ( $result['status'] ) {
			UpdateRunner::UP_TO_DATE => $this->done( sprintf( '%s %s is up to date.', \SMLISER_APP_NAME, \SMLISER_VER ) ),
			UpdateRunner::CANCELLED  => $this->done( sprintf( 'Update cancelled; nothing was changed. The verified package is kept for `%s`.', $this->command_line( 'run' ) ), false ),
			UpdateRunner::PENDING    => $this->pending( $result['finish'] ?? array() ),
			default                  => (int) ( $result['finish']['exit_code'] ?? 0 ),
		};
	}

	/**
	 * Complete an installed update. Runs with the new code.
	 *
	 * This process's own boot may already have finished it (see
	 * ApplicationEnvironment); that is reported the same way.
	 *
	 * @param CommandInput|null $input
	 * @return int
	 */
	public function finish( ?CommandInput $input = null ) : int {
		try {
			$result = $this->runner->finish_pending( $this->progress() ) ?? $this->runner->finished_at_boot();
		} catch ( UpdateException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		if ( null === $result ) {
			$journal = $this->updater->status();

			if ( Updater::STAGE_SWAPPED === ( $journal['stage'] ?? null ) && \SMLISER_VER !== ( $journal['to'] ?? null ) ) {
				$this->output->error( sprintf( 'This process runs version %s, not the new %s. Run `%s` again; a new process loads the new code.', \SMLISER_VER, $journal['to'] ?? '?', $this->command_line( 'finish' ) ) );
				return 1;
			}

			$this->output->info( 'There is no update waiting to be finished.' );
			return 0;
		}

		$this->output->success(
			sprintf(
				'%s was updated from %s to %s%s.',
				\SMLISER_APP_NAME,
				$result['from'],
				$result['to'],
				empty( $result['package']['source'] ) ? '' : sprintf( ' (package from %s)', $result['package']['source'] )
			)
		);

		return 0;
	}

	/**
	 * Restore the previous version's files.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function rollback( CommandInput $input ) : int {
		$journal = $this->updater->status();
		$yes     = (bool) ( $input->get_option( 'yes' ) ?? $input->get_option( 'y' ) );

		if ( null !== $journal && ! $yes && ! $this->io->confirm( sprintf( 'Restore version %s (the files from before the update to %s)?', $journal['from'] ?? '?', $journal['to'] ?? '?' ), false ) ) {
			$this->output->info( 'Rollback cancelled; nothing was changed.' );
			return 0;
		}

		try {
			$version = $this->runner->rollback( $this->progress() );
		} catch ( UpdateException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( sprintf( 'Version %s was restored.', $version ) );

		return 0;
	}

	/**
	 * Show the last update.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function status( CommandInput $input ) : int {
		$check  = $this->updates->last_check();
		$backup = $this->updater->backup();

		$this->output->table(
			array( 'Field', 'Value' ),
			array(
				array( 'Installed', \SMLISER_VER ),
				array( 'Latest', (string) ( $check['latest'] ?? 'unknown' ) . ( $check['security'] ? ' (security)' : '' ) ),
				array( 'Last checked', (string) $check['checked_at'] ),
				array( 'Check error', (string) ( $check['error'] ?? '' ) ),
				array( 'Automatic updates', $this->updates->auto_mode() ),
				array( 'Backup', null === $backup ? 'none' : sprintf( '%s, made %s', $backup['version'], $backup['made_at'] ) ),
			)
		);

		foreach ( $this->updates->blockers() as $blocker ) {
			$this->output->warning( $blocker );
		}

		$journal = $this->updater->status();

		if ( null === $journal ) {
			return 0;
		}

		$package = is_array( $journal['package'] ?? null ) ? $journal['package'] : array();

		$this->output->table(
			array( 'Field', 'Value' ),
			array(
				array( 'From', (string) ( $journal['from'] ?? '' ) ),
				array( 'To', (string) ( $journal['to'] ?? '' ) ),
				array( 'Package source', (string) ( $package['source'] ?? 'not recorded' ) ),
				array( 'Package SHA-256', (string) ( $package['sha256'] ?? '' ) ),
				array( 'Signed by', (string) ( $package['signed_by'] ?? ( empty( $package ) ? '' : 'unsigned' ) ) ),
				array( 'Stage', (string) ( $journal['stage'] ?? '' ) ),
				array( 'Started', (string) ( $journal['started_at'] ?? '' ) ),
				array( 'Finished', (string) ( $journal['finished_at'] ?? $journal['rolled_back_at'] ?? '' ) ),
			)
		);

		if ( Updater::STAGE_SWAPPED === ( $journal['stage'] ?? null ) ) {
			$this->output->warning( sprintf( 'This update is installed but not finished, so the site is in maintenance. Run `%s`, or `%s`.', $this->command_line( 'finish' ), $this->command_line( 'rollback' ) ) );
		}

		return 0;
	}

	/**
	 * Show or set the automatic update mode.
	 *
	 * @param CommandInput $input --mode=off|security|all to set it.
	 * @return int
	 */
	public function auto( CommandInput $input ) : int {
		$mode = $input->get_option( 'mode' );

		if ( is_string( $mode ) && '' !== $mode ) {
			try {
				$this->updates->set_auto_mode( $mode );
			} catch ( UpdateException $e ) {
				$this->output->error( $e->getMessage() );
				return 1;
			}
		}

		$this->output->info(
			match ( $this->updates->auto_mode() ) {
				UpdateService::AUTO_OFF      => 'Automatic updates are off.',
				UpdateService::AUTO_SECURITY => 'Security releases are installed automatically.',
				UpdateService::AUTO_ALL      => 'Every release is installed automatically.',
			}
		);

		return 0;
	}

	/**
	 * Delete the backup of the previous version.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function delete_backup( CommandInput $input ) : int {
		$yes = (bool) ( $input->get_option( 'yes' ) ?? $input->get_option( 'y' ) );

		if ( ! $yes && ! $this->io->confirm( 'Delete the backup? Rolling back will no longer be possible.', false ) ) {
			return 0;
		}

		try {
			$deleted = $this->updater->delete_backup();
		} catch ( UpdateException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( $deleted ? 'The backup was deleted.' : 'There is no backup.' );

		return 0;
	}

	/*
	|---------
	| Helpers
	|---------
	*/

	/**
	 * A progress callback that prints each update step.
	 *
	 * Also what the queue worker records: ApplyUpdateJob keeps this
	 * output as the attempt's message.
	 *
	 * @return callable( string $step, string $status, string $message ): void
	 */
	protected function progress() : callable {
		return function ( string $step, string $status, string $message ) : void {
			$line = sprintf( '[%s] %s', $step, $message );

			match ( $status ) {
				UpdateService::STATUS_OK      => $this->output->success( $line ),
				UpdateService::STATUS_WARNING => $this->output->warning( $line ),
				default                       => $this->output->info( $line ),
			};
		};
	}

	/**
	 * Print where a verified package came from.
	 *
	 * @param array|null $package {source, sha256, signed_by} from UpdateService::prepare().
	 * @return void
	 */
	protected function print_package( ?array $package ) : void {
		if ( empty( $package ) ) {
			return;
		}

		$this->output->info( sprintf( 'Source:    %s', $package['source'] ?? 'unknown' ) );
		$this->output->info( sprintf( 'SHA-256:   %s', $package['sha256'] ?? '' ) );
		$this->output->info( sprintf( 'Signed by: %s', $package['signed_by'] ?? 'nobody (unsigned)' ) );
	}

	/**
	 * Print a final message and return the exit code.
	 *
	 * @param string $message
	 * @param bool   $success Print as success (otherwise as information).
	 * @return int
	 */
	protected function done( string $message, bool $success = true ) : int {
		$success ? $this->output->success( $message ) : $this->output->info( $message );

		return 0;
	}

	/**
	 * Explain an installed update that finishes later.
	 *
	 * @param array $finish UpdateRunner finish details.
	 * @return int
	 */
	protected function pending( array $finish ) : int {
		if ( 'process' === ( $finish['method'] ?? '' ) ) {
			$this->output->warning( sprintf( 'The finish step did not complete. Run `%s`, or open the site: the next request finishes it.', $this->command_line( 'finish' ) ) );
			return 1;
		}

		$this->output->success( sprintf( 'Files installed. Run `%s` (or open the site) to finish; the site stays in maintenance until then.', $this->command_line( 'finish' ) ) );

		return 0;
	}

	/**
	 * The command line for a subcommand, as the user would type it.
	 *
	 * @param string $subcommand Subcommand.
	 * @return string
	 */
	protected function command_line( string $subcommand ) : string {
		$prefix = \is_interactive_shell() ? static::name() : $this->script_name . ' ' . static::name();

		return trim( "{$prefix} {$subcommand}" );
	}
}