<?php
/**
 * DowntimeExecutionHandler class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Web
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Web;

use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use SmartLicenseServer\Exceptions\Exception;

/**
 * Class DowntimeExecutionHandler
 *
 * Execution unit for HTTP requests while the application is unavailable
 * (maintenance or upgrade). Every request receives a 503 Service Unavailable
 * response; no routing, authentication or database access takes place.
 *
 * Requests that accept JSON receive the standard JSON error body, all others
 * receive the HTML error document rendered by Response.
 *
 * @package SmartLicenseServer\Environments\Application\Web
 * @since 0.2.0
 */
class DowntimeExecutionHandler implements ExecutionHandlerInterface {

	/**
	 * Error code carried by the downtime response.
	 */
	public const ERROR_CODE = 'service_unavailable';

	/**
	 * The generated HTTP response.
	 *
	 * @var Response|null
	 */
	protected ?Response $response = null;

	/**
	 * Class constructor.
	 *
	 * @param string $message     User-facing explanation of the downtime.
	 * @param int    $retry_after Seconds clients should wait before retrying. Sent as Retry-After.
	 */
	public function __construct(
		protected string $message,
		protected int $retry_after = 300
	) {}

	/**
	 * {@inheritdoc}
	 */
	public function handle() : int {
		$error = new Exception();
		$error->add( self::ERROR_CODE, $this->message, array( 'status' => 503 ) );

		$headers = array(
			'Retry-After'   => (string) max( 0, $this->retry_after ),
			'Cache-Control' => 'no-store, no-cache, must-revalidate',
		);

		if ( $this->wants_json() ) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
		}

		$this->response = Response::error( $error, 503, $headers );
		$this->response->send();

		return 1;
	}

	/**
	 * Get the resolved response instance.
	 *
	 * @return Response|null
	 */
	public function getResponse() : ?Response {
		return $this->response;
	}

	/**
	 * Whether the client asked for a JSON response.
	 *
	 * @return bool
	 */
	protected function wants_json() : bool {
		$accept = (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' );

		return false !== stripos( $accept, 'application/json' );
	}
}