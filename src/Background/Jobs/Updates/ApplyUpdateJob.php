<?php
/**
 * Apply update job class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Updates
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Background\Jobs\Updates;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Environments\Application\Update\UpdateException;
use SmartLicenseServer\Environments\Application\Update\UpdateRunner;
use SmartLicenseServer\Environments\Application\Update\UpdateService;

/**
 * Installs the latest release automatically, through the update API.
 *
 * Queued by the scheduled update check. Runs wherever the queue is
 * processed (the console worker, the cron URL, the admin page) through
 * UpdateRunner, as the console and the admin page do. Two steps, each a
 * job of its own, so neither has to fit a long download and the swap
 * into the same web request:
 *
 *  1. prepare: download, verify and stage the release; keep the package.
 *  2. apply:   swap it in (seconds) and finish it, or leave finishing to
 *              the next process on the new code.
 *
 * The outcome is recorded in state.json (update.attempt), which also
 * sends the failure email. A failed update is not retried by the queue:
 * the next scheduled check queues it again if it is still wanted.
 *
 * Payload keys:
 *   - action    (string) "install" (default) or "rollback".
 *   - step      (string) "prepare" (default) or "apply".
 *   - reinstall (bool)   Install the installed version again.
 */
class ApplyUpdateJob implements JobHandlerInterface {
	use QueueAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param UpdateService $updates   Records the outcome.
	 * @param UpdateRunner  $runner    Runs the update.
	 * @param JobQueue      $job_queue Queues the apply step.
	 */
	public function __construct(
		protected UpdateService $updates,
		protected UpdateRunner $runner,
		JobQueue $job_queue
	) {
		$this->job_queue = $job_queue;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $payload
	 * @return array{ok: bool, step: string, message: string, log: string[]}
	 */
	public function handle( array $payload = [] ): mixed {
		$action    = 'rollback' === ( $payload['action'] ?? 'install' ) ? 'rollback' : 'install';
		$step      = 'apply' === ( $payload['step'] ?? 'prepare' ) ? 'apply' : 'prepare';
		$reinstall = ! empty( $payload['reinstall'] );
		$log       = array();
		$progress  = static function ( string $name, string $status, string $message ) use ( &$log ) : void {
			$log[] = sprintf( '[%s] %s', $name, $message );
		};

		try {
			if ( 'rollback' === $action ) {
				return $this->finish_attempt( $action, true, sprintf( 'Version %s was restored.', $this->runner->rollback( $progress ) ), $log );
			}

			if ( 'prepare' === $step ) {
				return $this->prepare( $reinstall, $progress, $log );
			}

			$result = $this->runner->apply_ready( $reinstall, $progress );
		} catch ( UpdateException $e ) {
			return $this->finish_attempt( $action, false, $e->getMessage(), $log );
		}

		return match ( $result['status'] ) {
			UpdateRunner::UP_TO_DATE => $this->finish_attempt( $action, true, sprintf( '%s %s is up to date; nothing was installed.', \SMLISER_APP_NAME, \SMLISER_VER ), $log ),
			UpdateRunner::PENDING    => $this->finish_attempt( $action, true, sprintf( 'Version %s is installed and finishes on the next request to the site.', $result['prepared']['manifest']->version ?? '?' ), $log ),
			default                  => $this->finish_attempt( $action, true, sprintf( 'Version %s was installed.', $result['prepared']['manifest']->version ?? '?' ), $log ),
		};
	}

	/**
	 * Step 1: prepare the release and queue the apply step.
	 *
	 * @param bool     $reinstall
	 * @param callable $progress
	 * @param string[] $log
	 * @return array
	 * @throws UpdateException
	 */
	private function prepare( bool $reinstall, callable $progress, array &$log ) : array {
		$prepared = $this->runner->prepare( $reinstall, $progress );

		if ( null === $prepared ) {
			return $this->finish_attempt( 'install', true, sprintf( '%s %s is up to date; nothing was installed.', \SMLISER_APP_NAME, \SMLISER_VER ), $log );
		}

		$this->dispatch_job( self::class, array( 'action' => 'install', 'step' => 'apply', 'reinstall' => $reinstall ) );

		$message = sprintf( 'Version %s is downloaded and verified; installing it is queued.', $prepared['manifest']->version );

		return array( 'ok' => true, 'step' => 'prepare', 'message' => $message, 'log' => $log );
	}

	/**
	 * Record the attempt's outcome (which emails a failure) and build the job result.
	 *
	 * @param string   $action
	 * @param bool     $ok
	 * @param string   $message
	 * @param string[] $log
	 * @return array
	 */
	private function finish_attempt( string $action, bool $ok, string $message, array $log ) : array {
		$this->updates->record_attempt( $action, $ok, trim( implode( "\n", $log ) . "\n" . $message ) );

		return array( 'ok' => $ok, 'step' => 'install' === $action ? 'apply' : 'rollback', 'message' => $message, 'log' => $log );
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
		return 'Downloads, verifies and installs the latest release of the application through the update API, in two queued steps.';
	}
}