<?php
/**
 * Runner interface file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Runners
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Runners;

/**
 * Contract for a top-level CLI runner — the one-shot NonInteractiveRunner or the
 * interactive shell — invoked once by CLIEnvironment with the full
 * dispatch lifecycle handed off to it.
 */
interface RunnerInterface {

    /**
     * Run the CLI session to completion.
     *
     * @return int Process exit code — 0 for success, non-zero for
     *             failure.
     */
    public function init(): int;
}