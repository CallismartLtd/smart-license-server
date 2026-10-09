<?php
/**
 * Apply update job class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Updates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Background\Jobs\Updates;

use RuntimeException;
use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Console\Commands\Update;
use SmartLicenseServer\Environments\Application\Update\UpdateService;

/**
 * Installs the latest release, or rolls back, from the queue worker.
 *
 * Queued by the scheduled update check (automatic updates) or the admin
 * page. The worker does not update itself: it runs `smliser update run`
 * (or `rollback`) as a separate process, which applies the package and
 * finishes in a further process with the new code, exactly as from the
 * console. The outcome is recorded in state.json (update.attempt) for the
 * admin page.
 *
 * The worker has the old code loaded afterwards and must not run more
 * jobs with it; its stop checker ends it once state.json records another
 * version (see the queue command), and the process supervisor or cron
 * starts it again with the new code.
 */
class ApplyUpdateJob implements JobHandlerInterface {

	/**
	 * Most output kept in the job result, in bytes (the end is kept).
	 *
	 * @var int
	 */
	private const OUTPUT_LIMIT = 4096;

	/**
	 * Constructor.
	 *
	 * @param UpdateService $updates Records the outcome.
	 */
	public function __construct(
		protected UpdateService $updates
	) {}

	/**
	 * {@inheritdoc}
	 *
	 * Expected payload keys:
	 *   - action    (string) "install" (default) or "rollback".
	 *   - reinstall (bool)   Install the current version again. Optional.
	 *
	 * @param array<string, mixed> $payload
	 * @return array{exit_code: int, output: string}
	 * @throws RuntimeException When the update process cannot start or fails; its output is in the message.
	 */
	public function handle( array $payload = [] ): mixed {
		$action = 'rollback' === ( $payload['action'] ?? 'install' ) ? 'rollback' : 'install';

		if ( ! function_exists( 'proc_open' ) ) {
			$message = 'PHP may not start a new process (proc_open is disabled); run the update from the console.';
			$this->updates->record_attempt( $action, false, $message );
			throw new RuntimeException( $message );
		}

		$command = array( PHP_BINARY, \SMLISER_ROOT . 'smliser', Update::name(), 'install' === $action ? 'run' : 'rollback', '--yes' );

		if ( 'install' === $action && ! empty( $payload['reinstall'] ) ) {
			$command[] = '--reinstall';
		}

		$process = proc_open(
			$command,
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'redirect', 1 ) ),
			$pipes,
			\SMLISER_ROOT
		);

		if ( ! is_resource( $process ) ) {
			$this->updates->record_attempt( $action, false, 'Could not start the update process.' );
			throw new RuntimeException( 'Could not start the update process.' );
		}

		fclose( $pipes[0] );

		$output = (string) stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$code   = proc_close( $process );
		$output = strlen( $output ) > self::OUTPUT_LIMIT ? '…' . substr( $output, -self::OUTPUT_LIMIT ) : $output;

		$this->updates->record_attempt( $action, 0 === $code, trim( $output ) );

		if ( 0 !== $code ) {
			throw new RuntimeException( sprintf( 'The %s failed (exit code %d): %s', 'install' === $action ? 'update' : 'rollback', $code, trim( $output ) ) );
		}

		return array( 'exit_code' => $code, 'output' => trim( $output ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_name(): string {
		return 'Apply Update';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_job_description(): string {
		return 'Installs the latest release of the application, or restores the previous one, in a separate process.';
	}
}