<?php
/**
 * UpdateProgressTrait file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

/**
 * Reports update progress to an optional callback.
 *
 * The update classes take a `?callable $progress` the way AppInstaller takes
 * its callbacks, called as:
 *
 *     $progress( string $step, string $status, string $message ): void
 *
 * - step:    what is happening: "check", "download", "verify", "stage",
 *            "apply", "finish", "rollback".
 * - status:  one of the STATUS_* constants.
 * - message: a plain-language line for the console or a log.
 *
 * Failures are not reported here; they are thrown as UpdateException.
 */
trait UpdateProgressTrait {

	/**
	 * Progress statuses.
	 */
	public const STATUS_INFO    = 'INFO';
	public const STATUS_OK      = 'OK';
	public const STATUS_WARNING = 'WARNING';

	/**
	 * Report a step to the callback, if there is one.
	 *
	 * @param callable|null $progress Progress callback.
	 * @param string        $step     Step name.
	 * @param string        $status   One of the STATUS_* constants.
	 * @param string        $message  What happened.
	 * @return void
	 */
	protected function report( ?callable $progress, string $step, string $status, string $message ) : void {
		if ( null !== $progress ) {
			$progress( $step, $status, $message );
		}
	}
}