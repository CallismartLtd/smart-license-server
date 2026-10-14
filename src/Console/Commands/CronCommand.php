<?php
/**
 * Cron command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Background\BackgroundRunner;
use SmartLicenseServer\Background\BackgroundStatus;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;

/**
 * Runs all due background work in one go: scheduled tasks, then queued jobs.
 *
 * The one line a crontab or a hosting panel's "Cron Jobs" needs:
 *
 *   * * * * * cd /path/to/site && php smliser cron >> /dev/null 2>&1
 *
 * Covers schedules and the queue together, so hosts that do not allow a
 * long-running queue worker still process jobs, within about a minute.
 * Hosts whose cron runs every 5 minutes at most work too; jobs just wait
 * a little longer. Safe to overlap: a run that finds another one running
 * ends at once.
 */
class CronCommand extends AbstractCommand {

	/**
	 * Constructor.
	 *
	 * @param BackgroundRunner $runner      Runs the due work.
	 * @param InputInterface   $io          CLI input interface stream.
	 * @param OutputInterface  $output      CLI output interface stream.
	 * @param ScriptName       $script_name Executable binary alias context.
	 */
	public function __construct(
		protected BackgroundRunner $runner,
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
		return 'cron';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description() : string {
		return 'Run all due background work: scheduled tasks, then queued jobs.';
	}

	/**
	 * {@inheritdoc}
	 */
	public function help() : string {
		$script_name = $this->script_name;

		return implode( PHP_EOL, [
			'Runs the scheduled tasks that are due, then processes queued jobs for',
			'up to 45 seconds. Add it to cron to run every minute:',
			'',
			"  * * * * * cd " . rtrim( \SMLISER_ROOT, '/' ) . " && php {$script_name} cron >> /dev/null 2>&1",
			'',
			'Options:',
			'  --timeout=<sec>   Seconds of queued jobs per run. Default: 45.',
			'  --verbose         Print each task and job.',
			'',
			'A long-running worker (`queue work`) can still be used alongside it',
			'for instant job processing.',
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function run( CommandInput $input ) : int {
		$timeout = $input->get_option( 'timeout', null );
		$verbose = (bool) $input->get_option( 'verbose', false );

		$result = $this->runner->run(
			BackgroundStatus::SOURCE_CLI_CRON,
			[],
			null !== $timeout ? max( 1, (int) $timeout ) : null,
			$verbose ? fn( string $line ) => $this->output->writeln( $line ) : null
		);

		switch ( $result['result'] ) {
			case BackgroundRunner::BUSY:
				$this->output->info( 'Another run is in progress; nothing to do.' );
				return 0;

			case BackgroundRunner::UPDATING:
				$this->output->warning( 'The application is being updated, or an update has not finished; nothing was run.' );
				return 0;

			default:
				$this->output->success(
					sprintf(
						'Ran %d task(s) (%d failed) and %d job(s) in %ss.',
						$result['tasks'],
						$result['failed'],
						$result['jobs'],
						$result['seconds']
					)
				);

				return $result['failed'] > 0 ? 1 : 0;
		}
	}
}
