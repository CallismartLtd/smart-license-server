<?php
/**
 * Site probe class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Health
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Health;

use Callismart\Http\HttpClient;
use Callismart\Http\Exceptions\HttpTimeoutException;
use SmartLicenseServer\Core\URLManager;

/**
 * Requests the site's own URL and reports what came back, as a visitor
 * would see it.
 *
 * Philosophy:
 *
 *  - What a visitor gets. A GET of the application URL, following redirects
 *    and verifying TLS, so an expired certificate or a redirect loop shows
 *    as the failure it is for visitors.
 *  - Answered or not. An HTTP status, whatever it is, means the web server
 *    and PHP answered; no answer (refused, timed out, TLS failed) is the
 *    worse case and is reported with the reason.
 *  - Quick to give up. A short timeout keeps a down site from stalling the
 *    caller for long: a request that takes longer is reported as down.
 *  - From this server. The request leaves this machine for the public URL;
 *    DNS, a firewall or NAT that differ inside the server can make it fail
 *    while visitors reach the site. The recommendation says so.
 *
 * Verdict: 2xx passes; 3xx (redirects not resolved) and 4xx are warnings,
 * the site answers but not with the page; 5xx and no answer are critical.
 */
class SiteProbe {

	/**
	 * Seconds to wait for an answer.
	 */
	public const TIMEOUT = 5;

	/**
	 * Redirects followed before giving up.
	 */
	private const MAX_REDIRECTS = 5;

	/**
	 * Constructor.
	 *
	 * @param HttpClient $http HTTP client.
	 * @param URLManager $urls Knows the application URL.
	 */
	public function __construct(
		private HttpClient $http,
		private URLManager $urls
	) {}

	/**
	 * Request the site URL once.
	 *
	 * @return array{url: string, status: ?int, reason: string, seconds: float, final_url: ?string, error: ?string, timed_out: bool}
	 *         status is null when nothing answered; error then says why.
	 */
	public function probe() : array {
		$url     = (string) $this->urls->url();
		$started = \microtime( true );
		$result  = [
			'url'       => $url,
			'status'    => null,
			'reason'    => '',
			'seconds'   => 0.0,
			'final_url' => null,
			'error'     => null,
			'timed_out' => false,
		];

		try {
			$response = $this->http->get(
				$url,
				[ 'User-Agent' => 'SmartLicenseServer-HealthProbe', 'Cache-Control' => 'no-cache' ],
				[ 'timeout' => self::TIMEOUT, 'max_redirects' => self::MAX_REDIRECTS ]
			);

			$result['status']    = $response->status_code;
			$result['reason']    = $response->reason_phrase;
			$result['final_url'] = $response->was_redirected() ? $response->final_url() : null;
		} catch ( HttpTimeoutException $e ) {
			$result['error']     = \sprintf( 'no answer within %d seconds', self::TIMEOUT );
			$result['timed_out'] = true;
		} catch ( \Throwable $e ) {
			$result['error'] = $e->getMessage();
		}

		$result['seconds'] = \microtime( true ) - $started;

		return $result;
	}

	/**
	 * Whether a probe result counts as the site being down: no answer, or a 5xx.
	 *
	 * @param array{status: ?int} $probe A probe() result.
	 * @return bool
	 */
	public static function is_down( array $probe ) : bool {
		return null === $probe['status'] || $probe['status'] >= 500;
	}

	/**
	 * A probe result as a health check, shaped like SystemHealth's.
	 *
	 * @param array{url: string, status: ?int, reason: string, seconds: float, final_url: ?string, error: ?string, timed_out: bool}|null $probe
	 *        A probe() result; null to probe now.
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}
	 */
	public function check( ?array $probe = null ) : array {
		$probe ??= $this->probe();
		$took    = $probe['seconds'] < 1 ? \sprintf( '%d ms', (int) \round( $probe['seconds'] * 1000 ) ) : \sprintf( '%.1f s', $probe['seconds'] );
		$status  = $probe['status'];

		if ( null === $status ) {
			return $this->make_check(
				'critical',
				\sprintf( '%s did not answer: %s.', $probe['url'], \rtrim( (string) $probe['error'], '.' ) ),
				'Check that the web server and PHP are running, and read their error logs. This request is made from this server: if visitors can reach the site, DNS or a firewall inside the server may differ.'
			);
		}

		$answer = \sprintf(
			'%s answered %d %s in %s%s.',
			$probe['url'],
			$status,
			$probe['reason'],
			$took,
			null === $probe['final_url'] ? '' : ' (after redirects, at ' . $probe['final_url'] . ')'
		);

		return match ( true ) {
			$status >= 500 => $this->make_check( 'critical', $answer, 'The site answers with a server error. Read the PHP and web server error logs; if it is in maintenance mode, finish or roll back the update.' ),
			$status >= 400 => $this->make_check( 'warning', $answer, 'The site answers, but not with its page. Check the application URL (SMLISER_APP_URL) and the web server configuration.' ),
			$status >= 300 => $this->make_check( 'warning', $answer, 'The redirects did not end at a page. Check for a redirect loop between the web server and the application URL.' ),
			$status >= 200 => $this->make_check( 'pass', $answer ),
			default        => $this->make_check( 'warning', $answer, 'The site answered with an unexpected status.' ),
		};
	}

	/**
	 * Build the check entry.
	 *
	 * @param string      $status
	 * @param string      $message
	 * @param string|null $recommendation
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}
	 */
	private function make_check( string $status, string $message, ?string $recommendation = null ) : array {
		return [
			'id'             => 'site_reachable',
			'label'          => 'Site Reachable',
			'status'         => $status,
			'message'        => $message,
			'recommendation' => $recommendation,
		];
	}
}
