<?php
/**
 * ConsoleExecutionHandler class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Handlers
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\CLI;

use SmartLicenseServer\Environments\Application\CLI\ConsoleDispatcher;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;

/**
 * Class ConsoleExecutionHandler
 *
 * Execution unit handler for CLI commands.
 *
 * @package SmartLicenseServer\Environments\Application\Handlers
 * @since 0.2.0
 */
class ConsoleExecutionHandler implements ExecutionHandlerInterface {

    /**
     * Class constructor.
     *
     * @param ConsoleDispatcher $dispatcher The CLI console dispatcher.
     */
    public function __construct(
        protected ConsoleDispatcher $dispatcher
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle() : int {
        $runner = $this->dispatcher->dispatch();

        return $runner->init();
    }
}