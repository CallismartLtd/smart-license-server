<?php
/**
 * Cron execution handler class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Web
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Web;

use SmartLicenseServer\Background\BackgroundRunner;
use SmartLicenseServer\Background\BackgroundStatus;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\RequestFinisher;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environments\Application\Boot\BootMode;
use SmartLicenseServer\Environments\Application\Boot\BootModeResolver;
use SmartLicenseServer\Environments\Application\Kernel\ExecutionHandlerInterface;
use Throwable;

/**
 * Handles the public cron URL (<site>/cron), for external cron services
 * such as cron-job.org on hosts without cron.
 *
 * The URL is public: a secret in a query string ends up in logs anyway.
 * It is made harmless instead:
 *
 *  - every caller gets the same answer at once: 204 No Content, no body,
 *    no caching, sent before any work starts (RequestFinisher), so it
 *    reveals nothing, not even through its timing;
 *  - the work is only what is due (BackgroundRunner): due scheduled tasks
 *    and a slice of the queue, at most once a minute, one run at a time,
 *    and nothing during an update;
 *  - GET and HEAD only; other methods get 405 and nothing runs.
 *
 * Chosen at boot instead of the web stack (see CronBootstrapper), so a
 * cron request sets up no routes, sessions or identity.
 */
final class CronExecutionHandler implements ExecutionHandlerInterface {

	/**
	 * URL path, relative to the application URL.
	 */
	public const PATH = 'cron';

	/**
	 * Constructor.
	 *
	 * @param Container        $container Resolves the runner only when work is due to run.
	 * @param BootModeResolver $resolver  Whether the application is installed and available.
	 */
	public function __construct(
		private Container $container,
		private BootModeResolver $resolver
	) {}

	/**
	 * Whether the current request is for the cron URL.
	 *
	 * Compares paths only, from the request line, before anything else is
	 * set up: the application may live in a subdirectory.
	 *
	 * @param string|URL $app_url The application URL (url()).
	 * @return bool
	 */
	public static function matches( string|URL $app_url ) : bool {
		$requested = (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		$base      = (string) ( $app_url instanceof URL ? $app_url->get_path() : URL::from( $app_url )->get_path() );

		// "/" at the site root, "/smliser/" in a subdirectory: drop the trailing slash before appending.
		$base      = rtrim( $base, '/' );

		return rtrim( $requested, '/' ) === $base . '/' . self::PATH;
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle() : int {
		$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );

		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			RequestFinisher::finish( 405, '', array( 'Allow' => 'GET, HEAD' ) );
			return 1;
		}

		$released = RequestFinisher::finish( 204 );

		// Not installed, or in maintenance: answer the same way, run nothing.
		if ( BootMode::Normal !== $this->resolver->resolve() ) {
			return 0;
		}

		try {
			$this->container->get( BackgroundRunner::class )->run( BackgroundStatus::SOURCE_CRON_URL, array( 'release' => $released ) );
		} catch ( Throwable $e ) {
			\smliser_log_error( sprintf( '[CronExecutionHandler] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
		}

		return 0;
	}
}