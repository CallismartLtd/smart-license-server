<?php
/**
 * Console kernel class file.
 * 
 * @author Callistus Nwachukwu.
 * @package SmartLicenseServer
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\Application\Kernel;

use SmartLicenseServer\Console\Runners\RunnerInterface;
use SmartLicenseServer\Environments\Application\ApplicationEnvironment;
use SmartLicenseServer\Environments\Application\CLI\ConsoleManager;

/**
 * Console kernel class coordinates console command lifecycle.
 */
class ConsoleKernel extends Kernel {

    /**
     * The current CLI runner.
     * 
     * @var RunnerInterface
     */
    protected RunnerInterface $runner;

    /**
     * The console manager.
     */
    protected ConsoleManager $console_manager;

    /**
     * Exit code.
     * 
     * @var int
     */
    protected int $exit_code = 0;

    public function __construct(
        protected ApplicationEnvironment $app
    ) {}

    /**
     * {@inheritdoc}
     */
    public function boot() : static {
        $this->app->boot();

        $this->container    = $this->app->container();
        $this->runner       = $this->container->get( ConsoleManager::class )->dispatch();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {

        $this->exit_code = $this->runner->init();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate() : never {
        exit( $this->exit_code );
    }

}