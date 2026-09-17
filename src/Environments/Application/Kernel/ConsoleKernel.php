<?php
/**
 * Console kernel class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Kernel
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Kernel;

use Callismart\DBPrism\Database;
use SmartLicenseServer\Environments\Application\ApplicationEnvironment;

/**
 * Console kernel coordinates console command lifecycle.
 *
 * @package SmartLicenseServer\Environments\Application\Kernel
 * @since 0.2.0
 */
class ConsoleKernel extends Kernel {

    /**
     * Exit code.
     *
     * @var int
     */
    protected int $exit_code = 0;

    /**
     * Constructor.
     *
     * All execution dependencies are explicitly declared and resolved
     * by the container when this class is retrieved.
     *
     * @param ApplicationEnvironment    $app     The application environment adapter.
     * @param ExecutionHandlerInterface $handler The CLI command handler strategy.
     * @param Database                  $db      Database instance.
     */
    public function __construct(
        protected ApplicationEnvironment $app,
        protected ExecutionHandlerInterface $handler,
        protected Database $db
    ) {}

    /**
     * {@inheritdoc}
     */
    public function boot() : static {
        $this->app->boot();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run() : static {
        $this->exit_code = $this->handler->handle();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate() : never {
        $this->db->close();

        exit( $this->exit_code );
    }
}