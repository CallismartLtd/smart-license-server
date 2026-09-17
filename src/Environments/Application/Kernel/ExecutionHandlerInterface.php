<?php
/**
 * Execution handler contract file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Kernel
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Kernel;

/**
 * Interface ExecutionHandlerInterface
 *
 * Unified contract for executing environment request/command lifecycles.
 *
 * @package SmartLicenseServer\Core\Kernel
 * @since 0.2.0
 */
interface ExecutionHandlerInterface {

    /**
     * Execute the current environment request or command.
     *
     * @return int Exit code (0 for success, non-zero for error).
     */
    public function handle() : int;
}