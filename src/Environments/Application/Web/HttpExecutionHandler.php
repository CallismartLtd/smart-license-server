<?php
/**
 * HttpExecutionHandler class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Handlers
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Web;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Environments\Application\Web\HttpDispatcher;

/**
 * Class HttpExecutionHandler
 *
 * Execution unit handler for HTTP requests.
 *
 * @package SmartLicenseServer\Environments\Application\Handlers
 * @since 0.2.0
 */
class HttpExecutionHandler implements ExecutionHandlerInterface {

    /**
     * The generated HTTP response.
     *
     * @var Response|null
     */
    protected ?Response $response = null;

    /**
     * Class constructor.
     *
     * @param HttpDispatcher $dispatcher The HTTP route/request dispatcher.
     * @param Request        $request    The incoming HTTP request object.
     */
    public function __construct(
        protected HttpDispatcher $dispatcher,
        protected Request $request
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle() : int {        
        $this->response = $this->dispatcher->dispatch( $this->request );
        $this->response->send();

        return $this->response->get_status_code() >= 400 ? 1 : 0;
    }

    /**
     * Get the resolved response instance.
     *
     * @return Response|null
     */
    public function getResponse() : ?Response {
        return $this->response;
    }
}