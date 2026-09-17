<?php
/**
 * Http kernel class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Kernel
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Kernel;

use Callismart\DBPrism\Database;
use SmartLicenseServer\Environments\Application\ApplicationEnvironment;
use SmartLicenseServer\Environments\Application\Web\HttpExecutionHandler;

/**
 * HTTP kernel coordinates the HTTP request lifecycle.
 *
 * @package SmartLicenseServer\Environments\Application\Kernel
 * @since 0.2.0
 */
class HTTPKernel extends Kernel {

    /**
     * Exit status code.
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
     * @param ExecutionHandlerInterface $handler The HTTP request handler strategy.
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
     * Terminate the request.
     *
     * @return never
     */
    public function terminate() : never {
        $this->db->close();

        if ( $this->handler instanceof HttpExecutionHandler ) {
            $response = $this->handler->getResponse();
            if ( $response !== null ) {
                $response->stop();
            }
        }

        exit( $this->exit_code );
    }
}